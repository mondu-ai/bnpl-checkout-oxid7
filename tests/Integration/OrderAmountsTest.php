<?php

namespace OxidEsales\MonduPayment\Tests\Integration;

use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\MonduPayment\Core\Mappers\MonduAdjustmentMapper;
use OxidEsales\MonduPayment\Core\Mappers\MonduOrderMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Builds real OXID baskets and orders and checks that the Mondu payload adds up to the OXID total.
 *
 * Needs an OXID 7 shop with this module and the demo data, see tests/Integration/README.md.
 * The test changes the shop data (payment costs, a voucher series, a discount, a test user) and restores it.
 */
class OrderAmountsTest extends TestCase
{
    private const USER_ID = 'monduamountsuser';
    private const VOUCHER_SERIES_ID = 'monduamountsseries';
    private const DISCOUNT_ID = 'monduamountsdisc';
    private const PAYMENT_ID = 'oxmondu_invoice';

    private static bool $shopLoaded = false;
    private static array $paymentBackup = [];
    private static array $vatBackup = [];

    public static function setUpBeforeClass(): void
    {
        $bootstrap = getenv('OXID_BOOTSTRAP');
        if (!$bootstrap || !is_file($bootstrap)) {
            return;
        }

        require_once $bootstrap;
        self::$shopLoaded = true;

        $db = DatabaseProvider::getDb();
        self::$paymentBackup = $db->getRow(
            'select oxaddsum, oxaddsumtype from oxpayments where oxid = ?',
            [self::PAYMENT_ID]
        );

        $db->execute('delete from oxvoucherseries where oxid = ?', [self::VOUCHER_SERIES_ID]);
        $db->execute(
            "insert into oxvoucherseries (oxid, oxshopid, oxserienr, oxdiscount, oxdiscounttype, oxbegindate, oxenddate,
                oxallowsameseries, oxallowotherseries, oxallowuseanother, oxminimumvalue)
             values (?, 1, 'MONDUAMOUNTS', 5, 'absolute', '2020-01-01', '2099-01-01', 1, 1, 1, 0)",
            [self::VOUCHER_SERIES_ID]
        );

