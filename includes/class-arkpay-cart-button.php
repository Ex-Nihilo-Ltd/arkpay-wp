<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

add_action( 'woocommerce_widget_shopping_cart_buttons', 'add_arkpay_cart_pay_button' );
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
        <a href="#" class="checkout-button button alt wc-forward wp-element-button" id="arkpay-pay-button"><?php echo esc_html__( (string) $button_text , 'arkpay-payment' ); ?></a>
        <script>
            jQuery(function ($) {
                $('#arkpay-pay-button').on('click', function (e) {
                    e.preventDefault();

                    var cartData = {
                        action: 'arkpay_save_draft_order',
                        security: '<?php echo esc_attr( wp_create_nonce( 'arkpay-save-draft-order' ) ); ?>',
                    };

                    $.ajax({
                        type: 'POST',
                        url: '<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>',
                        data: cartData,
                        success: function (response) {
                            if ( 200 !== response.data['code'] ) {
                                location.reload();
                                return;
                            }

                            $(e.target).addClass('disabled');
                            window.open(
                                response.data['redirect_url'],
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

        $cart_total = floatval( WC()->cart->total );
        $currency = get_woocommerce_currency();

        // Create draft order for transaction
        $order_data = array(
            'id'            => uniqid(),
            'ammount'       => $cart_total,
            'currency'      => $currency,
            'description'   => 'Description.',
            'handlePayment' => false,
        );

        $cart_contents = WC()->cart->get_cart();

        if ( empty( $cart_contents ) ) {
            wc_add_notice( __( 'ArkPay: Your cart is empty.', 'arkpay-payment' ), 'error' );
            wp_send_json_error( array(
                'code' => 400,
                'message' => 'Cart is empty.'
            ) );
            wp_die();
        }

        $session                    = WC()->session->get_session_cookie();
        $session_id                 = implode( '', $session );
        $cart_contents_serialized   = serialize( $cart_contents );
        $cart_hash                  = WC()->cart->get_cart_hash();
        $cart_identifier            = md5( $session_id . $cart_hash . $cart_contents_serialized );

        $transaction_url = $arkpay_gateway->get_draft_order_by_cart_identifier( $cart_identifier );
        if ( ! empty( $transaction_url ) ) {
            // Clear cart
            WC()->cart->empty_cart();

            wp_send_json_success( array(
                'code' => 200,
                'redirect_url' => $transaction_url
            ) );
            wp_die();
        }

        $transaction = $arkpay_gateway->create_arkpay_transaction( $order_data );

        if ( $transaction ) {
            // Add products to the draft order
            $cart_items = WC()->cart->get_cart();
            $items = array();
            foreach ( $cart_items as $cart_item_key => $cart_item ) {
                $items[$cart_item_key]['product_id']    = $cart_item['product_id'];
                $items[$cart_item_key]['variation_id']  = $cart_item['variation_id'];
                $items[$cart_item_key]['quantity']      = $cart_item['quantity'];
            }

            $session_instance = WC()->session;
            $shipping_method_id = $session_instance->get('chosen_shipping_methods')[0];

            $chosen_shipping_methods = $session_instance->get('shipping_for_package_0')['rates'];
            $shipping_method_cost = 0;
            foreach ( $chosen_shipping_methods as $method_id => $rate ) {
                if ( $method_id === $shipping_method_id ) {
                    $shipping_method_title  = $rate->label;
                    $shipping_method_cost   = $rate->cost;
                    $shipping_items         = $rate->meta_data['Items'];
                    break;
                }
            }

            $shipping = array();
            $shipping['shipping_method_id']     = $shipping_method_id;
            $shipping['shipping_method_title']  = $shipping_method_title;
            $shipping['shipping_method_cost']   = $shipping_method_cost;
            $shipping['shipping_items']         = $shipping_items;

            $applied_coupons = WC()->cart->get_coupon_discount_totals();

            $draft_order_data = array(
                'transaction_id'        => $transaction->transaction->id,
                'transaction_status'    => $transaction->transaction->status,
                'transaction_url'       => $transaction->redirectUrl,
                'cart_items'            => wp_json_encode( $items ),
                'cart_identifier'       => $cart_identifier,
                'order_id'              => null,
                'order_key'             => null,
                'shipping'              => wp_json_encode( $shipping ),
                'applied_coupons'       => ! empty( $applied_coupons ) ? wp_json_encode( $applied_coupons ) : null,
            );

            $arkpay_gateway->save_draft_order( $draft_order_data );

            $redirect_url = $transaction->redirectUrl;

            // Clear cart
            WC()->cart->empty_cart();

            wp_send_json_success( array(
                'code' => 200,
                'redirect_url' => $redirect_url
            ) );
        }
    } catch ( Exception $error ) {
        wp_send_json_error( array( 'error_message' => $error->getMessage() ) );
    }
}
