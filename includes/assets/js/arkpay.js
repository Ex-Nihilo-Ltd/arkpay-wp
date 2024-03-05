jQuery( function( $ ) {

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

    // Credit card details format and validation
    function creditCardDetailsFormat() {
        var holderName                  = document.getElementById('name');
        var cardNumber                  = document.getElementById('cardnumber');
        var expirationDate              = document.getElementById('expirationdate');
        var securityCode                = document.getElementById('securitycode');
        var holderNameValidation        = false;
        var cardNumberValidation        = false;
        var expirationDateValidation    = false;
        var securityCodeValidation      = false;
        
        if ( holderName && cardNumber && expirationDate && securityCode ) {
            var invalidHolderNameBox = $('#invalid-holder-name-message');
            var invalidCardNumberBox = $('#invalid-card-number-message');
            var invalidExpirationDateBox = $('#invalid-expiration-date-message');
            var invalidCVCBox = $('#invalid-cvc-message');

            if ( holderName.value.trim().split(/\s+/).length > 1 ) {
                holderNameValidation = true;
            }

            if ( cardNumber.value.replace(/\D/g, '').substring(0, 16).length === 16 ) {
                cardNumberValidation = true;
            }
            
            if ( expirationDate.value.match(/^(0[1-9]|1[0-2])\/[0-9]{2}$/) ) {
                expirationDateValidation = true;
            }
            
            if ( securityCode.value.length === 3 ) {
                securityCodeValidation = true;
            }

            // Holder name validation
            holderName.addEventListener('input', function () {
                if ( ! /^[A-Za-z\s]*$/.test(this.value) ) {
                    this.value = this.value.slice(0, -1);
                }

                var words = this.value.trim().split(/\s+/);

                if ( words.length > 1 ) {
                    invalidHolderNameBox.css({'display': 'none'});
                    holderNameValidation = true;
                } else {
                    invalidHolderNameBox.css({'display': 'flex'});
                    holderNameValidation = false;
                }

                placeOrderButtonValidation(holderNameValidation, cardNumberValidation, expirationDateValidation, securityCodeValidation);
            });

            // Card number validation
            cardNumber.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').substring(0, 16).replace(/(\d{4})(?=\d)/g, '$1 ');

                var creditCardNumber = this.value.replace(/\D/g, '').substring(0, 16);
                if ( creditCardNumber.length === 16 ) {
                    if ( validateCardNumber( creditCardNumber ) ) {
                        invalidCardNumberBox.css({'display': 'none'});
                        cardNumberValidation = true;
                    } else {
                        invalidCardNumberBox.css({'display': 'flex'});
                        cardNumberValidation = false;
                    }
                } else {
                    cardNumberValidation = false;
                }

                placeOrderButtonValidation(holderNameValidation, cardNumberValidation, expirationDateValidation, securityCodeValidation);
            });
            
            // Expiration date validation
            expirationDate.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').substring(0, 4).replace(/(\d{2})(\d{0,2})/, '$1/$2');

                if ( this.value.match(/^(0[1-9]|1[0-2])\/[0-9]{2}$/) ) {
                    expirationDateValidation = true;
                    invalidExpirationDateBox.css({'display': 'none'});
                } else {
                    expirationDateValidation = false;
                    invalidExpirationDateBox.css({'display': 'flex'});
                }

                placeOrderButtonValidation(holderNameValidation, cardNumberValidation, expirationDateValidation, securityCodeValidation);
            });
            
            // CVC number validation
            securityCode.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').substring(0, 3);

                if ( this.value.length === 3 ) {
                    securityCodeValidation = true;
                    invalidCVCBox.css({'display': 'none'});
                } else {
                    securityCodeValidation = false;
                    invalidCVCBox.css({'display': 'flex'});
                }

                placeOrderButtonValidation(holderNameValidation, cardNumberValidation, expirationDateValidation, securityCodeValidation);
            });

            placeOrderButtonValidation(holderNameValidation, cardNumberValidation, expirationDateValidation, securityCodeValidation);
        }
    }

    function placeOrderButtonValidation(holderNameValidation, cardNumberValidation, expirationDateValidation, securityCodeValidation) {
        var placeOrderButton = $('#place_order');

        if ( holderNameValidation && cardNumberValidation && expirationDateValidation && securityCodeValidation ) {
            placeOrderButton.attr("disabled", false);
        } else {
            placeOrderButton.attr("disabled", true);
        }
    }

    $( document.body ).trigger( 'update_checkout' );

    $( document.body ).on( 'updated_checkout', function() {
        const current = $('form[name="checkout"] input[name="payment_method"]:checked').val();
        if (current == 'arkpay_payment') {
            creditCardDetailsFormat();
        }
    });
    
    $('form.checkout').on('change', 'input[name="payment_method"]', function () {
        $('#place_order').attr('disabled', false);
        const current = $('form[name="checkout"] input[name="payment_method"]:checked').val();
        if (current == 'arkpay_payment') {
            creditCardDetailsFormat();
        }
    });

});
