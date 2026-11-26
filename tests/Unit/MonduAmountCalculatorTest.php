<?php

namespace OxidEsales\MonduPayment\Tests\Unit;

use OxidEsales\MonduPayment\Core\Mappers\MonduAmountCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MonduAmountCalculatorTest extends TestCase
{
    /**
     * Mondu computes the order amount from the line like this.
     */
    private static function monduAmount(int $itemsNetCents, array $amounts): int
    {
        return $itemsNetCents
            + $amounts['tax_cents']
            - $amounts['discount_cents']
            + $amounts['shipping_price_cents']
            + $amounts['buyer_fee_cents'];
    }

    public static function basketProvider(): array
    {
        // itemsNetCents, productVat, shipping, fees, total, hasDiscount, expected discount_cents
        return [
            'articles only' => [2479, 4.71, 0.0, 0.0, 29.50, false, 0],
            'articles and shipping' => [2479, 4.71, 10.0, 0.0, 39.50, false, 0],
            'payment fee' => [2479, 4.71, 10.0, 2.50, 42.00, false, 0],
            'voucher of 5 EUR' => [2479, 3.91, 10.0, 0.0, 34.50, true, 420],
            '10 % discount' => [2479, 4.24, 10.0, 0.0, 36.55, true, 248],
            'discount, voucher and fee, 2 articles' => [4958, 7.68, 10.0, 2.50, 60.60, true, 916],
            'two VAT rates' => [5750, 7.00, 10.0, 0.0, 74.50, false, 0],
            'two VAT rates with discount, voucher and fee' => [8229, 9.92, 10.0, 2.50, 92.10, true, 1261],
        ];
    }

    #[DataProvider('basketProvider')]
    public function testAmountsAddUpToTheOxidTotal(
        int $itemsNetCents,
        float $productVat,
        float $shipping,
        float $fees,
        float $total,
        bool $hasDiscount,
        int $expectedDiscount
    ): void {
        $amounts = MonduAmountCalculator::calculate($itemsNetCents, $productVat, $shipping, $fees, $total, $hasDiscount);

        $this->assertSame((int) round($total * 100), $amounts['gross_amount_cents']);
        $this->assertSame($amounts['gross_amount_cents'], self::monduAmount($itemsNetCents, $amounts));
        $this->assertSame($expectedDiscount, $amounts['discount_cents']);
        $this->assertSame((int) round($shipping * 100), $amounts['shipping_price_cents']);
        $this->assertSame((int) round($fees * 100), $amounts['buyer_fee_cents']);
        $this->assertSame($itemsNetCents - $amounts['discount_cents'], $amounts['net_price_cents']);
    }

    public function testShippingIsNotLostWhenTheTotalContainsIt(): void
    {
        // the reported case: 29.50 articles + 10.00 shipping
        $amounts = MonduAmountCalculator::calculate(2479, 4.71, 10.0, 0.0, 39.50, false);

        $this->assertSame(3950, $amounts['gross_amount_cents']);
        $this->assertSame(1000, $amounts['shipping_price_cents']);
    }

    public function testRoundingDifferenceWithoutDiscountGoesIntoTheTax(): void
    {
        // 3 x 8.40 net = 25.20, OXID total 30.00 gross: VAT rounded per line is 4.79, one cent off
        $amounts = MonduAmountCalculator::calculate(2520, 4.79, 0.0, 0.0, 30.00, false);

        $this->assertSame(0, $amounts['discount_cents']);
        $this->assertSame(480, $amounts['tax_cents']);
        $this->assertSame(3000, self::monduAmount(2520, $amounts));
    }

    public function testNegativeRoundingDifferenceWithDiscountDoesNotProduceANegativeDiscount(): void
    {
        $amounts = MonduAmountCalculator::calculate(2520, 4.79, 0.0, 0.0, 30.00, true);

        $this->assertSame(0, $amounts['discount_cents']);
        $this->assertSame(480, $amounts['tax_cents']);
        $this->assertSame(3000, self::monduAmount(2520, $amounts));
    }

    public function testToCentsRoundsHalfUp(): void
    {
        $this->assertSame(1, MonduAmountCalculator::toCents(0.005));
        $this->assertSame(3950, MonduAmountCalculator::toCents('39.50'));
        $this->assertSame(0, MonduAmountCalculator::toCents(null));
    }
}
