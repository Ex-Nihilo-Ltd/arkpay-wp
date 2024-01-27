<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'woocommerce_proceed_to_checkout', 'add_arkpay_cart_pay_button' );
/**
 * Adds a custom button to the cart page for ArkPay payment.
 *
 * @since 1.0.0
 *
 * @throws Exception Throws an exception if there are issues retrieving payment gateway details or processing the AJAX request.
 */
function add_arkpay_cart_pay_button() {
  $arkpay_gateway = new WC_Gateway_Arkpay();
  $settings = $arkpay_gateway->get_arkpay_settings();
  $button_text = $settings['button_text'] ? $settings['button_text'] : 'Pay via Arkpay';

  ?>
    <a href="#" class="checkout-button button alt wc-forward wp-element-button" id="arkpay-pay-button"><?= esc_html__( $button_text , 'arkpay' ); ?></a>
    <script>
      jQuery(function ($) {
        $('#arkpay-pay-button').on('click', function (e) {
          e.preventDefault();

          var cartData = {
            action: 'arkpay_save_draft_order',
            security: '<?php echo wp_create_nonce( 'arkpay-save-draft-order' ); ?>',
          };

          $.ajax({
            type: 'POST',
            url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
            data: cartData,
            success: function (response) {
              console.log('Response data: ', response.data);
              $(e.target).addClass('disabled');
              window.open(
                response.data,
                '_blank',
              );
            },
            error: function (error) {
              console.log('Error: ', error);
            }
          });
        });
      });
    </script>
  <?php
}

// AJAX handler to save draft order
add_action( 'wp_ajax_arkpay_save_draft_order', 'arkpay_save_draft_order' );
add_action( 'wp_ajax_nopriv_arkpay_save_draft_order', 'arkpay_save_draft_order' );

/**
 * AJAX handler to save the draft order.
 *
 * This function handles AJAX requests to save the draft order.
 *
 * @throws Exception Throws an exception if there are issues saving the draft order.
 */
function arkpay_save_draft_order() {
  // Check nonce
  check_ajax_referer( 'arkpay-save-draft-order', 'security' );

  try {
    // ArkPay gateway
    $arkpay_gateway = new WC_Gateway_Arkpay();

    $cart_total = WC()->cart->subtotal;
    $currency = get_woocommerce_currency();

    // Create draft order for transaction
    $order_data = array(
      'id'          => uniqid(),
			'ammount'     => $cart_total,
			'currency'    => $currency,
			'description' => 'Description',
    );

    $transaction = $arkpay_gateway->create_arkpay_transaction( $order_data );

    if ( $transaction ) {
      // Add products to the draft order
      $cart_items = WC()->cart->get_cart();
      $items = array();
      foreach ( $cart_items as $cart_item_key => $cart_item ) {
        $items[$cart_item_key]['product_id'] = $cart_item['product_id'];
        $items[$cart_item_key]['variation_id'] = $cart_item['variation_id'];
        $items[$cart_item_key]['quantity'] = $cart_item['quantity'];
      }
  
      $draft_order_data = array(
        'transaction_id' => $transaction->transaction->id,
        'transaction_status' => $transaction->transaction->status,
        'cart_items' => json_encode( $items ),
        'order_key' => null,
      );
  
      $arkpay_gateway->save_draft_order( $draft_order_data );
  
      $redirect_url = $transaction->redirectUrl;
  
      wp_send_json_success( $redirect_url );
    }
  } catch ( Exception $error ) {
    wp_send_json_error( array( 'error_message' => $error->getMessage() ) );
  }
}