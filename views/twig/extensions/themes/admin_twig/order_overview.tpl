<tr>
  <td class="edittext">[{oxmultilang ident="ORDER_OVERVIEW_PAYMENTTYPE"}]: </td>
  <td class="edittext"><b>[{$paymentType->oxpayments__oxdesc->value}]</b></td>
</tr>
<tr>
  <td class="edittext">[{oxmultilang ident="ORDER_OVERVIEW_DELTYPE"}]: </td>
  <td class="edittext"><b>[{$deliveryType->oxdeliveryset__oxtitle->value}]</b><br></td>
</tr>

[{if $oView->isMonduPayment()}]
  [{if $oemonduAuthorizedNetTerm}]
    <tr>
      <td class="edittext">[{oxmultilang ident="ORDER_OVERVIEW_AUTHORIZED_NET_TERM"}]: </td>
      <td class="edittext"><b>[{$oemonduAuthorizedNetTerm}]</b><br></td>
    </tr>
  [{/if}]

  [{capture assign="orderShipping"}]
    window.onload = function () {
      const shipForm = document.querySelector('form#sendorder');
      const resetShippingForm = document.querySelector('form#resetorder');

      if (shipForm) {
        shipForm.addEventListener('submit', (event) => {
          event.preventDefault();
          const isConfirmed = confirm("[{oxmultilang ident="MONDU_WILL_CREATE_INVOICE"}]");

          if(isConfirmed) {
            event.target.submit();
          }
        });
      }

      if (resetShippingForm) {
        resetShippingForm.addEventListener('submit', (event) => {
          event.preventDefault();
          const isConfirmed = confirm("[{oxmultilang ident="MONDU_WILL_CANCEL_INVOICE"}]");

          if(isConfirmed) {
            event.target.submit();
          }
        });
      }
    };
  [{/capture}]

  [{oxscript add=$orderShipping}]
[{/if}]