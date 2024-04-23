<?php

/**
 * Class WC_Gateway_Arkpay file.
 *
 * @package WooCommerce\Gateways
 */

use Automattic\Jetpack\Constants;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

/**
 * ArkPay Gateway.
 *
 * Provides a ArkPay Payment Gateway.
 *
 * @class       WC_Gateway_ArkPay
 * @extends     WC_Payment_Gateway
 * @version     2.1.0
 * @package     WooCommerce\Classes\Payment
 */
class WC_Gateway_Arkpay extends WC_Payment_Gateway {

    const ID = 'arkpay_payment';

    /**
     * Gateway instructions that will be added to the thank you page and emails.
     *
     * @var string
     */
    public $instructions;

    /**
     * Enable for shipping methods.
     *
     * @var array
     */
    public $enable_for_methods;

    /**
     * Enable for virtual products.
     *
     * @var bool
     */
    public $enable_for_virtual;

    /**
     * Constructor for the gateway.
     */
    public function __construct() {
        // Setup general properties.
        $this->setup_properties();

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Get settings.
        $this->title       	    = $this->get_option( 'title' );
        $this->description 	    = $this->get_option( 'description' );
        $this->testmode    	    = $this->get_option( 'testmode' );
        $this->api_key     	    = $this->get_option( 'api_key' );
        $this->secret_key  	    = $this->get_option( 'secret_key' );
        $this->button_text 	    = $this->get_option( 'button_text' );
        $this->enable_direct    = $this->get_option( 'enable_direct' );

        // Actions.
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
        add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'change_payment_complete_order_status' ), 10, 3 );

        // Customer Emails.
        add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

        // Styles
        wp_register_style( 'arkpay_styles', plugins_url( 'assets/css/arkpay-styles.css', __FILE__ ), array(), ARKPAY_VERSION );
        wp_enqueue_style( 'arkpay_styles' );

        // JS
        wp_enqueue_script( 'arkpay_js', plugins_url( 'assets/js/arkpay.js', __FILE__ ), array( 'jquery' ), ARKPAY_VERSION, true );

        // Create database table
        $this->arkpay_create_draft_order_table();
    }

    /**
     * Display payment fields for the ArkPay payment gateway.
     *
     * This function outputs the HTML form fields required for processing payments through the ArkPay gateway.
     * It includes fields for the holder name, card number, expiration date, and security code (CVC).
     *
     * @return void
     */
    public function payment_fields() {
        wp_nonce_field( 'arkpay_payment_nonce', 'arkpay_payment_nonce' );
        $settings = $this->arkpay_get_settings();

        if ( $settings['enable_direct'] === 'yes' ) {
            ?>
                <div class="arkpay-form-container">
                    <div class="field-container">
                        <label for="name">Holder Name</label>
                        <input id="name" name="name" maxlength="30" type="text" placeholder="Holder Name">
                    </div>
                    <div class="field-container">
                        <label for="cardnumber">Card Number</label>
                        <input id="cardnumber" name="cardnumber" type="text" placeholder="Card Number">
                        <span id="invalid-card-number-message">Invalid Card Number</span>
                    </div>
                    <div class="field-container">
                        <label for="expirationdate">Expiration (mm/yy)</label>
                        <input id="expirationdate" name="expirationdate" type="text" placeholder="Expiration Date">
                    </div>
                    <div class="field-container">
                        <label for="securitycode">Security Code</label>
                        <input id="securitycode" name="securitycode" type="text" placeholder="CVC">
                    </div>
                </div>
            <?php
        }
    }

    /**
     * Create ArkPay Draft Order and Cart Item Tables.
     *
     * This function is responsible for creating two database tables:
     * 1. 'arkpay_draft_order' for storing draft order information.
     * 2. 'arkpay_cart_items' for storing cart items associated with each draft order.
     *
     * @global wpdb $wpdb WordPress database access abstraction object.
     */
    private function arkpay_create_draft_order_table() {
        global $wpdb;

        $table_order = $wpdb->prefix . 'arkpay_draft_order';

        // Check if the draft order table already exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '%s'", $table_order ) != $table_order ) {
            $charset_collate = $wpdb->get_charset_collate();

            $sql_order = "CREATE TABLE $table_order (
            transaction_id VARCHAR(255) NOT NULL,
            transaction_status VARCHAR(50),
            cart_items LONGTEXT,
            order_id VARCHAR(255),
            order_key VARCHAR(255),
            shipping LONGTEXT,
            PRIMARY KEY (transaction_id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql_order);
        }
    }

    /**
     * Setup general properties for the gateway.
     */
    protected function setup_properties() {
        $this->id                 = self::ID;
        $this->icon               = apply_filters( 'arkpay_woocommerce_icon', plugins_url( 'assets/images/arkpay-logo.svg', __FILE__ ) );
        $this->method_title       = esc_html__( 'Arkpay', 'arkpay' );
        $this->method_description = esc_html__( 'The Smartest, Fastest & Most Secure Payment Processor.' , 'arkpay' );
        $this->has_fields         = true;
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields() {
        $this->form_fields = apply_filters( 'arkpay_payments_fields', array(
            'enabled'       => array(
                'title'             => esc_html__( 'Enable/Disable', 'arkpay' ),
                'label'             => esc_html__( 'Enable Arkpay Payment', 'arkpay' ),
                'type'              => 'checkbox',
                'description'       => '',
                'default'           => 'no'
            ),
            'title'         => array(
                'title'             => esc_html__( 'Title', 'arkpay' ),
                'description'       => esc_html__( 'This controls the title which the user sees during checkout.', 'arkpay' ),
                'default'           => esc_html__( 'Arkpay Payment', 'arkpay' ),
                'type'              => 'text',
                'desc_tip'          => true,
            ),
            'description'   => array(
                'title'             => esc_html__( 'Description', 'arkpay' ),
                'description'       => esc_html__( 'Arkpay description.', 'arkpay' ),
                'default'           => esc_html__( 'Pay with your credit card via arkpay payment gateway.', 'arkpay' ),
                'type'              => 'textarea',
                'desc_tip'          => true,
            ),
            'testmode'      => array(
                'title'             => esc_html__( 'Test mode', 'arkpay' ),
                'label'             => esc_html__( 'Enable Test Mode', 'arkpay' ),
                'description'       => esc_html__( 'Place the payment gateway in test mode using test API keys.', 'arkpay' ),
                'type'              => 'checkbox',
                'default'           => 'no',
                'desc_tip'          => true,
            ),
            'api_key'       => array(
                'title'             => esc_html__( 'API Key', 'arkpay' ),
                'type'              => 'text',
            ),
            'secret_key'    => array(
                'title'             => esc_html__( 'Secret Key', 'arkpay' ),
                'type'              => 'text',
            ),
            'button_text'   => array(
                'title'             => esc_html__( 'Checkout Page - Button', 'arkpay' ),
                'type'              => 'text',
            ),
            'webhook_url'   => array(
                'title'             => esc_html__( 'Webhook URL: ', 'arkpay' ),
                'type'              => 'text',
                'desc_tip'          => 'Copy this webhook URL to your ArkPay store settings.',
                'default'           => $this->arkpay_get_webhook_url(),
                'custom_attributes' => array( 'readonly' => 'readonly' ),
            ),
            'enable_direct' => array(
                'title'             => esc_html__( 'Payment method', 'arkpay' ),
                'label'             => esc_html__( 'Enable direct API', 'arkpay' ),
                'description'       => esc_html__( 'By checking this box, Arkpay gateway works through Direct API. If unchecked, HPP is being used (a default method).', 'arkpay' ),
                'type'              => 'checkbox',
                'default'           => 'no',
                'desc_tip'          => true,
            ),
        ) );
    }

    /**
     * Checks to see whether or not the admin settings are being accessed by the current request.
     *
     * @return bool
     */
    private function is_accessing_settings() {
        if ( is_admin() ) {
            // phpcs:disable WordPress.Security.NonceVerification
            if ( ! isset( $_REQUEST['page'] ) || 'wc-settings' !== esc_html( sanitize_text_field( $_REQUEST['page'] ) ) ) {
                return false;
            }
            if ( ! isset( $_REQUEST['tab'] ) || 'checkout' !== esc_html( sanitize_text_field( $_REQUEST['tab'] ) ) ) {
                return false;
            }
            if ( ! isset( $_REQUEST['section'] ) || 'arkpay_payment' !== esc_html( sanitize_text_field( $_REQUEST['section'] ) ) ) {
                return false;
            }
            // phpcs:enable WordPress.Security.NonceVerification

            return true;
        }

        if ( Constants::is_true( 'REST_REQUEST' ) ) {
            global $wp;
            if ( isset( $wp->query_vars['rest_route'] ) && false !== strpos( $wp->query_vars['rest_route'], '/payment_gateways' ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Loads all of the shipping method options for the enable_for_methods field.
     *
     * @return array
     */
    private function load_shipping_method_options() {
        // Since this is expensive, we only want to do it if we're actually on the settings page.
        if ( ! $this->is_accessing_settings() ) {
            return array();
        }

        $data_store = WC_Data_Store::load( 'shipping-zone' );
        $raw_zones  = $data_store->get_zones();

        foreach ( $raw_zones as $raw_zone ) {
            $zones[] = new WC_Shipping_Zone( $raw_zone );
        }

        $zones[] = new WC_Shipping_Zone( 0 );

        $options = array();
        foreach ( WC()->shipping()->load_shipping_methods() as $method ) {

            $options[ $method->get_method_title() ] = array();

            // Translators: %1$s shipping method name.
            $options[ $method->get_method_title() ][ $method->id ] = sprintf( esc_html__( 'Any &quot;%1$s&quot; method', 'arkpay' ), $method->get_method_title() );

            foreach ( $zones as $zone ) {

                $shipping_method_instances = $zone->get_shipping_methods();

                foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {

                    if ( $shipping_method_instance->id !== $method->id ) {
                        continue;
                    }

                    $option_id = $shipping_method_instance->get_rate_id();

                    // Translators: %1$s shipping method title, %2$s shipping method id.
                    $option_instance_title = sprintf( esc_html__( '%1$s (#%2$s)', 'arkpay' ), $shipping_method_instance->get_title(), $shipping_method_instance_id );

                    // Translators: %1$s zone name, %2$s shipping method instance name.
                    $option_title = sprintf( esc_html__( '%1$s &ndash; %2$s', 'arkpay' ), $zone->get_id() ? $zone->get_zone_name() : esc_html__( 'Other locations', 'arkpay' ), $option_instance_title );

                    $options[ $method->get_method_title() ][ $option_id ] = $option_title;
                }
            }
        }

        return $options;
    }

    /**
     * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
     *
     * @since  3.4.0
     *
     * @param  array $order_shipping_items  Array of WC_Order_Item_Shipping objects.
     * @return array $canonical_rate_ids    Rate IDs in a canonical format.
     */
    private function get_canonical_order_shipping_item_rate_ids( $order_shipping_items ) {

        $canonical_rate_ids = array();

        foreach ( $order_shipping_items as $order_shipping_item ) {
            $canonical_rate_ids[] = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
        }

        return $canonical_rate_ids;
    }

    /**
     * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
     *
     * @since  3.4.0
     *
     * @param  array $chosen_package_rate_ids Rate IDs as generated by shipping methods. Can be anything if a shipping method doesn't honor WC conventions.
     * @return array $canonical_rate_ids  Rate IDs in a canonical format.
     */
    private function get_canonical_package_rate_ids( $chosen_package_rate_ids ) {

        $shipping_packages  = WC()->shipping()->get_packages();
        $canonical_rate_ids = array();

        if ( ! empty( $chosen_package_rate_ids ) && is_array( $chosen_package_rate_ids ) ) {
            foreach ( $chosen_package_rate_ids as $package_key => $chosen_package_rate_id ) {
                if ( ! empty( $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ] ) ) {
                    $chosen_rate          = $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ];
                    $canonical_rate_ids[] = $chosen_rate->get_method_id() . ':' . $chosen_rate->get_instance_id();
                }
            }
        }

        return $canonical_rate_ids;
    }

    /**
     * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
     *
     * @since  3.4.0
     *
     * @param array $rate_ids Rate ids to check.
     * @return boolean
     */
    private function get_matching_rates( $rate_ids ) {
        // First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
        return array_unique( array_merge( array_intersect( $this->enable_for_methods, $rate_ids ), array_intersect( $this->enable_for_methods, array_unique( array_map( 'wc_get_string_before_colon', $rate_ids ) ) ) ) );
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function process_payment( $order_id ) {
        if ( ! isset( $_POST['arkpay_payment_nonce'] ) || ! wp_verify_nonce( esc_html( sanitize_text_field( wp_unslash( $_POST['arkpay_payment_nonce'] ) ) ), 'arkpay_payment_nonce' ) ) {
            wc_add_notice( esc_html__( 'ArkPay Security check failed.', 'arkpay' ), 'error' );
            return;
        }

        $order = wc_get_order( $order_id );
        $order_data = $order->get_data();

        // Retrieve the credit card details
        $credit_card = array(
            'holder_name'        => esc_html( sanitize_text_field( $_POST['name'] ) ),
            'card_number'        => esc_html( sanitize_text_field( $_POST['cardnumber'] ) ),
            'expiration_date'    => esc_html( sanitize_text_field( $_POST['expirationdate'] ) ),
            'cvc'                => esc_html( sanitize_text_field( $_POST['securitycode'] ) ),
        );

        if ( $order->get_total() > 0 ) {

            $data = array(
                'id'            => strval( $order_data['order_key'] ),
                'ammount'       => floatval( $order_data['total'] ),
                'currency'      => $order_data['currency'],
                'description'   => 'Description.',
                'handlePayment' => true,
            );

            $order_transaction_meta_data = get_post_meta( $order_id, '_transaction_data', true );
            if ( isset( $order_transaction_meta_data ) && ! empty( $order_transaction_meta_data ) ) {
                $last_transaction_key = count( $order_transaction_meta_data ) - 1;

                if ( ! in_array( $order_transaction_meta_data[$last_transaction_key]['_transaction_status'], [ 'FAILED', 'CANCELLED' ] ) ) {

                    if ( $order_transaction_meta_data[$last_transaction_key]['_transaction_status'] === 'NOT_STARTED' ) {
                        $existing_not_started_transaction_id        = $order_transaction_meta_data[$last_transaction_key]['_transaction_id'];
                        $existing_not_started_transaction_status    = $order_transaction_meta_data[$last_transaction_key]['_transaction_status'];
                    } else {
                        switch( $order_transaction_meta_data[$last_transaction_key]['_transaction_status'] ) {
                            case 'PROCESSING':
                                wc_add_notice( esc_html__( 'ArkPay: Last transaction still in process. Try again in a few minutes.', 'arkpay' ), 'error' );
                                return;
                                break;
                            case 'COMPLETED':
                                wc_add_notice( esc_html__( 'ArkPay: Transaction already completed.', 'arkpay' ), 'error' );
                                return;
                                break;
                        }

                        wc_add_notice( esc_html__( 'ArkPay: Something went wrong.', 'arkpay' ), 'error' );
                        return;
                    }
                }
            }

            if ( ! isset( $existing_not_started_transaction_id ) && ! isset( $existing_not_started_transaction_status ) && ! $existing_not_started_transaction_id && ! $existing_not_started_transaction_status ) {
                $transaction = $this->arkpay_create_transaction( $data );

                if ( $transaction && isset( $transaction->transaction->id ) ) {
                    $transaction_id             = $transaction->transaction->id;
                    $merchant_transaction_id    = $transaction->transaction->merchantTransactionId;
                    $transaction_status         = $transaction->transaction->status;

                    $meta_data = array(
                        '_transaction_id'           => sanitize_text_field( $transaction_id ),
                        '_merchant_transaction_id'  => sanitize_text_field( $merchant_transaction_id ),
                        '_transaction_status'       => sanitize_text_field( $transaction_status ),
                    );

                    $existing_meta_data = get_post_meta( $order_id, '_transaction_data', true );

                    if ( ! $existing_meta_data ) {
                        $existing_meta_data = array();
                    }

                    $existing_meta_data[] = $meta_data;

                    update_post_meta( $order_id, '_transaction_data', $existing_meta_data );
                }

                if ( isset( $transaction->statusCode ) && 400 === $transaction->statusCode ) {
                    $unique_id = uniqid();

                    if ( preg_match( '/wc_order_[A-Za-z0-9]+/', $transaction->message, $matches ) ) {
                        $data['id'] = $matches[0] . '__' . $unique_id;
                    }

                    $transaction                = $this->arkpay_create_transaction( $data );
                    $transaction_id             = $transaction->transaction->id;
                    $merchant_transaction_id    = $transaction->transaction->merchantTransactionId;
                    $transaction_status         = $transaction->transaction->status;

                    $meta_data = array(
                        '_transaction_id'           => sanitize_text_field( $transaction_id ),
                        '_merchant_transaction_id'  => sanitize_text_field( $merchant_transaction_id ),
                        '_transaction_status'       => sanitize_text_field( $transaction_status ),
                    );

                    $existing_meta_data = get_post_meta( $order_id, '_transaction_data', true );

                    if ( ! $existing_meta_data ) {
                        $existing_meta_data = array();
                    }

                    $existing_meta_data[] = $meta_data;

                    update_post_meta( $order_id, '_transaction_data', $existing_meta_data );
                }

                if ( isset( $transaction->statusCode ) && 200 !== $transaction->statusCode ) {
                    wc_add_notice( sprintf( esc_html__( 'ArkPay: %s.', 'arkpay' ), esc_html( $transaction->message ) ), 'error' );
                    return;
                }
            }

            if ( isset( $existing_not_started_transaction_id ) && isset( $existing_not_started_transaction_status ) && $existing_not_started_transaction_id && $existing_not_started_transaction_status ) {
                $transaction_id     = $existing_not_started_transaction_id;
                $transaction_status = $existing_not_started_transaction_status;
            }

            if ( $transaction_id && $transaction_status === 'NOT_STARTED' ) {
                $order_return_url         = $this->get_return_url( $order );
                $pay_transaction_response = $this->arkpay_pay_transaction( $order_data, $credit_card, $transaction_id, $order_return_url );

                if ( isset( $pay_transaction_response->status ) && $pay_transaction_response->status === 'FAILED' ) {
                    wc_add_notice( sprintf( esc_html__( 'ArkPay: %s.', 'arkpay' ), esc_html( $pay_transaction_response->message ) ), 'error' );
                    return;
                }

                if ( isset( $pay_transaction_response->statusCode ) && 200 !== $pay_transaction_response->statusCode ) {
                    wc_add_notice( sprintf( esc_html__( 'ArkPay: %s.', 'arkpay' ), esc_html( $pay_transaction_response->message ) ), 'error' );
                    return;
                }

                if ( $pay_transaction_response->status === 'PROCESSING' && $pay_transaction_response->redirectUrl ) {
                    $order->update_status( apply_filters( 'arkpay_woocommerce_process_payment_order_status', $order->has_downloadable_item() ? 'on-hold' : 'pending', $order ), esc_html__( 'Processing transaction...', 'arkpay' ) );

                    WC()->cart->empty_cart();

                    // Return redirect ACS Bank page
                    return array(
                        'result'   => 'success',
                        'redirect' => $pay_transaction_response->redirectUrl,
                    );
                }
            }
        }
    }

    /**
     * Output for the order received page.
     */
    public function thankyou_page() {
        if ( $this->instructions ) {
            echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) );
        }
    }

    /**
     * Change payment complete order status to completed for ArkPay orders.
     *
     * @since  3.1.0
     * @param  string         $status Current order status.
     * @param  int            $order_id Order ID.
     * @param  WC_Order|false $order Order object.
     * @return string
     */
    public function change_payment_complete_order_status( $status, $order_id = 0, $order = false ) {
        if ( $order && 'arkpay_payment' === $order->get_payment_method() ) {
            $status = 'completed';
        }
        return $status;
    }

    /**
     * Add content to the WC emails.
     *
     * @param WC_Order $order Order object.
     * @param bool     $sent_to_admin  Sent to admin.
     * @param bool     $plain_text Email format: plain text or HTML.
     */
    public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
        if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() ) {
            echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
        }
    }

    /**
     * Retrieve ArkPay settings.
     *
     * @return array An array containing the settings related to ArkPay.
     */
    public function arkpay_get_settings() {
        return array(
            'title'         => $this->title,
            'description'   => $this->description,
            'testmode'      => $this->testmode,
            'payment_id'    => $this->id,
            'api_key'       => $this->api_key,
            'secret_key'    => $this->secret_key,
            'button_text'   => $this->button_text,
            'enable_direct' => $this->enable_direct,
            'webhook_url'   => $this->arkpay_get_webhook_url(),
        );
    }

    /**
     * Create a signature for API authentication using HMAC-SHA256.
     *
     * @param string $http_method The HTTP method (e.g., GET, POST).
     * @param string $api_uri The API endpoint URI.
     * @param string $body The request body (json).
     * @param string $secret_key The secret key used for HMAC-SHA256.
     *
     * @return string The generated HMAC-SHA256 signature.
     */
    public function arkpay_create_signature( $http_method, $api_uri, $body, $secret_key ) {
        $payload = $http_method . ' ' . $api_uri . "\n" . $body;
        return hash_hmac( 'sha256', $payload, $secret_key );
    }

    /**
     * Get webhook URL.
     */
    public function arkpay_get_webhook_url() {
        return get_rest_url() . 'api/arkpay/webhook';
    }

    /**
     * Get API URL.
     */
    public function arkpay_get_api_url() {
        $settings = $this->arkpay_get_settings();

        if ( $settings['testmode'] === 'yes' ) {
            return 'https://api-arkpay.exnihilo.dev/api/v1';
        }

        return 'https://arkpay.com/api/v1';
    }

    /**
     * Create an ArkPay transaction.
     *
     * @param array $order An associative array containing transaction details.
     *
     * @return mixed The response from the ArkPay API.
     */
    public function arkpay_create_transaction( $order ) {
        $settings = $this->arkpay_get_settings();

        $http_method    = 'POST';
        $api_key        = $settings['api_key'];
        $secret_key     = $settings['secret_key'];
        $api_url        = $this->arkpay_get_api_url();
        $api_uri        = '/api/v1/merchant/api/transactions';
        $endpoint       = '/merchant/api/transactions';

        $body = array(
            'merchantTransactionId' => $order['id'],
            'amount'                => $order['ammount'],
            'currency'              => $order['currency'],
            'description'           => $order['description'],
            'handlePayment'         => $order['handlePayment'],
        );

        $signature = $this->arkpay_create_signature( $http_method, $api_uri, wp_json_encode( $body ), $secret_key );
        $headers = array(
            'Content-Type'  => 'application/json',
            'X-Api-Key'     => $api_key,
            'Signature'     => $signature,
        );

        $response = wp_remote_post( $api_url . $endpoint, array(
            'body'      => wp_json_encode( $body ),
            'headers'   => $headers,
            'timeout'   => 45,
        ) );

        if ( is_wp_error( $response ) ) {
            wc_add_notice( sprintf( esc_html__( 'ArkPay: %s.', 'arkpay' ), esc_html( $response->get_error_message() ) ), 'error' );
            return false;
        }

        return json_decode( wp_remote_retrieve_body( $response ) );
    }

    /**
     * Save draft order data to a arkpay_draft_order table in the database.
     *
     * @param array $order_data {
     *     An array containing order data.
     *
     *     @type string $transaction_id     The transaction ID.
     *     @type string $transaction_status The transaction status.
     *     @type string $cart_items         The serialized cart items.
     *     @type int    $order_id           The order ID.
     *     @type string $order_key          The order key.
     * }
     *
     * @global wpdb $wpdb WordPress database class.
     */
    public function arkpay_create_draft_order( $order_data ) {
        global $wpdb;

        // Create draft order in the order table
        $table_order        = $wpdb->prefix . 'arkpay_draft_order';
        $transaction_id     = $order_data['transaction_id'];
        $transaction_status = $order_data['transaction_status'];
        $cart_items         = $order_data['cart_items'];
        $order_id           = $order_data['order_id'];
        $order_key          = $order_data['order_key'];
        $shipping           = $order_data['shipping'];

        $wpdb->insert(
            $table_order,
            array(
                'transaction_id'        => $transaction_id,
                'transaction_status'    => $transaction_status,
                'cart_items'            => $cart_items,
                'order_id'              => $order_id,
                'order_key'             => $order_key,
                'shipping'              => $shipping,
            ),
            array( '%s', '%s', '%s' )
        );
    }

    public function arkpay_pay_transaction( $order, $credit_card, $transaction_id, $order_return_url ) {
        $settings = $this->arkpay_get_settings();

        $http_method    = 'POST';
        $api_key        = $settings['api_key'];
        $secret_key     = $settings['secret_key'];
        $api_url        = $this->arkpay_get_api_url();
        $api_uri        = '/api/v1/merchant/api/transactions/' . $transaction_id . '/pay';
        $endpoint       = '/merchant/api/transactions/' . $transaction_id . '/pay';

        $body = array(
            'cardNumber'        => str_replace( ' ', '', strval( $credit_card['card_number'] ) ),
            'cardExpiryDate'    => strval( $credit_card['expiration_date'] ),
            'cvc'               => strval( $credit_card['cvc'] ),
            'holderName'        => $credit_card['holder_name'],
            'email'             => $order['billing']['email'],
            'phoneNumber'       => strval( $order['billing']['phone'] ),
            'ipAddress'         => strval( $order['customer_ip_address'] ),
            'acsReturnUrl'      => $order_return_url,
            'customerAddress'   => array(
                'address'       => $order['billing']['address_1'],
                'city'          => $order['billing']['city'],
                'countryCode'   => $order['billing']['country'],
                'zipCode'       => strval( $order['billing']['postcode'] ),
            ),
    	);

        if( 'US' === $order['billing']['country'] || 'CA' === $order['billing']['country'] ) {
            $body['customerAddress']['state'] = $order['billing']['state'];
        }

        $signature = $this->arkpay_create_signature( $http_method, $api_uri, wp_json_encode( $body ), $secret_key );
        $headers = array(
            'Content-Type'  => 'application/json',
            'X-Api-Key'     => $api_key,
            'Signature'     => $signature,
        );

        $response = wp_remote_post( $api_url . $endpoint, array(
            'body'      => wp_json_encode( $body ),
            'headers'   => $headers,
            'timeout'   => 45,
        ) );

        if ( is_wp_error( $response ) ) {
            wc_add_notice( sprintf( esc_html__( 'ArkPay: %s.', 'arkpay' ), esc_html( $response->get_error_message() ) ), 'error' );
            return false;
        }

        return json_decode( wp_remote_retrieve_body( $response ) );
    }
}
