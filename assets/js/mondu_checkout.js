class MonduCheckout {
    init() {
        this._isWidgetLoaded = this._initWidget(widgetUrl);
        this._registerProperties();
        this._registerEvents();
    }

    async _initWidget(src) {
        return new Promise((resolve, reject) => {
            const widget = document.createElement('script');
            widget.src = src;
            document.head.appendChild(widget);

            widget.onload = () => resolve(true);
            widget.onerror = () => resolve(false);
        });
    }

    _registerProperties() {
        this._form = document.getElementById('orderConfirmAgbBottom');
        this._submitButton = document.querySelector('button.btn.btn-highlight.btn-lg.w-100');
        this._inputEl = document.getElementById('mondu-checkout-input');
        this._paymentUrl = paymentUrl;
    }

    _registerEvents() {
        if (this._form) {
            this._form.addEventListener('submit', this._submitForm.bind(this));
        }

        if (this._submitButton && this._inputEl) {
            this._submitButton.onclick = (e) => {
                e.preventDefault();
                if (this._form) {
                    this._form.requestSubmit();
                }
            };
        }
    }

    async _submitForm(event) {
        if (this._isWidgetComplete()) {
            event.preventDefault();
            return true;
        }

        event.preventDefault();

        if (this._isWidgetLoaded) {
            const monduOrderData = await this._getMonduOrderData();

            console.log(monduOrderData);
            if (!monduOrderData || !monduOrderData.token) {
                window.location.href = this._paymentUrl;
            }

            if (monduOrderData.hostedCheckoutUrl) {
                window.location.href = monduOrderData.hostedCheckoutUrl;
            } else {
                this._renderWidget(monduOrderData.token);
            }
        }
    }

    async _createMonduOrder() {
        return new Promise((resolve, reject) => {
            const widget = document.createElement('script');
            widget.src = src;
            document.head.appendChild(widget);

            widget.onload = () => resolve(true);
            widget.onerror = () => resolve(false);
        });
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

    _renderWidget(token) {
        const that = this;
        const removeWidgetContainer = this._removeWidgetContainer.bind(this);

        window.monduCheckout.render({
            token,
            onClose() {
                removeWidgetContainer();
                if (that._isWidgetComplete()) {
                    that._form.submit();
                } else {
                    window.location.href = that._paymentUrl;
                }
            },
            onSuccess() {
                that._setMonduComplete('1');
            }
        });
    }

    _removeWidgetContainer() {
        const widgetContainer = document.getElementById('mondu-checkout-widget');
        if (widgetContainer) {
            widgetContainer.style.display = 'none';
            window.monduCheckout.destroy();
        }
    }

    _setMonduComplete(flag) {
        this._inputEl.dataset.monduComplete = parseInt(flag, 10);
    }

    _isWidgetComplete() {
        return parseInt(this._inputEl.dataset.monduComplete, 10) === 1;
    }
}

function monduStart() {
    if (!widgetUrl) {
        var widgetUrl = 'http://localhost:3002/widget.js';
    }

    var mondu = new MonduCheckout();
    mondu.init();
}

document.addEventListener('DOMContentLoaded', monduStart);