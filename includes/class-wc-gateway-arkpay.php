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
    $this->title       	= $this->get_option( 'title' );
    $this->description 	= $this->get_option( 'description' );
    $this->testmode    	= $this->get_option( 'testmode' );
    $this->api_key     	= $this->get_option( 'api_key' );
    $this->secret_key  	= $this->get_option( 'secret_key' );
    $this->button_text 	= $this->get_option( 'button_text' );

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'change_payment_complete_order_status' ), 10, 3 );

		// Customer Emails.
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

		// Styles
		wp_register_style( 'arkpay_styles', plugins_url( 'assets/css/arkpay-styles.css', __FILE__ ) );
		wp_enqueue_style( 'arkpay_styles' );

		// JS
		wp_enqueue_script( 'arkpay_js', plugins_url( 'assets/js/arkpay.js', __FILE__ ), array( 'jquery' ) );

		// Create database table
		$this->create_arkpay_draft_order_table();
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
	private function create_arkpay_draft_order_table() {
		global $wpdb;

		$table_order = $wpdb->prefix . 'arkpay_draft_order';

		// Check if the draft order table already exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_order'" ) != $table_order ) {
			$charset_collate = $wpdb->get_charset_collate();

			$sql_order = "CREATE TABLE $table_order (
				transaction_id VARCHAR(255) NOT NULL,
				transaction_status VARCHAR(50),
				cart_items LONGTEXT,
				order_id VARCHAR(255),
				order_key VARCHAR(255),
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
    $this->icon               = apply_filters( 'woocommerce_arkpay_icon', plugins_url( 'assets/images/arkpay-logo.svg', __FILE__ ) );
    $this->method_title       = __( 'Arkpay', 'arkpay' );
    $this->method_description = __( 'The Smartest, Fastest & Most Secure Payment Processor.' , 'arkpay' );
    $this->has_fields         = false;
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
  public function init_form_fields() {
    $this->form_fields = apply_filters( 'arkpay_payments_fields', array(
      'enabled' => array(
        'title'       			=> __( 'Enable/Disable', 'arkpay' ),
        'label'       			=> __( 'Enable Arkpay Payment', 'arkpay' ),
        'type'        			=> 'checkbox',
        'description' 			=> '',
        'default'     			=> 'no'
      ),
      'title' => array(
        'title'       			=> __( 'Title', 'arkpay' ),
        'description' 			=> __( 'This controls the title which the user sees during checkout.', 'arkpay' ),
        'default'     			=> __( 'Arkpay Payment', 'arkpay' ),
        'type'        			=> 'text',
        'desc_tip'    			=> true,
      ),
      'description' => array(
        'title'       			=> __( 'Description', 'arkpay' ),
        'description' 			=> __( 'Arkpay description.', 'arkpay' ),
        'default'     			=> __( 'Pay with your credit card via arkpay payment gateway.', 'arkpay' ),
        'type'        			=> 'textarea',
        'desc_tip'    			=> true,
      ),
      'testmode' => array(
        'title'       			=> __( 'Test mode', 'arkpay' ),
        'label'       			=> __( 'Enable Test Mode', 'arkpay' ),
        'description' 			=> __( 'Place the payment gateway in test mode using test API keys.', 'arkpay' ),
        'type'        			=> 'checkbox',
        'default'     			=> 'no',
        'desc_tip'    			=> true,
      ),
      'api_key' => array(
        'title'       			=> __( 'API Key', 'arkpay' ),
        'type'        			=> 'text',
      ),
      'secret_key' => array(
        'title'       			=> __( 'Secret Key', 'arkpay' ),
        'type'        			=> 'text',
      ),
			'button_text' => array(
				'title'       			=> __( 'Checkout Page - Button', 'arkpay' ),
				'type'        			=> 'text',
			),
			'webhook_url' => array(
				'title'       			=> __( 'Webhook URL: ', 'arkpay' ),
				'type'        			=> 'text',
				'desc_tip' 					=> 'Copy this webhook URL to your ArkPay store settings.',
				'default'  					=> $this->get_webhook_url(),
				'custom_attributes' => array( 'readonly' => 'readonly' ),
			),
    ) );
  }

	/**
	 * Check If The Gateway Is Available For Use.
	 *
	 * @return bool
	 */
	public function is_available() {
		$order          = null;
		$needs_shipping = false;

		// Test if shipping is needed first.
		if ( WC()->cart && WC()->cart->needs_shipping() ) {
			$needs_shipping = true;
		} elseif ( is_page( wc_get_page_id( 'checkout' ) ) && 0 < get_query_var( 'order-pay' ) ) {
			$order_id = absint( get_query_var( 'order-pay' ) );
			$order    = wc_get_order( $order_id );

			// Test if order needs shipping.
			if ( $order && 0 < count( $order->get_items() ) ) {
				foreach ( $order->get_items() as $item ) {
					$_product = $item->get_product();
					if ( $_product && $_product->needs_shipping() ) {
						$needs_shipping = true;
						break;
					}
				}
			}
		}

		$needs_shipping = apply_filters( 'woocommerce_cart_needs_shipping', $needs_shipping );

		// Virtual order, with virtual disabled.
		if ( ! $this->enable_for_virtual && ! $needs_shipping ) {
			return false;
		}

		// Only apply if all packages are being shipped via chosen method, or order is virtual.
		if ( ! empty( $this->enable_for_methods ) && $needs_shipping ) {
			$order_shipping_items            = is_object( $order ) ? $order->get_shipping_methods() : false;
			$chosen_shipping_methods_session = WC()->session->get( 'chosen_shipping_methods' );

			if ( $order_shipping_items ) {
				$canonical_rate_ids = $this->get_canonical_order_shipping_item_rate_ids( $order_shipping_items );
			} else {
				$canonical_rate_ids = $this->get_canonical_package_rate_ids( $chosen_shipping_methods_session );
			}

			if ( ! count( $this->get_matching_rates( $canonical_rate_ids ) ) ) {
				return false;
			}
		}
		return parent::is_available();
	}

	/**
	 * Checks to see whether or not the admin settings are being accessed by the current request.
	 *
	 * @return bool
	 */
	private function is_accessing_settings() {
		if ( is_admin() ) {
			// phpcs:disable WordPress.Security.NonceVerification
			if ( ! isset( $_REQUEST['page'] ) || 'wc-settings' !== $_REQUEST['page'] ) {
				return false;
			}
			if ( ! isset( $_REQUEST['tab'] ) || 'checkout' !== $_REQUEST['tab'] ) {
				return false;
			}
			if ( ! isset( $_REQUEST['section'] ) || 'arkpay_payment' !== $_REQUEST['section'] ) {
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
			$options[ $method->get_method_title() ][ $method->id ] = sprintf( __( 'Any &quot;%1$s&quot; method', 'arkpay' ), $method->get_method_title() );

			foreach ( $zones as $zone ) {

				$shipping_method_instances = $zone->get_shipping_methods();

				foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {

					if ( $shipping_method_instance->id !== $method->id ) {
						continue;
					}

					$option_id = $shipping_method_instance->get_rate_id();

					// Translators: %1$s shipping method title, %2$s shipping method id.
					$option_instance_title = sprintf( __( '%1$s (#%2$s)', 'arkpay' ), $shipping_method_instance->get_title(), $shipping_method_instance_id );

					// Translators: %1$s zone name, %2$s shipping method instance name.
					$option_title = sprintf( __( '%1$s &ndash; %2$s', 'arkpay' ), $zone->get_id() ? $zone->get_zone_name() : __( 'Other locations', 'arkpay' ), $option_instance_title );

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
		$order = wc_get_order( $order_id );

		if ( $order->get_total() > 0 ) {

			// TODO - API

			$response = true;
			$response_code = 200;
	
			if ( ! $response ) {
				$error_message = 'ArkPay Error.';
				return "Something went wrong: $error_message";
			}
	
			if ( 200 !== $response_code ) {
				$order->update_status( apply_filters( 'woocommerce_arkpay_process_payment_order_status', $order->has_downloadable_item() ? 'on-hold' : 'pending', $order ), __( 'Pending payment.', 'arkpay' ) );
			}
	
			if ( 200 === $response_code ) {
				// var_dump($response);
				// $response_message = $response['message'];
				$response_message = 'Thank you! Your payment was successful';
				if ( 'Thank you! Your payment was successful' === $response_message ) {
					$order->payment_complete();
	
					// Remove cart.
					WC()->cart->empty_cart();
	
					// Return thankyou redirect.
					return array(
						'result'   => 'success',
						'redirect' => $this->get_return_url( $order ),
					);
				}
			}
		}
	}

  private function arkpay_payment_processing( $order ) {}

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
	public function get_arkpay_settings() {
		return array(
			'title' 			=> $this->title,
			'description' => $this->description,
			'testmode' 		=> $this->testmode,
			'payment_id' 	=> $this->id,
			'api_key' 		=> $this->api_key,
			'secret_key' 	=> $this->secret_key,
			'button_text' => $this->button_text,
			'webhook_url' => $this->get_webhook_url(),
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
	public function create_signature( $http_method, $api_uri, $body, $secret_key ) {
		$payload = $http_method . ' ' . $api_uri . "\n" . $body;
		return hash_hmac( 'sha256', $payload, $secret_key );
	}

	/**
	 * Get webhook URL.
	 */
	public function get_webhook_url() {
		return get_rest_url() . 'api/arkpay/webhook';
	}

	/**
	 * Get API URL.
	 */
	public function get_api_url() {
		return 'https://api-arkpay.exnihilo.dev/api/v1';
	}

	/**
	 * Create an ArkPay transaction.
	 *
	 * @param array $order An associative array containing transaction details.
	 *
	 * @return mixed The response from the ArkPay API.
	 */
	public function create_arkpay_transaction( $order ) {
		$settings = $this->get_arkpay_settings();

		$http_method	= 'POST';
		$api_key 			= $settings['api_key'];
		$secret_key 	= $settings['secret_key'];
		$api_url 			= $this->get_api_url();
		$api_uri 			= '/api/v1/merchant/api/transactions';
		$endpoint 		= '/merchant/api/transactions';

		$body = array(
			'merchantTransactionId' => $order['id'],
			'amount' 								=> $order['ammount'],
			'currency' 							=> $order['currency'],
			'description' 					=> $order['description'],
			'handlePayment' 				=> false,
		);

		$signature = $this->create_signature( $http_method, $api_uri, json_encode( $body ), $secret_key );
		$headers = array(
			'Content-Type: ' . 'application/json',
			'X-Api-Key: ' . $api_key,
			'Signature: ' . $signature,
		);

		$ch = curl_init( $api_url . $endpoint );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $body ) );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

		$response = curl_exec( $ch );

		if ( curl_errno( $ch ) ) {
			echo 'Error: ' . curl_error( $ch );
		}

		curl_close( $ch );
		return json_decode( $response );
	}

	public function save_draft_order( $order_data ) {
		global $wpdb;

		// Create draft order in the order table
		$table_order 				= $wpdb->prefix . 'arkpay_draft_order';
		$transaction_id 		= $order_data['transaction_id'];
		$transaction_status = $order_data['transaction_status'];
		$cart_items 				= $order_data['cart_items'];
		$order_id 					= $order_data['order_id'];
		$order_key 					= $order_data['order_key'];

		$wpdb->insert(
			$table_order,
			array(
				'transaction_id' 			=> $transaction_id,
				'transaction_status' 	=> $transaction_status,
				'cart_items' 					=> $cart_items,
				'order_id' 						=> $order_id,
				'order_key' 					=> $order_key,
			),
			array( '%s', '%s', '%s' )
		);
	}
}
