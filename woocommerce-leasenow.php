<?php
/*
Plugin Name: ING Lease Now
Description: Add payment via Lease Now to WooCommerce
Version: 1.2.9
Author: ING
Author URI: https://leasenow.pl
Text Domain: leasenow
*/

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Leasenow\Payment\Api;
use Leasenow\Payment\Availability;
use Leasenow\Payment\Util;

require_once __DIR__ . "/includes/libs/Payment-core/src/Util.php";
require_once __DIR__ . "/includes/libs/Payment-core/src/Availability.php";

/**
 * @var string
 */
const LEASENOW_PAYMENT_METHOD_CODE = 'leasenow';

// region return page
if ( isset( $_GET['plugin_page'] ) && sanitize_text_field( $_GET['plugin_page'] ) == "lnreturn" ) {
	add_filter( 'the_title', 'leasenow_return_title' );
	add_filter( 'the_content', 'leasenow_return_content' );
	add_action( 'template_redirect', 'leasenow_return_template' );
}

/**
 * Load content for return page
 */
function leasenow_return_content() {
	load_template( WOOCOMMERCE_LEASENOW_PLUGIN_DIR . 'includes/templates/leasenow_return.php', false );
}

/**
 * Load template for return page
 */
function leasenow_return_template() {
	include( TEMPLATEPATH . "/page.php" );
	exit;
}

/**
 * Load title for return page
 *
 * @param $title
 *
 * @return mixed
 */
function leasenow_return_title( $title ) {
	if ( in_the_loop() && ! is_archive() ) {
		return __( "Lease Now -  that's all!", 'leasenow' );
	}

	return $title;
}

// endregion

/**
 * @param string $id
 *
 * @return false|WC_Product|WC_Product_Variation|null
 */
function leasenow_get_product_by_id( $id ) {

	$product = wc_get_product( $id );

	if ( $product->is_type( 'variable' ) ) {
		$product = new WC_Product_Variation( $id );
	}

	return $product;
}

/**
 * @return string
 */
function leasenow_get_notification_url() {
	return add_query_arg( 'wc-api', 'wc_gateway_leasenow', home_url( '/' ) );
}

/**
 * @return string
 */
function leasenow_get_redirect_url() {
	return add_query_arg( 'plugin_page', 'lnreturn', home_url( '/' ) );
}

// region install plugin
register_activation_hook( __file__, 'leasenow_install' );
// endregion

// region button and action check leasing in admin order
add_action( 'add_meta_boxes', 'leasenow_add_meta_box_check_leasing' );
add_action( 'wp_ajax_leasenow_check_leasing', 'leasenow_ajax_leasenow_check_leasing' );
add_action( 'wp_ajax_nopriv_leasenow_check_leasing', 'leasenow_ajax_leasenow_check_leasing' );

/**
 * Add meta box to admin order for check leasing status
 *
 * @return string|void
 */
function leasenow_add_meta_box_check_leasing() {

	add_meta_box(
		'ln_check_status',
		__( 'Leasing status', 'leasenow' ),
		'ln_check_status_metabox_content',
		class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order',
		'side',
		'core'
	);
}

/**
 * @param mixed $object
 *
 * @return void
 */
function ln_check_status_metabox_content( $object ) {

	$order = is_a( $object, 'WP_Post' )
		? wc_get_order( $object->ID )
		: $object;

	$leasenow_reservation_id = $order->get_meta( 'ln_reservation_id' );

	if ( $leasenow_reservation_id ) {

		global $wp_query;

		$wp_query->query_vars['leasenow_reservation_id'] = $leasenow_reservation_id;

		load_template( WOOCOMMERCE_LEASENOW_PLUGIN_DIR . 'includes/templates/leasenow_check_leasing.php', false );

		return;
	}

	echo __( 'Cannot check leasing - missing Leasing ID', 'leasenow' );
}

/**
 * Ajax check leasing API
 */
