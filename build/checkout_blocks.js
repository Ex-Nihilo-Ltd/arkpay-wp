const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { createElement } = window.wp.element;

const settings = window.wc.wcSettings.getSetting( 'arkpay_payment_data', {} );
const label = window.wp.htmlEntities.decodeEntities(settings.title) || window.wp.i18n.__('Credit card (ArkPay)', 'arkpay_payment');

const arkpayElements = createElement('div', { className: 'arkpay-form-container' },
    createElement('div', { className: 'field-container' },
        createElement('label', { htmlFor: 'name' }, 'Holder Name'),
        createElement('input', { id: 'name', name: 'name', maxLength: 30, type: 'text', placeholder: 'Holder Name' })
    ),
    createElement('div', { className: 'field-container' },
        createElement('label', { htmlFor: 'cardnumber' }, 'Card Number'),
        createElement('input', { id: 'cardnumber', name: 'cardnumber', type: 'text', placeholder: 'Card Number' }),
        createElement('span', { id: 'invalid-card-number-message' }, 'Invalid Card Number')
    ),
    createElement('div', { className: 'field-container' },
        createElement('label', { htmlFor: 'expirationdate' }, 'Expiration (mm/yy)'),
        createElement('input', { id: 'expirationdate', name: 'expirationdate', type: 'text', placeholder: 'Expiration Date' })
    ),
    createElement('div', { className: 'field-container' },
        createElement('label', { htmlFor: 'securitycode' }, 'Security Code'),
        createElement('input', { id: 'securitycode', name: 'securitycode', type: 'text', placeholder: 'CVC' })
    )
);

const options = {
    name: 'arkpay_payment',
    label: label,
    content: arkpayElements,
    edit: arkpayElements,
    canMakePayment: () => true,
    paymentMethodId: 'arkpay_payment',
    ariaLabel: label,
}

registerPaymentMethod( options );


