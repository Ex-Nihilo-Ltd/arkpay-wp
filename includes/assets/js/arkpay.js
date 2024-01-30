jQuery( function( $ ) {
    // Credit card format
    function creditCardDetailsFormat() {
        var cardNumber = document.getElementById('cardnumber');
        var expirationDate = document.getElementById('expirationdate');
        var securityCode = document.getElementById('securitycode');

        if ( cardNumber && expirationDate && securityCode ) {
            cardNumber.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').substring(0, 16).replace(/(\d{4})(?=\d)/g, '$1 ');
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

    $( document.body ).trigger( 'update_checkout' );
    $( document.body ).on( 'updated_checkout', function() {
        creditCardDetailsFormat();
    });
});
