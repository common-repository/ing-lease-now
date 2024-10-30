<?php

use Leasenow\Helper;

/**
 * Class WC_Gateway_Leasenow
 */
class WC_Gateway_Leasenow extends WC_Payment_Gateway {

	/**
	 * @var string
	 */
	const PAYMENT_METHOD = 'leasenow';

	/**
	 * initialise gateway with custom settings
	 */
	public function __construct() {

		$this->setup_properties();

		$this->init_form_fields();
		$this->init_settings();

		$this->icon = $this->get_image( 'logo.png' );

		// region actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [
			$this,
			'process_admin_options',
		] );
		add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), [
			$this,
			'leasenow_notification',
		] );
		add_action( 'woocommerce_receipt_' . $this->id, [
			$this,
			'receipt_page',
		] );
		// endregion

		// region filters
		add_filter( 'woocommerce_available_payment_gateways', [
			$this,
			'check_leasenow_available_payment_gateways',
		] );
		add_filter( 'woocommerce_get_wp_query_args', function ( $wp_query_args, $query_vars ) {
			if ( isset( $query_vars['meta_query'] ) ) {
				$meta_query                  = isset( $wp_query_args['meta_query'] )
					? $wp_query_args['meta_query']
					: [];
				$wp_query_args['meta_query'] = array_merge( $meta_query, $query_vars['meta_query'] );
			}

			return $wp_query_args;
		}, 10, 2 );
		// endregion
	}

	/**
	 * @param string $order_id
	 */
	public function receipt_page( $order_id ) {

		header( "Cache-Control: no-cache, no-store, must-revalidate" );

		$cart = leasenow_get_cart();

		$leasing = leasenow_get_leasing_api( leasenow_prepare_availability_body( $cart['product_list'], true ) );

		if ( $leasing['success']
		     && ( isset( $leasing['body']['reservationId'] ) && $leasing['body']['reservationId'] )
		     && ( isset( $leasing['body']['redirectUrl'] ) && $leasing['body']['redirectUrl'] ) ) {

			$wc = WC();

			// Remove cart
			$wc->cart->empty_cart();

			$order = new WC_Order( $order_id );
			$order->update_meta_data( 'ln_reservation_id', esc_textarea( $leasing['body']['reservationId'] ) );
			$order->save_meta_data();

			wp_redirect( esc_url( $leasing['body']['redirectUrl'] ) );
			die();
		}

		wp_redirect( home_url() );
		die();
	}

	/**
	 * Initialize form in admin panel.
	 */
	public function init_form_fields() {

		$scale_image = [
			'title'   => __( 'Image size as a percentage', self::PAYMENT_METHOD ),
			'type'    => 'number',
			'default' => 100,
			'css'     => 'width:60px;',
		];

		$hr = [
			'title' => '<hr style="border-top:1px black solid !important;"/>',
			'type'  => 'title',
		];

		$t_enable = __( 'Enable', self::PAYMENT_METHOD );

		$this->form_fields = [

			'leasenow_store_id'              => [
				'title'   => __( 'Store ID', self::PAYMENT_METHOD ),
				'type'    => 'text',
				'default' => '',
				'label'   => $t_enable,
			],
			'leasenow_secret'                => [
				'title'   => __( 'Secret', self::PAYMENT_METHOD ),
				'type'    => 'text',
				'default' => '',
				'label'   => $t_enable,
			],

			$hr,
			'leasenow_sandbox'               => [
				'title'   => __( 'Sandbox', self::PAYMENT_METHOD ),
				'type'    => 'checkbox',
				'label'   => $t_enable,
				'default' => 'no',
			],
			'leasenow_sandbox_store_id'      => [
				'title'   => __( 'Sandbox store ID', self::PAYMENT_METHOD ),
				'type'    => 'text',
				'default' => '',
				'label'   => $t_enable,
			],
			'leasenow_sandbox_secret'        => [
				'title'   => __( 'Sandbox secret', self::PAYMENT_METHOD ),
				'type'    => 'text',
				'default' => '',
				'label'   => $t_enable,
			],
			$hr,
			'leasenow_button_checkout'       => [
				'title'   => __( 'Display as payment method', self::PAYMENT_METHOD ),
				'type'    => 'checkbox',
				'default' => 'no',
				'label'   => $t_enable,
			],
			'leasenow_title'                 => [
				'title'   => __( 'Payment title', self::PAYMENT_METHOD ),
				'type'    => 'text',
				'default' => $this->get_default_title(),
			],
			'leasenow_description'           => [
				'title'   => __( 'Payment description', self::PAYMENT_METHOD ),
				'type'    => 'text',
				'default' => $this->get_default_description(),
			],
			$hr,
			'leasenow_button_product_before' => [
				'title'   => __( 'Display on product page <span style="font-weight:700">before</span> add to cart button', self::PAYMENT_METHOD ),
				'type'    => 'checkbox',
				'default' => 'no',
				'label'   => $t_enable,
			],
			'leasenow_button_product_after'  => [
				'title'   => __( 'Display on product page <span style="font-weight:700">after</span> add to cart button', self::PAYMENT_METHOD ),
				'type'    => 'checkbox',
				'default' => 'no',
				'label'   => $t_enable,
			],
			'leasenow_button_product_scale'  => $scale_image,
			'leasenow_button_test'           => [
				'title'   => __( 'Display test button on product page', self::PAYMENT_METHOD ),
				'type'    => 'checkbox',
				'default' => 'no',
				'label'   => $t_enable,
			],
			$hr,

			'leasenow_button_cart_above' => [
				'title'   => __( 'Display on cart page <span style="font-weight:700">above</span> summary', self::PAYMENT_METHOD ),
				'type'    => 'checkbox',
				'default' => 'no',
				'label'   => $t_enable,
			],
			'leasenow_button_cart_under' => [
				'title'   => __( 'Display on cart page <span style="font-weight:700">under</span> summary', self::PAYMENT_METHOD ),
				'type'    => 'checkbox',
				'default' => 'no',
				'label'   => $t_enable,
			],
			'leasenow_button_cart_scale' => $scale_image,

			$hr,

			'leasenow_button_product_list'       => [
				'title'   => __( 'Display on product list (for custom themes possible additional configuration)', self::PAYMENT_METHOD ),
				'type'    => 'checkbox',
				'default' => 'no',
				'label'   => $t_enable,
			],
			'leasenow_button_product_list_scale' => $scale_image,

			$hr,

			'leasenow_rel_no_follow' => [
				'title'   => __( 'Add nofollow attribute to buttons', self::PAYMENT_METHOD ),
				'type'    => 'checkbox',
				'default' => 'no',
				'label'   => $t_enable,
			],
		];
	}

	/**
	 * @return string
	 */
	public function get_default_title() {
		return __( 'Leasing (for business)', self::PAYMENT_METHOD );
	}

	/**
	 * @return string
	 */
	public function get_default_description() {
		return __( 'Adjust the leasing parameters by yourself and complete the application. After receiving the decision, choose the way of signing the contract (online or by courier).', self::PAYMENT_METHOD );
	}

	/**
	 * @param string $file
	 *
	 * @return string
	 */
	public function get_image( $file ) {
		return WOOCOMMERCE_LEASENOW_PLUGIN_URL . 'resources/images/' . $file . '\'';
	}

	/**
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {

		$order = new WC_Order( $order_id );

		// Return thank you redirect
		return [
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		];
	}

	/**
	 * Method that checking notify and changing status
	 *
	 * @throws Exception
	 */
	public function leasenow_notification() {

		include_once 'Helper.php';

		$store_id = $this->get_option( 'leasenow_store_id' );
		$secret   = $this->get_option( 'leasenow_secret' );

		if ( $this->get_option( 'leasenow_sandbox' ) === 'yes' ) {
			$store_id = $this->get_option( 'leasenow_sandbox_store_id' );
			$secret   = $this->get_option( 'leasenow_sandbox_secret' );
		}

		Helper::process_notification(
			$store_id,
			$secret
		);
	}

	/**
	 * Validate data on checkout page.
	 *
	 * @param array $gateways
	 */
	public function check_leasenow_available_payment_gateways( $gateways ) {

		if ( is_admin() || ! is_checkout() ) {

			return $gateways;
		}

		$plugin_options = leasenow_get_options();

		$cart = leasenow_get_cart();

		$leasing = leasenow_get_leasing_api( leasenow_prepare_availability_body( $cart['product_list'], true ) );

		$this->description = $this->get_option( 'leasenow_description' );

		if ( $leasing['success']
		     && $plugin_options['leasenow_button_checkout'] === 'yes'
		     && $leasing['body']['availability'] ) {

			return $gateways;
		}

		unset( $gateways['leasenow'] );

		return $gateways;
	}

	/**
	 * Setup general properties for the gateway.
	 */
	protected function setup_properties() {

		$this->id                 = self::PAYMENT_METHOD;
		$this->title              = $this->get_option( 'leasenow_title' );
		$this->description        = $this->get_option( 'leasenow_description' );
		$this->method_title       = __( 'ING Lease Now', self::PAYMENT_METHOD );
		$this->method_description = __( 'ING Lease Now - a customer can calculate lease installments and sign a lease contract. An item will be purchased by ING.', self::PAYMENT_METHOD );
		$this->has_fields         = false;
		$this->supports           = [
			'products',
		];
	}
}
