<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://arkpay.com
 * @since             1.0.0
 * @package           Arkpay
 *
 * @wordpress-plugin
 * Plugin Name:       Arkpay
 * Plugin URI:        https://arkpay.com
 * Description:       The Smartest, Fastest & Most Secure Payment Processor.
 * Version:           1.0.0
 * Author:            Arkpay
 * Author URI:        https://arkpay.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       arkpay-payment
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'ARKPAY_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-arkpay-activator.php
 */
function activate_arkpay() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-arkpay-activator.php';
    Arkpay_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-arkpay-deactivator.php
 */
function deactivate_arkpay() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-arkpay-deactivator.php';
    Arkpay_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_arkpay' );
register_deactivation_hook( __FILE__, 'deactivate_arkpay' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-arkpay.php';

/**
 * Initialize Arkpay payment gateway.
 */
function arkpay_payment_init() {
    if ( class_exists( 'WC_Payment_Gateway' ) ) {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-gateway-arkpay.php';

        $enable_direct = get_option( 'woocommerce_arkpay_payment_settings' )['enable_direct'];
        if ( $enable_direct === 'no' ) {
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-arkpay-cart-button.php';
        }
        
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-arkpay-checkout-button.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-arkpay-thankyou-redirect.php';

        // Register API webhook
        add_action( 'rest_api_init', 'register_api_webhook_route' );
    }
}
add_action( 'plugins_loaded', 'arkpay_payment_init', 11 );

/**
* Add Arkpay payment gateway to the list of available WooCommerce payment gateways.
*
* @param array $gateways List of available payment gateways.
* @return array Modified list of available payment gateways.
*/
function add_arkpay_payment_gateway_to_wc( $gateways ) {
    $gateways[] = 'WC_Gateway_Arkpay';

    return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'add_arkpay_payment_gateway_to_wc' );

/**
 * Register ArkPay API webhook route.
 */
function register_api_webhook_route() {
    require_once 'includes/class-wc-arkpay-webhook-handler.php';
	
    @register_rest_route( 'api/arkpay', '/webhook', array(
        'methods'  => 'POST',
        'callback' => 'handle_arkpay_transaction_status_change_webhook',
    ) );
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_arkpay() {

    $plugin = new Arkpay();
    $plugin->run();

}
run_arkpay();
