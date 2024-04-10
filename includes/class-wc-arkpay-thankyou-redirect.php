<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Callback function to handle the redirection after a successful transaction.
 *
 * This function is hooked into the 'init' action to check if specific parameters
 * are present in the query string ($_GET). If the conditions are met, it retrieves
 * relevant information from the database and redirects the user to the thank you page
 * of the corresponding WooCommerce order.
 */
function arkpay_thankyou_redirect_page() {
    if ( isset( $_GET['arkpayTransactionId'] ) && isset( $_GET['success'] ) && $_GET['success'] === 'true' ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'arkpay_draft_order';
        $transaction_id = esc_html( sanitize_text_field( $_GET['arkpayTransactionId'] ) );
        $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE transaction_id=%s", $transaction_id ) );
        if ( !empty( $results ) ) {
            $order_transaction_id   = $results[0]->transaction_id;
            $order_id               = $results[0]->order_id;
            $order_key              = $results[0]->order_key;
        }

        if ( isset( $order_transaction_id ) && $transaction_id === $order_transaction_id ) {
            // Redirect to the thank you page
            $thank_you_page_url = wc_get_checkout_url() . 'order-received/' . $order_id . '/?key=' . $order_key;
            wp_safe_redirect( $thank_you_page_url );
            exit;
        }
    }
}

add_action( 'init', 'arkpay_thankyou_redirect_page' );