function leasenow_ajax_leasenow_check_leasing() {

	if ( ! isset( $_REQUEST['leasenow_reservation_id'] ) || ! $_REQUEST['leasenow_reservation_id'] ) {

		wp_send_json_error( [
			'success' => false,
		] );
	}

	$leasing = leasenow_get_leasing_api( sanitize_text_field( $_REQUEST['leasenow_reservation_id'] ), true );

	if ( $leasing['success']
	     && isset( $leasing['body']['status'] )
	     && $leasing['body']['status']
	) {

		wp_send_json_success(
			[
				'status' => leasenow_map_status( $leasing['body']['status'] ),
			]
		);
	}

	wp_send_json_error( [
		'error' => [
			'code' => $leasing['body']['error']['code'],
		],
	] );
}

/**
 * @param string $status
 *
 * @return string|void
 */
function leasenow_map_status( $status ) {

	switch ( $status ) {
		case Api::S_CREATED:
			return __( 'New leasing offer', 'leasenow' );
		case API::S_ASSIGNED:
			return __( 'The customer clicked used - started the process of filling in the application', 'leasenow' );
		case API::S_FILLED:
			return __( 'The client completed the application and received a preliminary positive decision', 'leasenow' );
		case API::S_SETTLED:
			return __( 'Leasing started', 'leasenow' );
		case API::S_DECLINED:
			return __( 'Leasing declined', 'leasenow' );
		default:
			return __( 'Leasing status unknown', 'leasenow' );
	}
}

// endregion

// region ajax get leasing
add_action( 'wp_ajax_nopriv_leasenow_add_content_after_addtocart_button_func_ajax', 'leasenow_add_content_after_addtocart_button_func_ajax' );
add_action( 'wp_ajax_leasenow_add_content_after_addtocart_button_func_ajax', 'leasenow_add_content_after_addtocart_button_func_ajax' );

/**
 * Get data from LeaseNow (via AJAX)
 *
 * @return void
 */
function leasenow_add_content_after_addtocart_button_func_ajax() {

	if ( isset( $_REQUEST['variation_id'] ) && $_REQUEST['variation_id'] ) {

		leasenow_get_availability_variation_product( $_REQUEST );
	}

	if ( ( isset( $_REQUEST['quantity'] ) && $_REQUEST['quantity'] )
	     && isset( $_REQUEST['product_id'] ) && $_REQUEST['product_id'] ) {

		leasenow_get_availability_ajax( sanitize_text_field( $_REQUEST['product_id'] ), sanitize_text_field( $_REQUEST['quantity'] ) );
	}

	wp_send_json_error( [
		'success' => false,
	] );
}

/**
 * @param string $productId
 * @param string $quantity
 *
 * @return void
 */
function leasenow_get_availability_ajax( $productId, $quantity ) {

	$product = leasenow_get_product_by_id( $productId );

	if ( ! $product->get_date_created() || ! $product->is_in_stock() ) {

		wp_send_json_error( [
			'success' => false,
			'body'    => [
				'error' => [
					'code' => Util::EC_P,
				],
			],
		] );
	}

	$leasing = leasenow_get_leasing_api( leasenow_prepare_availability_body( [
		[
			'data'     => $product,
			'quantity' => $quantity,
		],
	] ) );

	if ( ! $leasing['success']
	     || empty( $leasing['body']['imageUrl'] ) ) {
		wp_send_json_error( [
			'success' => false,
			'body'    => [
				'error' => [
					'code' => $leasing['body']['error']['code'],
				],
			],
		] );
	}

	$leasing = $leasing['body'];

	$arr = [
		'leasenow_redirect_url' => $leasing['redirectUrl'],
		'leasenow_message'      => $leasing['leasenow_message'],
		'leasenow_image_url'    => $leasing['imageUrl'],
		'leasenow_image_scale'  => leasenow_get_options()['leasenow_button_product_scale'],
	];

	if ( $leasing['availability'] && $leasing['reservationId'] ) {

		if ( leasenow_insert_reservation( $leasing['reservationId'],
			[
				[
					'id'       => $product->get_id(),
					'quantity' => 1,
				],
			],
			Util::convertAmountToFractional( wc_get_price_excluding_tax( $product ) ),
			get_current_user_id() ) ) {

			wp_send_json_success(
				$arr
			);
		}

		wp_send_json_error( [
			'success' => false,
			'body'    => [
				'error' => [
					'code' => $leasing['error']['code'],
				],
			],
		] );
	}

	if ( Util::displayTooltip( $leasing, Util::isEveryProductAvailable( $leasing ) )
	     && $leasing['missingNetAmount'] !== $leasing['minimalNetAmount'] ) {
		$arr['leasenow_tooltip_message'] = leasenow_get_tooltip_text( $leasing );
		$arr['leasenow_redirect_url']    = '';
	}

	wp_send_json_success(
		$arr
	);
}

