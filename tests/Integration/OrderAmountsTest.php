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
 * The test changes the shop data (payment costs, a voucher series, discounts, wrappings, a test user, the VAT
 * of one article) and restores it. Config changes (net prices, VAT on top, currency) stay in the test process.
 */
class OrderAmountsTest extends TestCase
{
    private const USER_ID = 'monduamountsuser';
    private const VOUCHER_SERIES_ID = 'monduamountsseries';
    private const PAYMENT_ID = 'oxmondu_invoice';
    private const WRAPPING_ID = 'monduamountswrap';
    private const CARD_ID = 'monduamountscard';

    // discount fixtures, all inactive until a scenario switches one on
    private const DISCOUNTS = [
        'percent' => ['monduamountsdisc', '%', 10],
        'absolute' => ['monduamountsabs', 'abs', 5],
        'article' => ['monduamountsart', '%', 20],
        'bundle' => ['monduamountsitm', 'itm', 0],
    ];

    private static bool $shopLoaded = false;
    private static array $paymentBackup = [];
    private static array $vatBackup = [];
    private static array $configBackup = [];

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
        $config = Registry::getConfig();
        foreach (['blShowNetPrice', 'blDeliveryVatOnTop', 'blPaymentVatOnTop', 'blWrappingVatOnTop', 'blUseStock'] as $param) {
            self::$configBackup[$param] = $config->getConfigParam($param);
        }

        self::removeFixtures();

