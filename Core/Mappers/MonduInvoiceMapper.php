<?php

namespace OxidEsales\MonduPayment\Core\Mappers;

use OxidEsales\MonduPayment\Core\Utils\MonduHelper;

class MonduInvoiceMapper
{
  public function getMappedInvoiceData($order)
  {
    $invoiceLineItems = $this->getInvoiceLineItems($order);

    return MonduHelper::removeEmptyElementsFromArray([
      'external_reference_id' => $order->getFieldData('oxorder__oxordernr') ? (string) $order->getFieldData('oxorder__oxordernr') : $order->getId(),
      'invoice_url' => 'http://localhost',
      'gross_amount_cents' => round($order->getFieldData('oxtotalordersum') * 100),
      'line_items' => $invoiceLineItems
    ]);
  }

  protected function getInvoiceLineItems($order)
  {
    $items = array_values($order->getOrderArticles(true)->getArray());

    foreach ($items as $lineItem) {
      $lineItems[] = [
        'external_reference_id' => $lineItem->getProductId(),
        'quantity' => (int) $lineItem->getFieldData('oxamount')
      ];
    }

    return $lineItems;
  }
}