/**
 * @return void
 */
function leasenow_get_availability_variation_product( $request ) {

	leasenow_get_availability_ajax( sanitize_text_field( $request['variation_id'] ), isset( $request['quantity'] ) && $request['quantity']
		? sanitize_text_field( $request['quantity'] )
		: 1 );
}

// endregion

/**
 * @return string
 */
function leasenow_get_table_structure() {

	$table_data      = leasenow_get_table_data_reservationlist();
	$charset_collate = $table_data['charset_collation'];
	$table_name      = $table_data['name'];

	return "CREATE TABLE IF NOT EXISTS `$table_name` (
		`id` INT(10) NOT NULL AUTO_INCREMENT, 
 		`reservation_id` VARCHAR(45) NOT NULL UNIQUE, 
 		`product_list` TEXT NOT NULL, 
 		`price` INT(10) NOT NULL, 
 		`order_created` INT (1) DEFAULT 0 NOT NULL, 
 		`user_id` BIGINT(20) UNSIGNED, 
 		`created` DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL, 
 		`modified` DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
		PRIMARY KEY  (`id`)
	) $charset_collate;";
}

/**
 * Function used for plugin install event
 *
 * @return void
 */
function leasenow_install() {

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( leasenow_get_table_structure() );
}

// endregion

// region init gateway leasenow
add_action( 'plugins_loaded', 'leasenow_init_woocommerce_gateway_leasenow', 0 );

/**
 * Init function that runs after plugin install.
 */
function leasenow_init_woocommerce_gateway_leasenow() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	define( 'WOOCOMMERCE_LEASENOW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	define( 'WOOCOMMERCE_LEASENOW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	load_plugin_textdomain( 'leasenow', false, dirname( plugin_basename( __FILE__ ) ) . '/langs/' );

	require_once( 'includes/class.WCGatewayLeasenow.php' );

	add_filter( 'woocommerce_payment_gateways', 'leasenow_add_leasenow_gateways' );
}

// endregion

/**
 * @return array
 */
function leasenow_get_table_data_reservationlist() {
	global $wpdb;

	return [
		'db_prefix'         => $wpdb->prefix,
		'name'              => $wpdb->prefix . 'ln_reservationlist',
		'charset_collation' => $wpdb->get_charset_collate(),
	];
}

/**
 * @param string $reservationId
 * @param array  $productList
 * @param int    $price
 * @param null   $user_id
 *
 * @return bool|int
 */
function leasenow_insert_reservation( $reservationId, $productList, $price, $user_id = null ) {
	global $wpdb;
	$table_data = leasenow_get_table_data_reservationlist();

	$time = current_time( 'mysql' );

	$data = [
		'reservation_id' => $reservationId,
		'product_list'   => json_encode( $productList ),
		'price'          => $price,
		'created'        => $time,
		'modified'       => $time,
	];

	if ( $user_id ) {
		$data['user_id'] = $user_id;
	}

	return $wpdb->insert(
		$table_data['name'],
		$data
	);
}

/**
 * @param string $reservationId
 * @param array  $data
 *
 * @return bool|int
 */
function leasenow_update_reservation( $reservationId, $data ) {
	global $wpdb;
	$table_data = leasenow_get_table_data_reservationlist();

	$data['modified'] = current_time( 'mysql' );

	return $wpdb->update(
		$table_data['name'],
		$data,
		[
			'id' => $reservationId,
		]
	);
}

/**
 * @return array
 */
