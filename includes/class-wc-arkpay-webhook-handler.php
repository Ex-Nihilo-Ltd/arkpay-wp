<?php

/**
 * ArkPay handle transaction status change webhook.
 */
function handle_arkpay_transaction_status_change_webhook() {
    global $wpdb;
    $payment_gateway = new WC_Gateway_Arkpay();
  
    $data           = file_get_contents('php://input');
    $headers        = getallheaders();
    $settings       = $payment_gateway->get_arkpay_settings();
    $secret_key     = $settings['secret_key'];
    $webhook_url    = $settings['webhook_url'];
    $http_method    = 'POST';

    $signature = $payment_gateway->create_signature( $http_method, $webhook_url, $data, $secret_key );

    if ( $signature === $headers['signature'] ) {
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
            }
        }

        $merchant_transaction_id = $body->merchantTransactionId;

        if ( $body->status === 'COMPLETED' || $body->status === 'FAILED' ) {
            if ( strpos( $merchant_transaction_id, '__' ) !== false ) {
                $parts = explode( '__', $merchant_transaction_id );
                $merchant_transaction_id = $parts[0];
            }
        }

        $order_id       = wc_get_order_id_by_order_key( $merchant_transaction_id );
        $order_exist    = wc_get_order( $order_id );

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
                    $order->calculate_totals();
                    $order->save();

                    update_transaction_status( $table_name, $transaction_id, $body->status, $order->get_id(), $order->get_order_key() );
                }
                break;
            case 'COMPLETED':
                if ( ! $order_exist && $draft_transaction_id === $transaction_id && $draft_transaction_status === 'PROCESSING' ) {
                    update_transaction_status( $table_name, $transaction_id, $body->status );
                    $order_completed = wc_get_order( $draft_order_id );
                    $order_completed->update_status( 'processing', __( 'Transaction has been completed.' ) );
                } else {
                    $order_exist->update_status( 'processing', __( 'Transaction has been completed.' ) );
                }
                break;
            case 'FAILED':
                if ( ! $order_exist && $draft_transaction_id === $transaction_id && $draft_transaction_status === 'PROCESSING' ) {
                    update_transaction_status( $table_name, $transaction_id, $body->status );
                    $order_failed = wc_get_order( $draft_order_id );
                    $order_failed->update_status( 'failed', __( 'Transaction has been failed.' ) );
                } else {
                    $order_exist->update_status( 'failed', __( 'Transaction has been failed.' ) );
                }
                break;
        }
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
function update_transaction_status( $table_name, $transaction_id, $transaction_status, $order_id = null, $order_key = null ) {
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
