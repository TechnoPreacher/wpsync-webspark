<?php
/**
 * Plugin Name: WP Sync Webspark Plugin (SWP)
 * Description:
 * Version: 1.0
 * Text Domain: search
 * Domain Path: /lang/
 * Author: TechnoPreacher
 * License: GPLv2 or later
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // direct access denided.
}


add_action( 'plugins_loaded', 'swp_loaded' ); // set initial conditions.
add_action( 'swp_hourly_update', 'data_update' ); // cron hook.

register_activation_hook( __FILE__, 'swp_activate' );
register_deactivation_hook( __FILE__, 'swp_deactivate' );// remove plugin actions.

require_once __DIR__ . '/inc/Swagger.php';

function data_update() {

	// 1. get goods by api.

	$swagger = new Swagger();
	$swagger->prepare_url();
	$answer = $swagger->get_answer();
	$data   = &$answer['data'];
	$error  = $answer['error'];

	// $data = array_slice( $data, 0, 5 ); // uncomment for test.

	if ( $error ) {
		return false; // no action if error in server response.
	}

	// get all local goods.
	$products = wc_get_products(
		array(
			'numberposts' => - 1,
			'post_status' => 'published',
		)
	);

	// simplify array of local goods for id & sku only.
	$products_id_and_sku = array();
	foreach ( $products as $v ) {
		$products_id_and_sku[ $v->get_sku() ] = $v->get_id();
	}
	foreach ( $data as $v ) {
		// if there is new sku - create new product locally.
		if ( ! array_key_exists( $v['sku'], $products_id_and_sku ) ) {
			$product = new WC_Product_Simple();
			$product->set_sku( $v['sku'] );
			$product->set_name( $v['name'] );
			$product->set_description( $v['description'] );
			$product->set_regular_price( substr( $v['price'], 1 ) );
			$product->set_image_id( $v['picture'] );
			$product->set_manage_stock( true );
			$product->set_stock_quantity( $v['in_stock'] );
			$product->save();
		} else {

			$changed = false; // status for changes.

			$id                  = $products_id_and_sku[ $v['sku'] ];
			$product             = wc_get_product( $id );
			$current_name        = $product->get_name();
			$current_description = $product->get_description();
			$current_price       = $product->get_price();
			$current_picture     = $product->get_image_id();
			$current_stock       = $product->get_stock_quantity();

			$new_name        = $v['name'];
			$new_description = $v['description'];
			$new_price       = substr( $v['price'], 1 );
			$new_picture     = $v['picture'];
			$new_stock       = $v['in_stock'];

			if ( $current_name !== $new_name ) {
				$changed = true;
				$product->set_name( $new_name );
			}

			if ( $current_description !== $new_description ) {
				$changed = true;
				$product->set_description( $new_description );
			}

			if ( $current_price !== $new_price ) {
				$changed = true;
				$product->set_price( $new_price );
			}

			if ( $current_picture !== $new_picture ) {
				$changed = true;
				$product->set_image_id( $new_picture );
			}

			if ( $current_stock !== $new_stock ) {
				$changed = true;
				$product->set_image_id( $new_stock );
			}

			if ( $changed ) { // update only if have some changes.
				$product->set_name( 'modified_' . $product->get_name() );
				$product->save();
			}
		}
	}
	// 2. remove unupdated goods.

	$products_id_and_sku = array(); // clear.

	// get all products form site after update routine.
	$products = wc_get_products(
		array(
			'numberposts' => - 1,
			'post_status' => 'published',
		)
	);

	// simplifiy goods for sku & id's only
	foreach ( $products as $v ) {
		$products_id_and_sku[ $v->get_sku() ] = $v->get_id();
	}

	// simplify data from api for sku & id's only
	$simple_data = array();
	foreach ( $data as $v ) {
		$simple_data[ $v['sku'] ] = $v['id'];
	}

	// if there is no sku from site in data from api - delete sku locally.
	foreach ( $products_id_and_sku as $k => $v ) {
		if ( ! array_key_exists( $k, $simple_data ) ) {
			$product = wc_get_product( $v );
			$product->delete( true );
		}

	}

}


function swp_activate() {
	// Test to see if WooCommerce is active (including network activated).
	$plugin_path = trailingslashit( WP_PLUGIN_DIR ) . 'woocommerce/woocommerce.php';
	if (
		in_array( $plugin_path, wp_get_active_and_valid_plugins() )
		|| in_array( $plugin_path, wp_get_active_network_plugins() )
	) {
		// do_action( 'swp_hourly_update' ); // uncomment for test.

		wp_clear_scheduled_hook( 'swp_hourly_update' );
		wp_schedule_event( time(), 'hourly', 'swp_hourly_update' ); // add cron routine.
	} else {
		wp_die(
			'Sorry, but this plugin requires the Woocommerce to be installed and active. <br><a href="' .
			esc_url( admin_url( 'plugins.php' ) ) . '">&laquo; Return to Plugins</a>'
		);
	}

}

function swp_deactivate() {
	unload_textdomain( 'swp' );
	wp_clear_scheduled_hook( 'swp_hourly_update' );    // remove cron routine.
}

function swp_loaded() {
	$text_domain_dir = dirname( plugin_basename( __FILE__ ) ) . '/lang/'; // localization.
	load_plugin_textdomain( 'swp', false, $text_domain_dir );
}