function leasenow_get_cart() {

	$wc = WC();

	$product_list    = [];
	$db_product_list = [];
	$price           = 0;

	if ( ! $wc->cart ) {
		return [
			'product_list'    => $product_list,
			'db_product_list' => $db_product_list,
			'price'           => $price,
		];
	}

	foreach ( $wc->cart->get_cart() as $cart_item ) {

		$product = wc_get_product( $cart_item['product_id'] );

		if ( $product->is_type( 'variable' ) ) {
			$product = new WC_Product_Variation( $cart_item['variation_id'] );
		}

		$name = $product->get_title();

		if ( method_exists( $product, 'get_attribute_summary' )
		     && $attributeSummary = $product->get_attribute_summary() ) {
			$name .= ' - ' . $attributeSummary
				? ( ' - ' . $product->get_attribute_summary() )
				: '';
		}

		$productId = $product->get_id();

		$product_list[] = [
			'id'         => $productId,
			'url'        => $product->get_permalink(),
			'name'       => $name,
			'valueNet'   => round( $cart_item['line_total'] / $cart_item['quantity'], 2 ),
			'quantity'   => $cart_item['quantity'],
			'categoryId' => leasenow_get_product_category(
				( $product->is_type( 'simple' )
					? $productId
					: $product->get_parent_id() ),
				'product_cat',
				'',
				', ' ),
			'taxRate'    => leasenow_get_product_vat( $product->get_tax_class() ),
		];

		$db_product_list[] = [
			'id'       => $product->get_id(),
			'quantity' => $cart_item['quantity'],
		];
		$price             += wc_get_price_excluding_tax( $product ) * $cart_item['quantity'];
	}

	return [
		'product_list'    => $product_list,
		'db_product_list' => $db_product_list,
		'price'           => $price,
	];
}

// region display button at product list
add_action( 'woocommerce_after_shop_loop_item_title', 'leasenow_woocommerce_after_shop_loop_item_title_func' );

/**
 * Render HTML button before add to cart button in product list
 *
 * @return void
 */
function leasenow_woocommerce_after_shop_loop_item_title_func() {
	leasenow_content_button_addtocart_button( 'leasenow_button_product_list', false, 'leasenow_button_product_list_scale' );
}

// endregion

// region display button order summary
add_action( 'woocommerce_after_cart_totals', 'leasenow_add_content_after_cart_contents_button_func' );
add_action( 'woocommerce_cart_totals_before_order_total', 'leasenow_add_content_cart_totals_before_order_total_func' );

/**
 * Render HTML button after addtocart button
 *
 * @return void
 */
function leasenow_add_content_after_cart_contents_button_func() {
	leasenow_content_after_cart_contents_button( 'leasenow_button_cart_above' );
}

/**
 * Render HTML button after addtocart button
 *
 * @return void
 */
function leasenow_add_content_cart_totals_before_order_total_func() {
	leasenow_content_after_cart_contents_button( 'leasenow_button_cart_under' );
}

/**
 * @return string|void
 */
function leasenow_content_after_cart_contents_button( $option ) {

	if ( ! leasenow_check_credentials() ) {
		return '';
	}

	$plugin_options = leasenow_get_options();

	if ( $plugin_options[ $option ] === 'no' ) {
		return '';
	}

	$cart = leasenow_get_cart();

	$leasing = leasenow_get_leasing_api( leasenow_prepare_availability_body( $cart['product_list'], true ) );

	if ( ! $leasing['success'] ) {

		leasenow_load_template_error( $leasing['body']['error']['code'] );

		return;
	}

	$leasing = $leasing['body'];

	if ( Util::displayTooltip( $leasing, Util::isEveryProductAvailable( $leasing ) ) ) {

		leasenow_load_template_leasenow_button( $leasing, $plugin_options['leasenow_button_cart_scale'] );

		return;
	}

	if ( ! leasenow_insert_reservation(
		$leasing['reservationId'],
		$cart['db_product_list'],
		Util::convertAmountToFractional( $cart['price'] ),
		get_current_user_id()
	) ) {

		leasenow_load_template_error( isset( $leasing['error']['code'] ) && $leasing['error']['code']
			? $leasing['error']['code']
			: '' );

		return;
	}

	leasenow_load_template_leasenow_button( $leasing, $plugin_options['leasenow_button_cart_scale'] );
}

// endregion

/**
 * @param array $leasing
 *
 * @return string
 */
function leasenow_get_tooltip_text( $leasing ) {

	return sprintf( __( 'Only <strong>%s</strong> is missing to take advantage of the lease. Add more items to your cart and <strong style="color:#ff6200">Lease with ING.</strong>', LEASENOW_PAYMENT_METHOD_CODE ),
		number_format( $leasing['missingNetAmount'], 2 ) . ' PLN' );
}

