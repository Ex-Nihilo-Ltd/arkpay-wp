<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Gateway_Arkpay_Blocks_Support extends AbstractPaymentMethodType {
	
    private $gateway;
	
    protected $name = 'arkpay_payment';

    public function initialize() {
        $this->settings = get_option( "woocommerce_{$this->name}_settings", array() );
    }

    public function get_payment_method_script_handles() {
        wp_register_script(
            'wc-arkpay-blocks-integration',
            plugin_dir_url( __DIR__ ) . 'build/checkout_blocks.js',
            array(
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
            ),
            null,
            true
        );

        return array( 'wc-arkpay-blocks-integration' );
    }

    public function get_payment_method_data() {
        return array(
            'title'         => $this->get_setting( 'title' ) ? $this->get_setting( 'title' ) : __( 'Credit card (ArkPay)', 'arkpay-payment' ),
            'description'   => $this->get_setting( 'description' ),
            'enable_direct' => $this->get_setting( 'enable_direct' ),
            'testmode'      => $this->get_setting( 'testmode' ),
            'button_text'   => $this->get_setting( 'button_text' ),
            'icon'          => plugins_url( 'assets/images/arkpay-logo.svg', __FILE__ ),
        );
    }
}
