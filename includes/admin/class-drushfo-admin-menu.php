<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Drushfo_Admin_Menu {

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
	}

	public static function add_menu_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Speedy Orders', 'drusoft-shipping-for-speedy' ),
			__( 'Speedy Orders', 'drusoft-shipping-for-speedy' ),
			'manage_woocommerce',
			'drushfo-orders',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function enqueue_scripts( $hook ): void {
		if ( 'woocommerce_page_drushfo-orders' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'drushfo-admin-orders',
			DRUSHFO_URL . 'assets/js/admin-orders.js',
			[ 'jquery' ],
			DRUSHFO_VER,
			true
		);

		wp_localize_script( 'drushfo-admin-orders', 'drushfo_admin_params', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'drushfo_actions' ),
			'i18n'     => [
				'confirm_cancel'  => __( 'Are you sure you want to cancel this shipment?', 'drusoft-shipping-for-speedy' ),
				'requesting'      => __( 'Requesting...', 'drusoft-shipping-for-speedy' ),
				'requested'       => __( 'Requested', 'drusoft-shipping-for-speedy' ),
				'request_courier' => __( 'Request Courier', 'drusoft-shipping-for-speedy' ),
				'generating'      => __( 'Generating...', 'drusoft-shipping-for-speedy' ),
				'generate'        => __( 'Generate', 'drusoft-shipping-for-speedy' ),
			],
		] );
	}

	public static function render_page(): void {
		require_once __DIR__ . '/class-drushfo-orders-list-table.php';

		$table = new Drushfo_Orders_List_Table();
		$table->prepare_items();

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Speedy Orders', 'drusoft-shipping-for-speedy' ) . '</h1>';
		echo '<form method="post">';
		$table->display();
		echo '</form>';
		echo '</div>';
	}
}

Drushfo_Admin_Menu::init();