/**
 * @param array $leasing
 */
function leasenow_load_template_leasenow_button( $leasing = '', $image_scale = 100 ) {

	global $wp_query;

	$wp_query->query_vars['leasenow_loading_gif']  = WOOCOMMERCE_LEASENOW_PLUGIN_URL . 'resources/images/leasenow_loading.gif';
	$wp_query->query_vars['leasenow_display']      = 0;
	$wp_query->query_vars['leasenow_availability'] = 0;
	$wp_query->query_vars['leasenow_product_id']   = 0;

	if ( ! $leasing ) {
		load_template( WOOCOMMERCE_LEASENOW_PLUGIN_DIR . 'includes/templates/leasenow_button.php', false );

		return;
	}

	$wp_query->query_vars['leasenow_availability'] = $leasing['availability'];

	$isEveryProductAvailable = Util::isEveryProductAvailable( $leasing );

	if ( ! $leasing['availability'] && ! $isEveryProductAvailable ) {
		load_template( WOOCOMMERCE_LEASENOW_PLUGIN_DIR . 'includes/templates/leasenow_button.php', false );

		return;
	}

	$tooltipDisplay = Util::displayTooltip( $leasing, $isEveryProductAvailable );

	if ( $tooltipDisplay ) {

		if ( $leasing['missingNetAmount'] === $leasing['minimalNetAmount'] ) {
			load_template( WOOCOMMERCE_LEASENOW_PLUGIN_DIR . 'includes/templates/leasenow_button.php', false );

			return;
		}

		if ( ( isset( $leasing['missingNetAmount'] ) && $leasing['missingNetAmount'] ) && $leasing['missingNetAmount'] > 0 ) {
			$wp_query->query_vars['leasenow_missing_amount_text'] = leasenow_get_tooltip_text( $leasing );
		}
	}

	$plugin_options = leasenow_get_options();

	$wp_query->query_vars['leasenow_display']         = 1;
	$wp_query->query_vars['leasenow_tooltip_display'] = $tooltipDisplay;
	$wp_query->query_vars['leasenow_code']            = '';
	$wp_query->query_vars['leasenow_redirect_url']    = isset( $leasing['redirectUrl'] ) && $leasing['redirectUrl']
		? $leasing['redirectUrl']
		: '';
	$wp_query->query_vars['leasenow_image_url']       = $leasing['imageUrl'];
	$wp_query->query_vars['leasenow_message']         = $leasing['leasenow_message'];
	$wp_query->query_vars['leasenow_nofollow']        = $plugin_options['leasenow_rel_no_follow'] === 'yes';
	$wp_query->query_vars['leasenow_image_scale']     = $image_scale;

	load_template( WOOCOMMERCE_LEASENOW_PLUGIN_DIR . 'includes/templates/leasenow_button.php', false );
}

/**
 * @param string $code
 */
function leasenow_load_template_error( $code ) {

	global $wp_query;
	$wp_query->query_vars['leasenow_loading_gif']  = WOOCOMMERCE_LEASENOW_PLUGIN_URL . 'resources/images/leasenow_loading.gif';
	$wp_query->query_vars['leasenow_code']         = $code;
	$wp_query->query_vars['leasenow_redirect_url'] = '';
	$wp_query->query_vars['leasenow_image_url']    = '';
	$wp_query->query_vars['leasenow_message']      = '';
	$wp_query->query_vars['leasenow_nofollow']     = 0;
	$wp_query->query_vars['leasenow_availability'] = 0;

	load_template( WOOCOMMERCE_LEASENOW_PLUGIN_DIR . 'includes/templates/leasenow_button.php', false );
}

/**
 * @return array
 */
function leasenow_get_options() {

	return get_option( 'woocommerce_leasenow_settings' );
}

/**
 * @return bool
 */
function leasenow_check_credentials() {

	$plugin_options = leasenow_get_options();

	return ( $plugin_options['leasenow_store_id'] && $plugin_options['leasenow_secret'] )
	       || ( $plugin_options['leasenow_sandbox_store_id'] && $plugin_options['leasenow_sandbox_secret'] );
}

/**
 * @param string $body
 *
 * @return array
 */
