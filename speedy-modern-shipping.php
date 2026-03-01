<?php
/**
 * Plugin Name: Speedy Modern Shipping
 * Description: A clean, conflict-free Speedy integration for Bulgaria.
 * Version: 1.0.0
 * Author: DRUSOFT LTD
 * Text Domain: speedy-modern
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Declare HPOS Compatibility for WooCommerce 8.0+
 */
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( FeaturesUtil::class ) ) {
		FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__
		);
	}
} );

/**
 * Guard Clause: Exit if WooCommerce is not active.
 * This keeps the rest of the code clean and un-indented.
 */
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

/**
 * Define Constants
 * Helpful for paths and URLs throughout the plugin.
 */
define( 'SPEEDY_MODERN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SPEEDY_MODERN_URL',  plugin_dir_url( __FILE__ ) );

/**
 * Load Dependencies
 */
add_action( 'plugins_loaded', 'speedy_modern_load_dependencies' );
function speedy_modern_load_dependencies() {
	require_once SPEEDY_MODERN_PATH . 'class-speedy-modern-method.php';
	require_once SPEEDY_MODERN_PATH . 'includes/class-speedy-modern-syncer.php';
}

/**
 * Activation & Deactivation Hooks
 */
register_activation_hook( __FILE__, 'speedy_modern_activate' );
register_deactivation_hook( __FILE__, 'speedy_modern_deactivate' );

/**
 * Run on plugin activation.
 *
 * Creates tables and schedules sync.
 *
 * @return void
 */
function speedy_modern_activate(): void {
	// Create Database Tables
	require_once SPEEDY_MODERN_PATH . 'includes/class-speedy-modern-activator.php';
	Speedy_Modern_Activator::activate();

	// Schedule Background Data Sync (Action Scheduler)
	// This ensures we don't freeze the admin panel fetching thousands of offices
	$settings = get_option( 'woocommerce_speedy_modern_settings' );
	if ( ! empty( $settings['speedy_username'] ) && ! empty( $settings['speedy_password'] ) ) {
		if ( function_exists( 'as_schedule_single_action' ) && ! as_next_scheduled_action( 'speedy_modern_sync_locations_event' ) ) {
			as_schedule_single_action( time(), 'speedy_modern_sync_locations_event' );
		}
	}
}

/**
 * Run on plugin deactivation.
 *
 * Drops tables and clears scheduled actions.
 *
 * @return void
 */
function speedy_modern_deactivate(): void {
	// Unschedule the sync event so it doesn't run when plugin is disabled
	if ( function_exists( 'as_unschedule_action' ) ) {
		as_unschedule_action( 'speedy_modern_sync_locations_event' );
	}

	// Drop Database Tables
	require_once SPEEDY_MODERN_PATH . 'includes/class-speedy-modern-activator.php';
	Speedy_Modern_Activator::deactivate();
}

/**
 * Load plugin text domain.
 *
 * @return void
 */
add_action( 'plugins_loaded', 'speedy_modern_init' );
function speedy_modern_init(): void {
	load_plugin_textdomain( 'speedy-modern', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

/**
 * Add Speedy Modern to WooCommerce shipping methods.
 *
 * @param array $methods Existing shipping methods.
 * @return array Updated shipping methods.
 */
add_filter( 'woocommerce_shipping_methods', 'register_speedy_modern_method' );
function register_speedy_modern_method( $methods ) {
	$methods['speedy_modern'] = 'WC_Speedy_Modern_Method';
	return $methods;
}

/**
 * Enqueue scripts for the checkout page.
 *
 * @return void
 */
add_action( 'wp_enqueue_scripts', 'speedy_modern_enqueue_scripts' );
function speedy_modern_enqueue_scripts(): void {
	// Only load on checkout and only if we aren't in the admin
	if ( is_checkout() && ! is_admin() ) {
		wp_enqueue_script(
			'speedy-modern-checkout',
			SPEEDY_MODERN_URL . 'assets/js/checkout.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);

		// Pass PHP data to JS (like AJAX URL or carrier IDs)
		wp_localize_script( 'speedy-modern-checkout', 'speedy_params', array(
			'ajax_url'  => admin_url( 'admin-ajax.php' ),
			'method_id' => 'speedy_modern'
		));
	}
}

/**
 * Background Job Listeners
 * This connects the scheduled event to the actual logic.
 */
add_action( 'speedy_modern_sync_locations_event', array( 'Speedy_Modern_Syncer', 'sync' ) );

/**
 * Reschedule sync when settings are saved.
 *
 * @return void
 */
add_action( 'woocommerce_update_options_shipping_speedy_modern', 'speedy_modern_trigger_sync_on_save' );
function speedy_modern_trigger_sync_on_save(): void {
    if ( function_exists( 'as_schedule_single_action' ) ) {
        // Cancel any pending sync to avoid duplicates
        if ( function_exists( 'as_unschedule_action' ) ) {
            as_unschedule_action( 'speedy_modern_sync_locations_event' );
        }
        // Schedule new sync immediately
        as_schedule_single_action( time(), 'speedy_modern_sync_locations_event' );
    }
}