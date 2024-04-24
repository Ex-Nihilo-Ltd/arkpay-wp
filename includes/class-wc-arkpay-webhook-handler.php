<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * ArkPay handle transaction status change webhook.
 */
function arkpay_handle_transaction_status_change_webhook() {
    global $wpdb;
    $payment_gateway = new WC_Gateway_Arkpay();

    $data               = file_get_contents('php://input');
    $request_signature  = esc_sql( esc_html( sanitize_text_field( $_SERVER['HTTP_SIGNATURE'] ) ) );
    $settings           = $payment_gateway->arkpay_get_settings();
    $secret_key         = $settings['secret_key'];
    $webhook_url        = $settings['webhook_url'];
    $http_method        = 'POST';

    $signature = $payment_gateway->arkpay_create_signature( $http_method, $webhook_url, $data, $secret_key );

    if ( isset( $request_signature ) && $signature === $request_signature ) {
        $body = json_decode( $data );

        $table_name = $wpdb->prefix . 'arkpay_draft_order';
        $transaction_id = $body->id;
        $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE transaction_id=%s", $transaction_id ) );
        if ( !empty( $results ) ) {
            foreach ( $results as $row ) {
                $draft_transaction_id       = $row->transaction_id;
                $draft_transaction_status   = $row->transaction_status;
                $draft_cart_items           = json_decode( $row->cart_items );
                $draft_order_id             = $row->order_id ? $row->order_id : '';
                $draft_shipping             = json_decode( $row->shipping );
            }
        }

        $merchant_transaction_id = $body->merchantTransactionId;

        if ( $body->status === 'COMPLETED' || $body->status === 'FAILED' || $body->status === 'PROCESSING' ) {
            if ( strpos( $merchant_transaction_id, '__' ) !== false ) {
                $parts                      = explode( '__', $merchant_transaction_id );
                $merchant_transaction_id    = $parts[0];
            }
        }

        $order_id                       = wc_get_order_id_by_order_key( $merchant_transaction_id );
        $order_transaction_meta_data    = get_post_meta( $order_id, '_transaction_data', true );
        $order_exist                    = wc_get_order( $order_id );

        switch ( $body->status ) {
            case 'PROCESSING':
                if ( ! $order_exist && $draft_transaction_id === $transaction_id && $draft_transaction_status === 'NOT_STARTED' ) {
                    $order = wc_create_order( array( 'status' => 'pending' ) );

                    foreach ( $draft_cart_items as $cart_item_key => $cart_item ) {
                        $product_id   = $cart_item->product_id;
                        $variation_id = $cart_item->variation_id;
                        $quantity     = $cart_item->quantity;
                        $order->add_product( wc_get_product( $product_id ), $quantity, array( 'variation_id' => $variation_id ) );
                    }

                    $address = array(
                        'first_name' => '',
                        'last_name'  => '',
                        'email'      => $body->email,
                        'phone'      => '',
                        'address_1'  => '',
                        'city'       => '',
                        'state'      => '',
                        'postcode'   => '',
                        'country'    => ''
                    );

                    $order->set_address( $address, 'billing' );

                    $shipping = new WC_Order_Item_Shipping();
                    $shipping->set_method_id( $draft_shipping->shipping_method_id );
                    $shipping->set_method_title( $draft_shipping->shipping_method_title );
                    $shipping->set_total( $draft_shipping->shipping_method_cost );
                    $shipping->save();
                    $order->add_item( $shipping );

                    $order->set_payment_method( 'Credit card (ArkPay)' );
                    $order->calculate_totals();
                    $order->save();

                    arkpay_update_transaction_status( $table_name, $transaction_id, $body->status, $order->get_id(), $order->get_order_key() );
                } else {
                    arkpay_update_order_transaction_status_meta_data( $order_id, $order_transaction_meta_data, 'PROCESSING' );
                }
                break;
            case 'COMPLETED':
                if ( ! $order_exist && $draft_transaction_id === $transaction_id && $draft_transaction_status === 'PROCESSING' ) {
                    arkpay_update_transaction_status( $table_name, $transaction_id, $body->status );
                    $order_completed = wc_get_order( $draft_order_id );
                    $order_completed->update_status( 'processing', esc_html__( 'Transaction has been completed.', 'arkpay' ) );
                } else {
                    arkpay_update_order_transaction_status_meta_data( $order_id, $order_transaction_meta_data, 'COMPLETED' );
                    $order_exist->update_status( 'processing', esc_html__( 'Transaction has been completed.', 'arkpay' ) );
                }
                break;
            case 'FAILED':
                if ( $draft_transaction_id === $transaction_id && in_array( $draft_transaction_status, [ 'PROCESSING', 'NOT_STARTED', 'FAILED' ] ) ) {
                    arkpay_update_transaction_status( $table_name, $transaction_id, $body->status );
                    if ( $draft_transaction_status === 'PROCESSING' ) {
                        $order_failed = wc_get_order( $draft_order_id );
                        $order_failed->update_status( 'failed', esc_html__( 'Transaction has been failed.', 'arkpay' ) );
                    }
                } else {
                    arkpay_update_order_transaction_status_meta_data( $order_id, $order_transaction_meta_data, 'FAILED' );
                    $order_exist->update_status( 'failed', esc_html__( 'Transaction has been failed.', 'arkpay' ) );
                }
                break;
            case 'CANCELLED':
                if ( ! $order_exist && $draft_transaction_id === $transaction_id && $draft_transaction_status === 'NOT_STARTED' ) {
                    arkpay_update_transaction_status( $table_name, $transaction_id, $body->status );
                } else {
                    arkpay_update_order_transaction_status_meta_data( $order_id, $order_transaction_meta_data, 'CANCELLED' );
                    $order_exist->update_status( 'CANCELLED', esc_html__( 'Transaction has been cancelled.', 'arkpay' ) );
                }
                break;
        }
    } else {
        http_response_code( 401 );
        $response = array(
            'code'              => 401,
            'message'           => 'Signature mismatch.',
        );
        echo wp_json_encode( $response );
        exit();
    }
}