        $db->execute(
            "insert into oxvoucherseries (oxid, oxshopid, oxserienr, oxdiscount, oxdiscounttype, oxbegindate, oxenddate,
                oxallowsameseries, oxallowotherseries, oxallowuseanother, oxminimumvalue)
             values (?, 1, 'MONDUAMOUNTS', 5, 'absolute', '2020-01-01', '2099-01-01', 1, 1, 1, 0)",
            [self::VOUCHER_SERIES_ID]
        );
        self::assignToShop('oxvoucherseries', self::VOUCHER_SERIES_ID);

        foreach (self::DISCOUNTS as $kind => [$id, $type, $amount]) {
            $db->execute(
                "insert into oxdiscount (oxid, oxshopid, oxactive, oxtitle, oxamount, oxamountto, oxpriceto, oxprice,
                    oxaddsumtype, oxaddsum, oxitmartid, oxitmamount, oxitmmultiple, oxsort)
                 values (?, 1, 0, ?, 1, 999999, 999999, 0, ?, ?, ?, ?, 0, ?)",
                [
                    $id,
                    "Mondu amounts test {$kind}",
                    $type,
                    $amount,
                    $kind === 'bundle' ? self::secondArticleId() : '',
                    $kind === 'bundle' ? 1 : 0,
                    (int) $db->getOne('select max(oxsort) from oxdiscount') + 1,
                ]
            );
            self::assignToShop('oxdiscount', $id);
        }
        // the article discount only applies to the main article
        $db->execute(
            "insert into oxobject2discount (oxid, oxdiscountid, oxobjectid, oxtype) values (?, ?, ?, 'oxarticles')",
            [md5('monduamountsart'), self::DISCOUNTS['article'][0], self::articleId()]
        );

        foreach ([[self::WRAPPING_ID, 'WRAP', 3.0], [self::CARD_ID, 'CARD', 2.5]] as [$id, $type, $price]) {
            $db->execute(
                "insert into oxwrapping (oxid, oxshopid, oxactive, oxtype, oxname, oxprice) values (?, 1, 1, ?, ?, ?)",
                [$id, $type, "Mondu amounts test {$type}", $price]
            );
            self::assignToShop('oxwrapping', $id);
        }

        $user = oxNew(User::class);
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
        self::removeFixtures();
        foreach (self::$vatBackup as $articleId => $vat) {
            $db->execute('update oxarticles set oxvat = ? where oxid = ?', [$vat === false ? null : $vat, $articleId]);
        }
    }

    private static function removeFixtures(): void
    {
        $db = DatabaseProvider::getDb();
        $db->execute('delete from oxvouchers where oxvoucherserieid = ?', [self::VOUCHER_SERIES_ID]);
        self::removeFromShop('oxvoucherseries', self::VOUCHER_SERIES_ID);
        $db->execute('delete from oxvoucherseries where oxid = ?', [self::VOUCHER_SERIES_ID]);
        foreach (self::DISCOUNTS as [$id]) {
            self::removeFromShop('oxdiscount', $id);
            $db->execute('delete from oxobject2discount where oxdiscountid = ?', [$id]);
            $db->execute('delete from oxdiscount where oxid = ?', [$id]);
        }
        foreach ([self::WRAPPING_ID, self::CARD_ID] as $id) {
            self::removeFromShop('oxwrapping', $id);
            $db->execute('delete from oxwrapping where oxid = ?', [$id]);
        }
        $db->execute('delete from oxuser where oxid = ?', [self::USER_ID]);
    }

    /**
     * OXID EE assigns discounts, voucher series and wrappings to shops through mapping tables.
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

    protected function tearDown(): void
    {
        if (!self::$shopLoaded) {
            return;
        }
        $config = Registry::getConfig();
        foreach (self::$configBackup as $param => $value) {
            $config->setConfigParam($param, $value);
        }
        $config->setActShopCurrency(0);
    }

    public static function scenarioProvider(): array
    {
        $s = fn(array $options) => [$options];

        return [
            'articles and shipping' => $s([]),
            'payment fee' => $s(['fee' => 2.50]),
            'voucher' => $s(['voucher' => true]),
            'percent discount' => $s(['discount' => 'percent']),
            'absolute basket discount' => $s(['discount' => 'absolute']),
            'discount on one article' => $s(['discount' => 'article', 'second' => true]),
            'free article as bundle' => $s(['discount' => 'bundle']),
            'fee, voucher and discount, 3 pieces' => $s(['fee' => 2.50, 'voucher' => true, 'discount' => 'percent', 'quantity' => 3]),
            'two VAT rates' => $s(['second' => true]),
            'two VAT rates with fee, voucher and discount' => $s(['fee' => 2.50, 'voucher' => true, 'discount' => 'percent', 'quantity' => 2, 'second' => true]),
            'gift wrapping and greeting card' => $s(['wrapping' => true, 'card' => true]),
            'everything' => $s(['fee' => 2.50, 'voucher' => true, 'discount' => 'absolute', 'quantity' => 2, 'second' => true, 'wrapping' => true, 'card' => true]),
            'net prices (B2B)' => $s(['netto' => true]),
            'net prices with fee, voucher, discount, wrapping, two VAT rates' => $s(['netto' => true, 'fee' => 2.50, 'voucher' => true, 'discount' => 'percent', 'second' => true, 'wrapping' => true, 'card' => true]),
            'shipping, payment and wrapping costs with VAT on top' => $s(['vatOnTop' => true, 'fee' => 2.50, 'wrapping' => true, 'card' => true]),
            'other currency (GBP)' => $s(['currency' => 'GBP', 'fee' => 2.50, 'voucher' => true, 'discount' => 'percent', 'second' => true]),
        ];
    }

    #[DataProvider('scenarioProvider')]
    public function testOrderPayloadAddsUpToTheBasketTotal(array $options): void
    {
        $basket = $this->createBasket($options);

        $mapper = oxNew(MonduOrderMapper::class);
        $mapper->setBasket($basket);
        $payload = $mapper->getMappedOrderData('invoice');
        $line = $payload['lines'][0];

        $total = (int) round($basket->getPrice()->getBruttoPrice() * 100);
        $this->assertSame($basket->getBasketCurrency()->name, $payload['currency']);
        $this->assertSame($total, (int) $payload['gross_amount_cents']);
        $this->assertSame($total, self::monduAmount($line), 'Mondu would compute a different order amount');
        $this->assertSame((int) round($basket->getDeliveryCost()->getBruttoPrice() * 100), $line['shipping_price_cents']);
        $this->assertSame(self::expectedFeeCents($basket), $line['buyer_fee_cents']);
        $this->assertEqualsWithDelta(
            (int) round(array_sum($basket->getProductVats(false)) * 100),
            $line['tax_cents'],
            1,
            'tax_cents must contain all VAT rates (up to one cent of rounding)'
        );
        $this->assertGreaterThanOrEqual(0, $line['discount_cents']);
        $this->assertGreaterThan(0, $line['tax_cents']);
        if (!empty($options['voucher']) || in_array($options['discount'] ?? null, ['percent', 'absolute'], true)) {
            $this->assertGreaterThan(0, $line['discount_cents']);
        }
    }

    #[DataProvider('scenarioProvider')]
    public function testAdjustmentPayloadAddsUpToTheOrderTotal(array $options): void
    {
        $basket = $this->createBasket($options);
        $order = $this->createOrderFromBasket($basket);

        try {
            $payload = oxNew(MonduAdjustmentMapper::class)->getMappedOrderData($order);
            $line = $payload['lines'][0];
            $total = (int) round((float) $order->getFieldData('oxtotalordersum') * 100);

            $this->assertSame((int) round($basket->getPrice()->getBruttoPrice() * 100), $total);
            $this->assertSame($basket->getBasketCurrency()->name, $payload['currency']);
            $this->assertSame($total, $payload['amount']['gross_amount_cents']);
            $this->assertSame($total, self::monduAmount($line), 'Mondu would compute a different order amount');
            $this->assertSame(
                $total,
                $payload['amount']['net_price_cents'] + $payload['amount']['tax_cents']
                    + $line['shipping_price_cents'] + $line['buyer_fee_cents']
            );
            $this->assertGreaterThanOrEqual(0, $line['discount_cents']);
            $this->assertGreaterThan(0, $line['tax_cents']);
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

    private static function expectedFeeCents(Basket $basket): int
    {
        $fee = 0.0;
        foreach ([$basket->getPaymentCost(), $basket->getWrappingCost(), $basket->getGiftCardCost()] as $price) {
            $fee += $price ? $price->getBruttoPrice() : 0;
        }

        return (int) round($fee * 100);
    }

    private static function articleId(): string
    {
        return getenv('E2E_PRODUCT_ID') ?: '7c82af3a29fb1d0f644316e2da9c68c0';
    }

    private static function secondArticleId(): string
    {
        return getenv('E2E_SECOND_PRODUCT_ID') ?: '4817bafbc4508130ea7f787ee4c8d852';
    }

    private function createBasket(array $options): Basket
    {
        $options += [
            'fee' => 0.0, 'voucher' => false, 'discount' => null, 'quantity' => 1, 'second' => false,
            'wrapping' => false, 'card' => false, 'netto' => false, 'vatOnTop' => false, 'currency' => null,
        ];

        $db = DatabaseProvider::getDb();
        $db->execute("update oxpayments set oxaddsum = ?, oxaddsumtype = 'abs' where oxid = ?", [$options['fee'], self::PAYMENT_ID]);
        foreach (self::DISCOUNTS as $kind => [$id]) {
            $db->execute('update oxdiscount set oxactive = ? where oxid = ?', [(int) ($options['discount'] === $kind), $id]);
        }

        $config = Registry::getConfig();
        $config->setConfigParam('blUseStock', false);
        $config->setConfigParam('blShowNetPrice', $options['netto']);
        foreach (['blDeliveryVatOnTop', 'blPaymentVatOnTop', 'blWrappingVatOnTop'] as $param) {
            $config->setConfigParam($param, $options['vatOnTop']);
        }
        if ($options['currency']) {
            $index = null;
            foreach ($config->getCurrencyArray() as $currency) {
                if ($currency->name === $options['currency']) {
                    $index = $currency->id;
                }
            }
            $this->assertNotNull($index, "currency {$options['currency']} is not configured in the shop");
            $config->setActShopCurrency($index);
        } else {
            $config->setActShopCurrency(0);
        }
        // OXID keeps the loaded discounts for the whole process, reload them for this scenario
        Registry::set(\OxidEsales\Eshop\Application\Model\DiscountList::class, null);

        $user = oxNew(User::class);
        $user->load(self::USER_ID);
        Registry::getSession()->setUser($user);

        $basket = oxNew(Basket::class);
        Registry::getSession()->setBasket($basket);
        $basket->setBasketUser($user);
        $basket->addToBasket(self::articleId(), $options['quantity']);
        if ($options['second'] || $options['discount'] === 'bundle') {
            $second = self::secondArticleId();
            if (!array_key_exists($second, self::$vatBackup)) {
                self::$vatBackup[$second] = $db->getOne('select oxvat from oxarticles where oxid = ?', [$second]);
            }
            $db->execute('update oxarticles set oxvat = 7 where oxid = ?', [$second]);
        }
        if ($options['second']) {
            $basket->addToBasket(self::secondArticleId(), 1);
        }
        $basket->setShipping('oxidstandard');
        $basket->setPayment(self::PAYMENT_ID);
        Registry::getSession()->setVariable('paymentid', self::PAYMENT_ID);
        $basket->calculateBasket(true);

        if ($options['wrapping']) {
            foreach ($basket->getContents() as $item) {
                $item->setWrapping(self::WRAPPING_ID);
            }
        }
        if ($options['card']) {
            $basket->setCardId(self::CARD_ID);
        }
        if ($options['voucher']) {
            $code = 'MONDUAMOUNTS' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
            $db->execute(
                "insert into oxvouchers (oxid, oxvouchernr, oxvoucherserieid) values (?, ?, ?)",
                [md5($code), $code, self::VOUCHER_SERIES_ID]
            );
            $basket->addVoucher($code);
        }
        $basket->onUpdate();
        $basket->calculateBasket(true);

        $this->assertSame($options['netto'], (bool) $basket->isCalculationModeNetto(), 'net price mode');
        if ($options['voucher']) {
            $this->assertGreaterThan(0, $basket->getVoucherDiscount()->getBruttoPrice(), 'voucher was not applied');
        }
        if (in_array($options['discount'], ['percent', 'absolute'], true)) {
            $this->assertGreaterThan(0, $basket->getTotalDiscount()->getBruttoPrice(), 'discount was not applied');
        }
        if ($options['discount'] === 'article') {
            // OXID applies an article discount to the article price itself, not as a basket discount
            $article = oxNew(\OxidEsales\Eshop\Application\Model\Article::class);
            $article->load(self::articleId());
            $item = $basket->getContents()[array_key_first($basket->getContents())];
            $this->assertLessThan(
                $article->getFieldData('oxprice'),
                $item->getUnitPrice()->getBruttoPrice(),
                'article discount was not applied'
            );
            $this->assertSame(0.0, (float) $basket->getTotalDiscount()->getBruttoPrice());
        }
        if ($options['discount'] === 'bundle') {
            $this->assertCount(2, $basket->getContents(), 'bundle article was not added');
        }
        if ($options['fee'] > 0) {
            $this->assertGreaterThan(0, $basket->getPaymentCost()->getBruttoPrice(), 'payment fee was not applied');
        }
        if ($options['wrapping']) {
            $this->assertGreaterThan(0, $basket->getWrappingCost()->getBruttoPrice(), 'wrapping was not applied');
        }
        if ($options['card']) {
            $this->assertGreaterThan(0, $basket->getGiftCardCost()->getBruttoPrice(), 'greeting card was not applied');
        }

        return $basket;
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
