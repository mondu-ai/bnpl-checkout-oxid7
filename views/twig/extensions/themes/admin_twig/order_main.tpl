[{$smarty.block.parent}]

[{if $oView->isMonduPayment()}]
  [{capture assign="orderShipping"}]
    window.onload = function () {
      const updatePaymentMethod = document.querySelector('select[name="setPayment"]');
      const shipButton = document.querySelector('input#shippNowButton');
      const resetButton = document.querySelector('input#resetShippingDateButton');

      if(updatePaymentMethod) {
        updatePaymentMethod.disabled = true;
      }

      if (shipButton) {
        shipButton.addEventListener('mousedown', (event) => {
          event.preventDefault();
          const isConfirmed = confirm("[{oxmultilang ident="MONDU_WILL_CREATE_INVOICE"}]");

          if(isConfirmed) {
            event.target.click();
          }
        });
      }

      if (resetButton) {
        resetButton.addEventListener('mousedown', (event) => {
          event.preventDefault();
          const isConfirmed = confirm("[{oxmultilang ident="MONDU_WILL_CANCEL_INVOICE"}]");

          if(isConfirmed) {
            event.target.click();
          }
        });
      }
    };
  [{/capture}]

  [{oxscript add=$orderShipping}]
[{/if}]