/**
 * Update order transaction status meta data.
 *
 * This function updates the transaction status meta data for an order.
 *
 * @param int    $order_id                    The ID of the order.
 * @param array  $order_transaction_meta_data The array containing transaction meta data for the order.
 * @param string $transaction_status          The status to be updated for the transaction.
 * 
 * @return void
 */
function arkpay_update_order_transaction_status_meta_data( $order_id, $order_transaction_meta_data, $transaction_status ) {
    if ( $order_transaction_meta_data ) {
        $last_transaction_key = count( $order_transaction_meta_data ) - 1;

        $order_transaction_meta_data[$last_transaction_key]['_transaction_status'] = $transaction_status;

        update_post_meta( $order_id, '_transaction_data', $order_transaction_meta_data );
    }
}

/**
 * Update the transaction status in the specified table.
 *
 * This function updates the transaction status in the specified table based on the given transaction ID.
 *
 * @param string $table_name       The name of the database table to update.
 * @param int    $transaction_id   The unique identifier for the transaction.
 * @param string $transaction_status The new status to set for the transaction.
 * @param int|null $order_id       (Optional) The order ID associated with the transaction.
 * @param string|null $order_key   (Optional) The order key associated with the transaction.
 *
 * @global wpdb $wpdb              WordPress database access abstraction object.
 */
function arkpay_update_transaction_status( $table_name, $transaction_id, $transaction_status, $order_id = null, $order_key = null ) {
    global $wpdb;

    $data = array(
        'transaction_status' => $transaction_status,
    );

    if ( $order_id && $order_key ) {
        $data['order_id']  = $order_id;
        $data['order_key'] = $order_key;
    }

    $where = array(
        'transaction_id' => $transaction_id,
    );

    $data_format = array(
        '%s'
    );

    $where_format = array(
        '%s'
    );

    $wpdb->update( $table_name, $data, $where, $data_format, $where_format );
}