function leasenow_get_leasing_api( $body, $check = false ) {
	require_once __DIR__ . "/includes/libs/Payment-core/src/Api.php";

	if ( ! leasenow_check_credentials() || ! $body ) {
		return [
			'success' => false,
			'body'    => [
				'error' =>
					[
						'code' => Util::EC_BC,
					],
			],
		];
	}

	$plugin_options = leasenow_get_options();

	$store_id    = $plugin_options['leasenow_store_id'];
	$secret      = $plugin_options['leasenow_secret'];
	$environment = Util::ENVIRONMENT_PRODUCTION;

	if ( $plugin_options['leasenow_sandbox'] === 'yes' ) {
		$store_id    = $plugin_options['leasenow_sandbox_store_id'];
		$secret      = $plugin_options['leasenow_sandbox_secret'];
		$environment = Util::ENVIRONMENT_SANDBOX;
	}

	$api = new Api(
		$store_id,
		$secret,
		$environment
	);

	if ( $check ) {
		// body is leasing_id
		$leasing = $api->getStatus( '', $body );
	} else {
		$leasing = $api->getAvailability( $body );
	}

	if ( ! $leasing['success'] ) {
		return [
			'success' => false,
			'body'    => [
				'error' =>
					[
						'code' => Util::EC_S,
					],
			],
		];
	}

	$leasing  = $leasing['body'];
	$language = substr( get_locale(), 0, 2 );

	$message = '';
	foreach ( $leasing['additionalMessage'] as $v ) {
		if ( $v['languageCode'] === $language ) {
			$message = $v['message'];
		}
	}
	$leasing['leasenow_message'] = $message;
	unset( $leasing['additionalMessage'] );

	return [
		'success' => true,
		'body'    => $leasing,
	];
}

/**
 * @param string $post_id
 * @param string $taxonomy
 * @param string $before
 * @param string $sep
 * @param string $after
 *
 * @return array|false|int|string|WP_Error|WP_Term|WP_Term[]|null
 */
function leasenow_get_product_category( $post_id, $taxonomy, $before = '', $sep = '', $after = '' ) {
	$terms = get_the_terms( $post_id, $taxonomy );

	if ( is_wp_error( $terms ) ) {
		return $terms;
	}

	if ( empty( $terms ) ) {
		return false;
	}

	$links = [];

	foreach ( $terms as $term ) {
		$link = get_term_link( $term, $taxonomy );
		if ( is_wp_error( $link ) ) {
			return $link;
		}
		$links[] = $term->name;
	}

	$term_links = apply_filters( "term_links-$taxonomy", $links );  // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

	return $before . implode( $sep, $term_links ) . $after;
}

/**
 * @param array $product_list
 *
 * @return string
 */
function leasenow_prepare_availability_body( $product_list, $is_cart = false ) {

	$availability = new Availability();

	foreach ( $product_list as $product ) {

		if ( $is_cart ) {
			$availability->addItem(
				$product['url'],
				$product['name'],
				$product['valueNet'],
				$product['quantity'],
				$product['id'],
				$product['categoryId'],
				$product['taxRate']
			);
			continue;
		}

		$product_id = $product['data']->get_id();

		$name = $product['data']->get_title();

		if ( method_exists( $product['data'], 'get_attribute_summary' )
		     && $attributeSummary = $product['data']->get_attribute_summary() ) {
			$name .= ' - ' . $attributeSummary
				? ( ' - ' . $product['data']->get_attribute_summary() )
				: '';
		}

		$availability->addItem(
			$product['data']->get_permalink(),
			$name,
			wc_get_price_excluding_tax( $product['data'] ),
			$product['quantity'],
			$product_id,
			leasenow_get_product_category(
				( $product['data']->is_type( 'simple' )
					? $product_id
					: $product['data']->get_parent_id() ),
				'product_cat',
				'',
				', ' ),
			leasenow_get_product_vat( $product['data']->get_tax_class() )
		);
	}
	$availability->setCurrencyIsoName( get_woocommerce_currency() );
	$availability->setRedirectUrl( leasenow_get_redirect_url() );
	$availability->setNotificationUrl( leasenow_get_notification_url() );

	return $availability->prepareData();
}

