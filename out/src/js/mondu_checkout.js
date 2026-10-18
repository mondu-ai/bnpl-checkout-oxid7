class MonduCheckout {
    init() {
        this._registerProperties();
        this._registerEvents();
    }

    _registerProperties() {
        this._form = document.getElementById('orderConfirmAgbBottom');
        this._submitButton = document.querySelector('button.btn.btn-highlight.btn-lg.w-100');
        this._inputEl = document.getElementById('mondu-checkout-input');
        this._paymentUrl = paymentUrl;
    }

    _registerEvents() {
        // The hidden input is rendered only when a Mondu payment method is selected,
        // other payment methods submit the order form as usual.
        if (!this._form || !this._inputEl) {
            return;
        }

        this._form.addEventListener('submit', this._submitForm.bind(this));

        if (this._submitButton) {
            this._submitButton.onclick = (e) => {
                e.preventDefault();
                this._form.requestSubmit();
            };
        }
    }

    async _submitForm(event) {
        event.preventDefault();

        const monduOrderData = await this._getMonduOrderData();

        if (monduOrderData && monduOrderData.hostedCheckoutUrl) {
            window.location.href = monduOrderData.hostedCheckoutUrl;
            return;
        }

        window.location.href = this._paymentUrl;
    }

    async _getMonduOrderData() {
        try {
            const client = new HttpRequest();
            const { data } = await client.post('?cl=oemonducheckout&fnc=createOrder', {});

            if (data.token !== 'error') {
                return data;
            } else {
                return null;
            }
        } catch (e) {
            return null;
        }
    }
}

function monduStart() {
    const mondu = new MonduCheckout();
    mondu.init();
}

document.addEventListener('DOMContentLoaded', monduStart);
