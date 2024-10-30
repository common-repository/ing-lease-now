<?php

namespace Leasenow;

use Leasenow\Payment\Notification;

class Helper
{

	const CONTACT_TYPE_PHONE = 'PHONE';
	const CONTACT_TYPE_EMAIL = 'EMAIL';

	/**
	 * @param string $store_id
	 * @param string $secret
	 *
	 * @throws Exception
	 */
	public static function process_notification($store_id, $secret)
	{

		include_once 'libs/Payment-core/src/Notification.php';
		include_once 'libs/Payment-core/src/Validate.php';
		include_once 'libs/Payment-core/vendor/autoload.php';

		$notification = new Notification(
			$store_id,
			$secret
		);

		// it can be order data or notification code - depends on verification notification
		$result_check_request_notification = $notification->checkRequest();

		if(is_int($result_check_request_notification)) {
			echo Notification::formatResponse(Notification::NS_ERROR);
			exit();
		}

		$reservation_id = $result_check_request_notification['reservationId'];

		if(!Notification::isSupportedStatus($result_check_request_notification['status'])) {

			echo Notification::formatResponse(Notification::NS_OK, $reservation_id);
			exit();
		}

		// region try to find order and change status
		$orders = wc_get_orders([
				'meta_query' => [
					[
						'key'     => 'ln_reservation_id',
						'compare' => '=',
						'value'   => $reservation_id,
					],
				],
			]
		);

		if(count($orders) === 1) {

			$order = $orders[0];

			if($order->get_status() !== 'pending') {
				echo Notification::formatResponse(Notification::NS_ERROR);
				die();
			}

			switch($result_check_request_notification['status']) {
				case Notification::LEASING_STATUS_SETTLED:
					$new_status = $order->needs_processing()
						? 'processing'
						: 'completed';
					break;
				case Notification::LEASING_STATUS_DECLINED:
					$new_status = 'failed';
					break;
				default:
					$new_status = 'pending';
					break;
			}

			$order->update_status(
				$new_status
			);
			$order->save();

			wc_reduce_stock_levels($order->get_id());

			echo Notification::formatResponse(Notification::NS_OK, $reservation_id);
			exit;
		}
		// endregion

		global $wpdb;

		$table_data = leasenow_get_table_data_reservationlist();
		$table_name = $table_data['name'];

		$db_reservation = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `$table_name` WHERE `$table_name`.`reservation_id` = %s", $reservation_id
			)
		);

		if(!$db_reservation) {

			echo Notification::formatResponse(Notification::NS_ERROR, $reservation_id);
			exit();
		}

		if($db_reservation[0]->order_created) {

			echo Notification::formatResponse(Notification::NS_OK, $reservation_id);
			exit();
		}

		$product_list = json_decode($db_reservation[0]->product_list, true);

		foreach($product_list as $product) {
			$item = leasenow_get_product_by_id($product['id']);
			if(!$item->is_in_stock()) {
				echo Notification::formatResponse(Notification::NS_ERROR);
				exit();
			};
		}

		if(leasenow_update_reservation(
				$db_reservation[0]->id, [
					'order_created' => 1,
				]
			) && self::leasenow_create_order(
				$result_check_request_notification['customerData'],
				$product_list,
				$db_reservation[0]->user_id,
				$reservation_id
			)
		) {

			echo Notification::formatResponse(Notification::NS_OK, $reservation_id);
			exit();
		}

		echo Notification::formatResponse(Notification::NS_ERROR, $reservation_id);
		exit();
	}

	/**
	 * @param object $customer_data
	 * @param array  $product_list
	 * @param string $user_id
	 * @param string $reservation_id
	 *
	 * @return bool
	 */
	private static function leasenow_create_order($customer_data, $product_list, $user_id, $reservation_id = '')
	{
		$email = '';
		$phone = '';

		foreach($customer_data->contacts as $contact) {

			switch($contact->type) {
				case self::CONTACT_TYPE_PHONE:
					if($contact->prefix) {
						$phone .= $contact->prefix;
					}
					$phone .= $contact->value;
					break;
				case self::CONTACT_TYPE_EMAIL:
					if($contact->prefix) {
						$email .= $contact->prefix;
					}
					$email .= $contact->value;
					break;
				default:
					break;
			}
		}

		$address_billing = [
			'first_name' => $customer_data->name,
			'last_name'  => $customer_data->lastName,
			'email'      => $email,
			'phone'      => $phone,
			'address_1'  => $customer_data->billingAddress->streetString,
			'city'       => $customer_data->billingAddress->city,
			'postcode'   => $customer_data->billingAddress->postCode,
			'country'    => $customer_data->billingAddress->countryIsoCode,
		];

		if($customer_data->companyName) {
			$address_billing['company'] = $customer_data->companyName;
		}

		if($customer_data->companyNip) {
			$address_billing['company'] .= ' ' . $customer_data->companyNip;
		}

		if($user_id) {
			$address_billing['customer_id'] = $user_id;
		}

		$order = wc_create_order();

		if($customer_data->deliveryAddress) {
			$order->set_address([
				'first_name' => $customer_data->name,
				'last_name'  => $customer_data->lastName,
				'email'      => $email,
				'phone'      => $phone,
				'address_1'  => $customer_data->deliveryAddress->streetString,
				'city'       => $customer_data->deliveryAddress->city,
				'postcode'   => $customer_data->deliveryAddress->postCode,
				'country'    => $customer_data->deliveryAddress->countryIsoCode,
			], 'shipping');
		}

		foreach($product_list as $product) {
			$order->add_product(wc_get_product($product['id']), $product['quantity']);
		}

		$order->set_address($address_billing);
		$order->calculate_totals();
		if($reservation_id) {
			$order->add_meta_data('ln_reservation_id', $reservation_id);
			$order->save_meta_data();
		}

		return $order->update_status("pending", __('Payment via ING Lease Now', 'leasenow') . '. ', true);
	}
}
