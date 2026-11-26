<?php

namespace OxidEsales\MonduPayment\Core\Mappers;

/**
 * Builds the amount fields of a Mondu order line so that they add up to the OXID total.
 *
 * Mondu computes the order amount from the line as
 * net items + tax_cents - discount_cents + shipping_price_cents + buyer_fee_cents.
 * The OXID total already contains discounts, vouchers, shipping and payment/wrapping costs, and the
 * product VAT is calculated after discounts. So the discount is sent as a net value, and the rounding
 * difference goes into the discount (when the basket has one) or into the tax.
 */
class MonduAmountCalculator
{
    /**
     * @param int   $itemsNetCents sum of net_price_per_item_cents * quantity of the line items
     * @param float $productVat    VAT of the products after discounts, all rates together
     * @param float $shipping      shipping costs, gross
     * @param float $fees          payment, wrapping and gift card costs, gross
     * @param float $total         OXID total, gross
     * @param bool  $hasDiscount   true if the basket/order has a discount or a voucher
     *
     * @return array{gross_amount_cents:int, tax_cents:int, discount_cents:int, shipping_price_cents:int, buyer_fee_cents:int, net_price_cents:int}
     */
    public static function calculate(
        int $itemsNetCents,
        float $productVat,
        float $shipping,
        float $fees,
        float $total,
        bool $hasDiscount
    ): array {
        $grossCents = self::toCents($total);
        $taxCents = self::toCents($productVat);
        $shippingCents = self::toCents($shipping);
        $feeCents = self::toCents($fees);

        $discountCents = $itemsNetCents + $taxCents + $shippingCents + $feeCents - $grossCents;

        if (!$hasDiscount || $discountCents < 0) {
            // no discount to carry the difference, or a rounding difference upwards: keep it in the tax
            $taxCents -= $discountCents;
            $discountCents = 0;
        }

        return [
            'gross_amount_cents' => $grossCents,
            'tax_cents' => $taxCents,
            'discount_cents' => $discountCents,
            'shipping_price_cents' => $shippingCents,
            'buyer_fee_cents' => $feeCents,
            'net_price_cents' => $itemsNetCents - $discountCents,
        ];
    }

    public static function toCents($amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
