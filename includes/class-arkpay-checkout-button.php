<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_filter( 'woocommerce_available_payment_gateways', 'arkpay_payment_change_button_text' );

/**
 * Modify the order button text for ArkPay payments based on ArkPay settings.
 *
 * @param array $available_gateways List of available payment gateways.
 * @return array Modified list of available payment gateways.
 */
function arkpay_payment_change_button_text( $available_gateways ) {
	if ( ! is_checkout() ) return $available_gateways;

  $arkpay_gateway = new WC_Gateway_Arkpay();
  $settings = $arkpay_gateway->arkpay_get_settings();

	if ( array_key_exists( 'arkpay_payment', $available_gateways ) ) {
    $button_text = $settings['button_text'] ? $settings['button_text'] : 'Pay via ArkPay';
		$available_gateways['arkpay_payment']->order_button_text = sprintf( esc_html__( '%s', 'arkpay' ), esc_html( $button_text ) );
	}

	return $available_gateways;
}
