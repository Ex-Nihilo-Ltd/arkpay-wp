<?php

/**
 * ArkPay handle transaction status change webhook.
 */
function handle_arkpay_transaction_status_change_webhook() {
  global $wpdb;
  $payment_gateway = new WC_Gateway_Arkpay();
  
	$data 				= file_get_contents('php://input');
	$headers 			= getallheaders();
	$settings			= $payment_gateway->get_arkpay_settings();
	$secret_key		= $settings['secret_key'];
	$webhook_url	= $settings['webhook_url'];
	$http_method	= 'POST';

  $signature = $payment_gateway->create_signature( $http_method, $webhook_url, $data, $secret_key );

  if ( $signature === $headers['signature'] ) {
    $body = json_decode( $data );

    $table_name = $wpdb->prefix . 'arkpay_draft_order';
    $transaction_id = $body->id;
    $results = $wpdb->get_results( "SELECT * FROM $table_name WHERE transaction_id='$transaction_id'");
    if ( !empty( $results ) ) {
      foreach ( $results as $row ) {
        $draft_transaction_id = $row->transaction_id;
        $draft_transaction_status = $row->transaction_status;
        $draft_cart_items = json_decode( $row->cart_items );
      }
    }

    switch ( $body->status ) {
      case 'PROCESSING':
        if ( $draft_transaction_id === $transaction_id && $draft_transaction_status === 'NOT_STARTED' ) {
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
        if ( $draft_transaction_id === $transaction_id && $draft_transaction_status === 'PROCESSING' ) {
          $order->update_status( 'processing', 'Transaction has been completed.' );
          update_transaction_status( $table_name, $transaction_id, $body->status );
        }
        break;
      case 'FAILED':
        update_transaction_status( $table_name, $transaction_id, $body->status );
        break;
    }
  }
}

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
