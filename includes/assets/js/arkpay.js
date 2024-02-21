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

    // Credit card format
    function creditCardDetailsFormat() {
        var cardNumber = document.getElementById('cardnumber');
        var expirationDate = document.getElementById('expirationdate');
        var securityCode = document.getElementById('securitycode');
        
        if ( cardNumber && expirationDate && securityCode ) {
            var placeOrderButton = $('#place_order');
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