jQuery( function( $ ) {
    $( window ).load( function() {
        $('#radio-control-wc-payment-method-options-arkpay_payment__label').append('<img src="' + settings.icon + '" alt="ArkPay Logo" style="margin-left: 10px;" />');

        // Card Number validation
        function validateCardNumber( cardNumber ) {
            var cardRegex = {
                'MasterCard': /^5[1-5][0-9]{14}$|^2(?:2(?:2[1-9]|[3-9][0-9])|[3-6][0-9][0-9]|7(?:[01][0-9]|20))[0-9]{12}$/,
                'AmericanExpress': /^3[47][0-9]{13}$/,
                'Visa': /^4[0-9]{12}(?:[0-9]{3})?$/,
                'Discover': /^65[4-9][0-9]{13}|64[4-9][0-9]{13}|6011[0-9]{12}|(622(?:12[6-9]|1[3-9][0-9]|[2-8][0-9][0-9]|9[01][0-9]|92[0-5])[0-9]{10})$/,
                'Maestro': /^(5018|5081|5044|5020|5038|603845|6304|6759|676[1-3]|6799|6220|504834|504817|504645)[0-9]{8,15}$/,
                'JCB': /^(?:2131|1800|35[0-9]{3})[0-9]{11}$/,
                'DinersClub': /^3(?:0[0-5]|[68][0-9])[0-9]{11}$/
            };

            for ( var cardType in cardRegex ) {
                if ( cardRegex[ cardType ].test( cardNumber ) ) {
                    return true;
                }
            }

            return false;
        }

        // Credit card format
        function creditCardDetailsFormat() {
            var cardNumber      = document.getElementById('cardnumber');
            var expirationDate  = document.getElementById('expirationdate');
            var securityCode    = document.getElementById('securitycode');

            if ( cardNumber && expirationDate && securityCode ) {
                var placeOrderButton = $('button.components-button.wc-block-components-checkout-place-order-button');
                var invalidCardNumberBox = $('#invalid-card-number-message');

                if ( cardNumber.length == 0 || cardNumber.value.replace(/\D/g, '').substring(0, 16).length < 16 ) {
                    placeOrderButton.attr("disabled", true);
                }

                cardNumber.addEventListener('input', function () {
                    this.value = this.value.replace(/\D/g, '').substring(0, 16).replace(/(\d{4})(?=\d)/g, '$1 ');

                    var creditCardNumber = this.value.replace(/\D/g, '').substring(0, 16);
                    if ( creditCardNumber.length === 16 ) {
                        if ( validateCardNumber( creditCardNumber ) ) {
                            placeOrderButton.attr("disabled", false);
                            invalidCardNumberBox.css({'display': 'none'})
                        } else {
                            placeOrderButton.attr("disabled", true);
                            invalidCardNumberBox.css({'display': 'flex'})
                        }
                    } else {
                        placeOrderButton.attr("disabled", true);
                    }
                });
                
                expirationDate.addEventListener('input', function () {
                    this.value = this.value.replace(/\D/g, '').substring(0, 4).replace(/(\d{2})(\d{0,2})/, '$1/$2');
                });
                
                securityCode.addEventListener('input', function () {
                    this.value = this.value.replace(/\D/g, '').substring(0, 3);
                });
            }
        }
        creditCardDetailsFormat();

        // Place Order Button Text Change
        function changeOrderButtonText() {
            var placeOrderButtonText = document.querySelector('button.components-button.wc-block-components-checkout-place-order-button > span.wc-block-components-button__text');
            if ( placeOrderButtonText ) {
                var defaultOrderButtonText = placeOrderButtonText.innerHTML;
            }
            var arkPaySelected = false;

            var checkedRadio = $('input[name="radio-control-wc-payment-method-options"]:checked');
            if ( checkedRadio.attr('value') === 'arkpay_payment' ) {
                placeOrderButtonText.innerHTML = settings.button_text;
            }

            $('input[name="radio-control-wc-payment-method-options"]').change(function() {
                arkPaySelected = false;
                var checkedRadio = $('input[name="radio-control-wc-payment-method-options"]:checked');
                var labelId = checkedRadio.closest('.wc-block-components-radio-control-accordion-option').find('.wc-block-components-radio-control__label').attr('id');
            
                if ( labelId.indexOf( 'arkpay_payment' ) !== -1 ) {
                    arkPaySelected = true;
                }

                if ( arkPaySelected ) {
                    placeOrderButtonText.innerHTML = settings.button_text;
                } else {
                    placeOrderButtonText.innerHTML = defaultOrderButtonText;
                }
            });
        }
        changeOrderButtonText();
    } );

} );

const originalFetch = window.fetch;

window.fetch = async function ( url, options ) {
    if ( url.includes('https://arkpay-wp.exnihilo.dev/wp-json/wc/store/v1/checkout') ) {
        if ( options.body && options.method === 'POST' && options.headers && options.headers['Content-Type'] === 'application/json' ) {
            var holderName      = document.getElementById('name');
            var cardNumber      = document.getElementById('cardnumber');
            var expirationDate  = document.getElementById('expirationdate');
            var securityCode    = document.getElementById('securitycode');
            var decodedBody     = JSON.parse( options.body );
            decodedBody.payment_data[1] = { key: 'name', value: holderName.value };
            decodedBody.payment_data[2] = { key: 'cardnumber', value: cardNumber.value };
            decodedBody.payment_data[3] = { key: 'expirationdate', value: expirationDate.value };
            decodedBody.payment_data[4] = { key: 'securitycode', value: securityCode.value };
            decodedBody.payment_data[5] = { key: 'is_block', value: true };

            arguments[1].body = JSON.stringify( decodedBody );
        }
    }

    const response = await originalFetch.apply( this, arguments );

    if ( 200 !== response.status ) {
        const responseBodyText = JSON.parse( await response.text() );
    
        function checkElement() {
            var checkoutNoticeMessage = document.querySelector( 'div.wc-block-components-notices > div > div > div' );
            if ( checkoutNoticeMessage ) {
                clearInterval( checkInterval );
                if ( responseBodyText.data ) {
                    checkoutNoticeMessage.innerHTML = responseBodyText.data;
                }
            }
        }
        var checkInterval = setInterval( checkElement, 50 );
    } else {
        return response;
    }
};