        $db->execute('delete from oxdiscount where oxid = ?', [self::DISCOUNT_ID]);
        $db->execute(
            "insert into oxdiscount (oxid, oxshopid, oxactive, oxtitle, oxamount, oxamountto, oxpriceto, oxprice,
                oxaddsumtype, oxaddsum, oxsort)
             values (?, 1, 0, 'Mondu amounts test 10%', 1, 999999, 999999, 0, '%', 10, ?)",
            [self::DISCOUNT_ID, (int) $db->getOne('select max(oxsort) from oxdiscount') + 1]
        );

        self::assignToShop('oxdiscount', self::DISCOUNT_ID);
        self::assignToShop('oxvoucherseries', self::VOUCHER_SERIES_ID);

        $user = oxNew(User::class);
        if (!$user->load(self::USER_ID)) {
            $user->setId(self::USER_ID);
            $user->assign([
                'oxusername' => 'mondu.amounts@example.com',
                'oxfname' => 'Max',
                'oxlname' => 'Mustermann',
                'oxcompany' => 'Mondu GmbH',
                'oxstreet' => 'Strassmannstr.',
                'oxstreetnr' => '45',
                'oxzip' => '10122',
                'oxcity' => 'Berlin',
                'oxcountryid' => $db->getOne("select oxid from oxcountry where oxisoalpha2 = 'DE'"),
                'oxactive' => 1,
                'oxshopid' => 1,
            ]);
            $user->save();
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (!self::$shopLoaded) {
            return;
        }

        $db = DatabaseProvider::getDb();
        $db->execute(
            'update oxpayments set oxaddsum = ?, oxaddsumtype = ? where oxid = ?',
            [self::$paymentBackup['oxaddsum'] ?? 0, self::$paymentBackup['oxaddsumtype'] ?? 'abs', self::PAYMENT_ID]
        );
        $db->execute('delete from oxvouchers where oxvoucherserieid = ?', [self::VOUCHER_SERIES_ID]);
        self::removeFromShop('oxvoucherseries', self::VOUCHER_SERIES_ID);
        $db->execute('delete from oxvoucherseries where oxid = ?', [self::VOUCHER_SERIES_ID]);
        self::removeFromShop('oxdiscount', self::DISCOUNT_ID);
        $db->execute('delete from oxdiscount where oxid = ?', [self::DISCOUNT_ID]);
        $db->execute('delete from oxuser where oxid = ?', [self::USER_ID]);
        foreach (self::$vatBackup as $articleId => $vat) {
            $db->execute('update oxarticles set oxvat = ? where oxid = ?', [$vat === false ? null : $vat, $articleId]);
        }
    }

    /**
     * OXID EE assigns discounts and voucher series to shops through mapping tables.
     */
    private static function assignToShop(string $table, string $oxid): void
    {
        $db = DatabaseProvider::getDb();
        if (!$db->getOne("show tables like '{$table}2shop'")) {
            return;
        }
        $db->execute(
            "insert ignore into {$table}2shop (oxshopid, oxmapobjectid) select 1, oxmapid from {$table} where oxid = ?",
            [$oxid]
        );
    }

    private static function removeFromShop(string $table, string $oxid): void
    {
        $db = DatabaseProvider::getDb();
        if (!$db->getOne("show tables like '{$table}2shop'")) {
            return;
        }
        $db->execute(
            "delete m from {$table}2shop m join {$table} t on t.oxmapid = m.oxmapobjectid where t.oxid = ?",
            [$oxid]
        );
    }

    protected function setUp(): void
    {
        if (!self::$shopLoaded) {
            $this->markTestSkipped('OXID_BOOTSTRAP is not set, see tests/Integration/README.md');
        }
    }

    public static function scenarioProvider(): array
    {
        // payment fee, voucher, 10 % discount, quantity, second article (other VAT rate)
        return [
            'articles and shipping' => [0.0, false, false, 1, false],
            'payment fee' => [2.50, false, false, 1, false],
            'voucher' => [0.0, true, false, 1, false],
            'discount' => [0.0, false, true, 1, false],
            'fee, voucher and discount, 3 pieces' => [2.50, true, true, 3, false],
            'two VAT rates' => [0.0, false, false, 1, true],
            'two VAT rates with fee, voucher and discount' => [2.50, true, true, 2, true],
        ];
    }

    #[DataProvider('scenarioProvider')]
    public function testOrderPayloadAddsUpToTheBasketTotal(
        float $fee,
        bool $voucher,
        bool $discount,
        int $quantity,
        bool $secondVatRate
    ): void {
        $basket = $this->createBasket($fee, $voucher, $discount, $quantity, $secondVatRate);

        $mapper = oxNew(MonduOrderMapper::class);
        $mapper->setBasket($basket);
        $payload = $mapper->getMappedOrderData('invoice');
        $line = $payload['lines'][0];

        $total = (int) round($basket->getPrice()->getBruttoPrice() * 100);
        $this->assertSame($total, (int) $payload['gross_amount_cents']);
        $this->assertSame($total, self::monduAmount($line), 'Mondu would compute a different order amount');
        $this->assertSame((int) round($basket->getDeliveryCost()->getBruttoPrice() * 100), $line['shipping_price_cents']);
        $this->assertSame((int) round($fee * 100), $line['buyer_fee_cents']);
        $this->assertEqualsWithDelta(
            (int) round(array_sum($basket->getProductVats(false)) * 100),
            $line['tax_cents'],
            1,
            'tax_cents must contain all VAT rates (up to one cent of rounding)'
        );
        if ($voucher || $discount) {
            $this->assertGreaterThan(0, $line['discount_cents']);
        } else {
            $this->assertSame(0, $line['discount_cents']);
        }
    }

    #[DataProvider('scenarioProvider')]
    public function testAdjustmentPayloadAddsUpToTheOrderTotal(
        float $fee,
        bool $voucher,
        bool $discount,
        int $quantity,
        bool $secondVatRate
    ): void {
        $basket = $this->createBasket($fee, $voucher, $discount, $quantity, $secondVatRate);
        $order = $this->createOrderFromBasket($basket);

        try {
            $payload = oxNew(MonduAdjustmentMapper::class)->getMappedOrderData($order);
            $line = $payload['lines'][0];
            $total = (int) round((float) $order->getFieldData('oxtotalordersum') * 100);

            $this->assertSame((int) round($basket->getPrice()->getBruttoPrice() * 100), $total);
            $this->assertSame($total, $payload['amount']['gross_amount_cents']);
            $this->assertSame($total, self::monduAmount($line), 'Mondu would compute a different order amount');
            $this->assertSame(
                $total,
                $payload['amount']['net_price_cents'] + $payload['amount']['tax_cents']
                    + $line['shipping_price_cents'] + $line['buyer_fee_cents']
            );
        } finally {
            $order->delete();
        }
    }

    private static function monduAmount(array $line): int
    {
        $net = 0;
        foreach ($line['line_items'] as $item) {
            $net += $item['net_price_per_item_cents'] * $item['quantity'];
        }

        return $net + $line['tax_cents'] - $line['discount_cents'] + $line['shipping_price_cents'] + $line['buyer_fee_cents'];
    }

    private function createBasket(float $fee, bool $voucher, bool $discount, int $quantity, bool $secondVatRate): Basket
    {
        $db = DatabaseProvider::getDb();
        $db->execute("update oxpayments set oxaddsum = ?, oxaddsumtype = 'abs' where oxid = ?", [$fee, self::PAYMENT_ID]);
        $db->execute('update oxdiscount set oxactive = ? where oxid = ?', [(int) $discount, self::DISCOUNT_ID]);
        Registry::getConfig()->setConfigParam('blUseStock', false);
        // OXID keeps the loaded discounts for the whole process, reload them for this scenario
        Registry::set(\OxidEsales\Eshop\Application\Model\DiscountList::class, null);

        $user = oxNew(User::class);
        $user->load(self::USER_ID);
        Registry::getSession()->setUser($user);

        $basket = oxNew(Basket::class);
        Registry::getSession()->setBasket($basket);
        $basket->setBasketUser($user);
        $basket->addToBasket(self::articleId('E2E_PRODUCT_ID', '7c82af3a29fb1d0f644316e2da9c68c0'), $quantity);
        if ($secondVatRate) {
            $second = self::articleId('E2E_SECOND_PRODUCT_ID', '4817bafbc4508130ea7f787ee4c8d852');
            if (!array_key_exists($second, self::$vatBackup)) {
                self::$vatBackup[$second] = $db->getOne('select oxvat from oxarticles where oxid = ?', [$second]);
            }
            $db->execute('update oxarticles set oxvat = 7 where oxid = ?', [$second]);
            $basket->addToBasket($second, 1);
        }
        $basket->setShipping('oxidstandard');
        $basket->setPayment(self::PAYMENT_ID);
        Registry::getSession()->setVariable('paymentid', self::PAYMENT_ID);
        $basket->calculateBasket(true);

        if ($voucher) {
            $code = 'MONDUAMOUNTS' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
            $db->execute(
                "insert into oxvouchers (oxid, oxvouchernr, oxvoucherserieid) values (?, ?, ?)",
                [md5($code), $code, self::VOUCHER_SERIES_ID]
            );
            $basket->addVoucher($code);
            $basket->calculateBasket(true);
            $this->assertGreaterThan(0, $basket->getVoucherDiscount()->getBruttoPrice(), 'voucher was not applied');
        }
        if ($discount) {
            $this->assertGreaterThan(0, $basket->getTotalDiscount()->getBruttoPrice(), 'discount was not applied');
        }
        if ($fee > 0) {
            $this->assertEqualsWithDelta($fee, $basket->getPaymentCost()->getBruttoPrice(), 0.001, 'payment fee was not applied');
        }

        return $basket;
    }

    private static function articleId(string $env, string $default): string
    {
        return getenv($env) ?: $default;
    }

    /**
     * Stores the basket as an order with its articles, the same way OXID does in finalizeOrder,
     * without executing the payment.
     */
    private function createOrderFromBasket(Basket $basket): Order
    {
        $order = new class () extends Order {
            public function storeFromBasket(Basket $basket, User $user): void
            {
                $this->setId(md5(uniqid('mondu-amounts', true)));
                $this->assignUserInformation($user);
                $this->loadFromBasket($basket);
                $this->setPayment($basket->getPaymentId());
                $this->setOrderArticles($basket->getContents());
                $this->save();
            }
        };
        $order->storeFromBasket($basket, $basket->getBasketUser());

        $loaded = oxNew(Order::class);
        $loaded->load($order->getId());

        return $loaded;
    }
}
