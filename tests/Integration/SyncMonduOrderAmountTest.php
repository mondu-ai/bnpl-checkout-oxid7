<?php

namespace OxidEsales\MonduPayment\Tests\Integration;

use OxidEsales\Eshop\Application\Controller\OrderController;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\MonduPayment\Core\Http\MonduClient;
use PHPUnit\Framework\TestCase;

/**
 * OrderController::syncMonduOrderAmount() after the buyer comes back from the hosted checkout, with a Mondu
 * client that answers like the API would. The Mondu sandbox accepts every adjustment, so the declined case
 * can only be tested this way.
 *
 * Needs an OXID 7 shop with this module, see tests/Integration/README.md.
 */
class SyncMonduOrderAmountTest extends TestCase
{
    private static bool $shopLoaded = false;
    private ?string $orderId = null;

    public static function setUpBeforeClass(): void
    {
        $bootstrap = getenv('OXID_BOOTSTRAP');
        if ($bootstrap && is_file($bootstrap)) {
            require_once $bootstrap;
            self::$shopLoaded = true;
        }
    }

    protected function setUp(): void
    {
        if (!self::$shopLoaded) {
            $this->markTestSkipped('OXID_BOOTSTRAP is not set, see tests/Integration/README.md');
        }

        // an OXID order of 39.50 EUR: 29.50 EUR article (19 % VAT) and 10.00 EUR shipping
        $this->orderId = md5(uniqid('mondu-sync', true));
        $db = DatabaseProvider::getDb();
        $db->execute(
            "insert into oxorder (oxid, oxshopid, oxordernr, oxtotalnetsum, oxtotalbrutsum, oxtotalordersum, oxartvat1,
                oxartvatprice1, oxdelcost, oxpaycost, oxwrapcost, oxgiftcardcost, oxdiscount, oxvoucherdiscount,
                oxcurrency, oxcurrate, oxfolder, oxtransstatus)
             values (?, 1, ?, 24.79, 29.50, 39.50, 19, 4.71, 10, 0, 0, 0, 0, 0, 'EUR', 1, 'ORDERFOLDER_NEW', 'OK')",
            [$this->orderId, random_int(100000000, 999999999)]
        );
        $db->execute(
            "insert into oxorderarticles (oxid, oxorderid, oxamount, oxartid, oxartnum, oxtitle, oxnetprice, oxbrutprice,
                oxvatprice, oxvat, oxprice, oxbprice, oxnprice)
             values (?, ?, 1, '7c82af3a29fb1d0f644316e2da9c68c0', 'TEST', 'Test article', 24.79, 29.50, 4.71, 19, 29.50, 29.50, 24.79)",
            [md5($this->orderId), $this->orderId]
        );
    }

    protected function tearDown(): void
    {
        if ($this->orderId) {
            $db = DatabaseProvider::getDb();
            $db->execute('delete from oxorderarticles where oxorderid = ?', [$this->orderId]);
            $db->execute('delete from oxorder where oxid = ?', [$this->orderId]);
        }
    }

    public function testNothingHappensWhenTheAmountsMatch(): void
    {
        $client = $this->client([3950]);
        $this->sync($client);

        $this->assertSame([], $client->adjustments);
        $this->assertSame('ORDERFOLDER_NEW', $this->folder());
    }

    public function testTheMonduOrderIsAdjustedToTheOxidOrder(): void
    {
        // Mondu has the order without shipping, after the adjustment it has the OXID total
        $client = $this->client([2950, 3950]);
        $this->sync($client);

        $this->assertCount(1, $client->adjustments);
        $adjustment = $client->adjustments[0];
        $this->assertSame(3950, $adjustment['amount']['gross_amount_cents']);
        $this->assertSame(1000, $adjustment['lines'][0]['shipping_price_cents']);
        $this->assertSame('ORDERFOLDER_NEW', $this->folder());
    }

    public function testADeclinedAdjustmentMovesTheOrderToTheProblemsFolder(): void
    {
        // Mondu keeps 29.50 EUR, the adjustment to 39.50 EUR is not accepted
        $client = $this->client([2950, 2950]);
        $this->sync($client);

        $this->assertCount(1, $client->adjustments);
        $this->assertSame('ORDERFOLDER_PROBLEMS', $this->folder());
    }

    public function testNothingHappensWhenTheMonduOrderCanNotBeLoaded(): void
    {
        $client = $this->client([null]);
        $this->sync($client);

        $this->assertSame([], $client->adjustments);
        $this->assertSame('ORDERFOLDER_NEW', $this->folder());
    }

    /**
     * @param array<int|null> $monduAmounts real_price_cents of each GET of the Mondu order, null for a failed request
     */
    private function client(array $monduAmounts): MonduClient
    {
        return new class ($monduAmounts) extends MonduClient {
            public array $adjustments = [];

            public function __construct(private array $monduAmounts)
            {
            }

            public function getMonduOrder($orderUuid)
            {
                $amount = array_shift($this->monduAmounts);

                return $amount === null ? null : ['uuid' => $orderUuid, 'real_price_cents' => $amount];
            }

            public function adjustOrder($orderUuid, $data = [])
            {
                $this->adjustments[] = $data;

                return ['uuid' => $orderUuid];
            }
        };
    }

    private function sync(MonduClient $client): void
    {
        $controller = oxNew(OrderController::class);
        $class = new \ReflectionClass(\OxidEsales\MonduPayment\Controller\OrderController::class);
        $class->getProperty('_client')->setValue($controller, $client);
        $class->getMethod('syncMonduOrderAmount')->invoke($controller, $this->orderId, 'test-order-uuid');
    }

    private function folder(): string
    {
        $order = oxNew(Order::class);
        $order->load($this->orderId);

        return (string) $order->getFieldData('oxfolder');
    }
}