// region display button product page
add_action( 'woocommerce_after_add_to_cart_button', 'leasenow_add_content_after_addtocart_button_func' );
add_action( 'woocommerce_before_add_to_cart_button', 'leasenow_add_content_before_addtocart_button_func' );

/**
 * @param string $option
 * @param bool   $display_variable
 * @param string $option_scale
 *
 * @return string|void
 */
function leasenow_content_button_addtocart_button( $option, $display_variable = true, $option_scale = '' ) {

	if ( ! leasenow_check_credentials() ) {
		return '';
	}

	$plugin_options = leasenow_get_options();

	if ( $plugin_options['leasenow_button_test'] === 'yes' ) {
		load_template( WOOCOMMERCE_LEASENOW_PLUGIN_DIR . 'includes/templates/leasenow_button_test.php', false );

		return;
	}

	if ( $plugin_options[ $option ] === 'no' ) {
		return '';
	}

	global $product;

	$is_variable = $product->is_type( 'variable' );

	if ( $is_variable && ! $display_variable ) {
		return '';
	}

	if ( $is_variable ) {

		foreach ( $product->get_available_variations() as $variation_values ) {
			foreach ( $variation_values['attributes'] as $key => $attribute_value ) {
				$attribute_name = str_replace( 'attribute_', '', $key );
				$default_value  = $product->get_variation_default_attribute( $attribute_name );
				if ( $default_value == $attribute_value ) {
					$is_default_variation = true;
				} else {
					$is_default_variation = false;
					break;
				}
			}
			if ( $is_default_variation ) {
				$variation_id = $variation_values['variation_id'];
				break;
			}
		}

		// Now we get the default variation data
		if ( $is_default_variation ) {

			// Get the "default" WC_Product_Variation object to use available methods
			$product = wc_get_product( $variation_id );
		}

		$attributes        = array_keys( $product->get_attributes() );
		$defaultAttributes = $product->get_default_attributes();

		foreach ( $attributes as $attribute ) {

			if ( ! isset( $defaultAttributes[ $attribute ] ) ) {

				leasenow_load_template_leasenow_button();

				return;
			}
		}

		leasenow_load_template_leasenow_button();

		return;
	}

	$leasing = leasenow_get_leasing_api( leasenow_prepare_availability_body( [
		[
			'data'     => $product,
			'quantity' => 1,
		],
	] ) );

	if ( $leasing['success'] ) {

		$scale = $option_scale
			? $plugin_options[ $option_scale ]
			: $plugin_options['leasenow_button_product_scale'];

		if ( empty( $leasing['availability'] ) ) {

			leasenow_load_template_leasenow_button( $leasing['body'], $scale );

			return;
		}

		if ( leasenow_insert_reservation(
			$leasing['body']['reservationId'],
			[
				[
					'id'       => $product->get_id(),
					'quantity' => 1,
				],
			],
			Util::convertAmountToFractional( wc_get_price_excluding_tax( $product ) ),
			get_current_user_id()
		) ) {
			leasenow_load_template_leasenow_button( $leasing['body'], $scale );

			return;
		}

		leasenow_load_template_error( Util::EC_CR );

		return;
	}

	leasenow_load_template_error( $leasing['body']['error']['code'] );
}

/**
 * Render HTML button after addtocart button
 *
 * @return void
 */
function leasenow_add_content_after_addtocart_button_func() {
	leasenow_content_button_addtocart_button( 'leasenow_button_product_after' );
}

/**
 * Render HTML button before addtocart button
 *
 * @return void
 */
function leasenow_add_content_before_addtocart_button_func() {
	leasenow_content_button_addtocart_button( 'leasenow_button_product_before' );
}

// endregion

/**
 * @param string $product_tax_class
 *
 * @return int
 */
function leasenow_get_product_vat( $product_tax_class ) {
	$tax_rates = WC_Tax::get_rates( $product_tax_class );
	$tax_rate  = 0;
	if ( ! empty( $tax_rates ) ) {
		$tax_rate = reset( $tax_rates );
		$tax_rate = (int) $tax_rate['rate'];
	}

	return $tax_rate;
}

/**
 * @param $methods
 *
 * @return array
 */
function leasenow_add_leasenow_gateways( $methods ) {
	$methods[] = 'WC_Gateway_Leasenow';

	return $methods;
}
