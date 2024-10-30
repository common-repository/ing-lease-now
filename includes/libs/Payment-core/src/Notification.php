<?php

namespace Leasenow\Payment;

use Exception;

/**
 * Class Notification
 *
 * @package Leasenow\Payment
 */
class Notification
{

	// region notification codes
	const NC_OK = 0;
	const NC_INVALID_SIGNATURE = 1;
	const NC_SERVICE_ID_NOT_MATCH = 2;
	const NC_ORDER_NOT_FOUND = 3;
	const NC_INVALID_SIGNATURE_HEADERS = 4;
	const NC_EMPTY_NOTIFICATION = 5;
	const NC_NOTIFICATION_IS_NOT_JSON = 6;
	const NC_INVALID_JSON_STRUCTURE = 7;
	const NC_INVALID_ORDER_STATUS = 8;
	const NC_AMOUNT_NOT_MATCH = 9;
	const NC_UNHANDLED_STATUS = 10;
	const NC_ORDER_STATUS_NOT_CHANGED = 11;
	const NC_CART_NOT_FOUND = 12;
	const NC_ORDER_STATUS_IS_NOT_SETTLED_ORDER_ARRANGEMENT_AFTER_IPN = 13;
	const NC_ORDER_EXISTS_ORDER_ARRANGEMENT_AFTER_IPN = 14;
	const NC_REQUEST_IS_NOT_POST = 15;
	const NC_MISSING_ORDER_ID_IN_POST = 16;
	const NC_CURL_IS_NOT_INSTALLED = 17;
	const NC_MISSING_SIGNATURE_IN_POST = 18;
	const NC_CUSTOMER_DATA_NOT_DECRYPTED = 19;
	const NC_CUSTOMER_DATA_IS_NOT_JSON = 20;
	const NC_CUSTOMER_DATA_HAS_INVALID_STRUCTURE = 20;
	const NC_UNKNOWN = 100;
	// endregion

	// region notification status
	const NS_OK = 'OK';
	const NS_ERROR = 'ERROR';
	// endregion

	// region leasing status
	const LEASING_STATUS_FILLED = 'FILLED';
	const LEASING_STATUS_SETTLED = 'SETTLED';
	const LEASING_STATUS_DECLINED = 'DECLINED';
	// endregion

	// region AES properties
	const AES_KEY_LENGTH = 32;
	const ALGO_AES_256_GCM = 'aes-256-gcm';
	// endregion

	// signature name
	const HEADER_SIGNATURE_NAME = 'HTTP_HMAC';

	/**
	 * @var string
	 */
	private $storeId = '';

	/**
	 * @var string
	 */
	private $secret = '';

	/**
	 * Notification constructor.
	 *
	 * @param string $storeId
	 * @param string $secret
	 */
	public function __construct($storeId, $secret)
	{
		$this->storeId = $storeId;
		$this->secret = $secret;
	}

	/**
	 * @param string $status
	 *
	 * @return bool
	 */
	public static function isSupportedStatus($status)
	{
		return isset([
				self::LEASING_STATUS_FILLED   => self::LEASING_STATUS_FILLED,
				self::LEASING_STATUS_SETTLED  => self::LEASING_STATUS_SETTLED,
				self::LEASING_STATUS_DECLINED => self::LEASING_STATUS_DECLINED,
			][$status]);
	}

	/**
	 * @param string $status
	 * @param string $reservationId
	 *
	 * @return string
	 */
	public static function formatResponse($status, $reservationId = '')
	{
		$response = [
			'status' => $status,
		];

		if($reservationId) {
			$response['reservationId'] = $reservationId;
		}

		header('Content-Type: application/json');
		// region add additional data for some cases and set proper header
		switch($status) {
			case self::NS_OK:

				header('HTTP/1.1 200 OK');
				break;
			case self::NS_ERROR:

				header('HTTP/1.1 400 Bad Request');
				break;
			default:

				break;
		}
		// endregion

		return json_encode($response);
	}

	/**
	 * Verify notification body and signature
	 *
	 * @return bool|array
	 * @throws Exception
	 */
	public function checkRequest()
	{

		if(!isset($_SERVER['CONTENT_TYPE'], $_SERVER[self::HEADER_SIGNATURE_NAME]) || $_SERVER['CONTENT_TYPE'] !== 'application/json') {

			return self::NC_INVALID_SIGNATURE_HEADERS;
		}

		$payload = file_get_contents('php://input', true);

		if(!$payload) {

			return self::NC_EMPTY_NOTIFICATION;
		}

		if(!Util::isJson($payload)) {

			return self::NC_NOTIFICATION_IS_NOT_JSON;
		}

		if($_SERVER[self::HEADER_SIGNATURE_NAME] !== Util::createHmac($payload . $this->storeId, $this->secret)) {

			return self::NC_INVALID_SIGNATURE;
		}

		try {

			Validate::notification($payload);
		} catch(Exception $e) {

			return self::NC_INVALID_JSON_STRUCTURE;
		}

		$payload = json_decode($payload, true);

		$payload['customerData'] = $this->decryptCustomerData($payload['customerData']);

		if(!Util::isJson($payload['customerData'])) {
			return self::NC_CUSTOMER_DATA_IS_NOT_JSON;
		}

		try {

			Validate::customerData($payload['customerData']);
		} catch(Exception $e) {

			return self::NC_CUSTOMER_DATA_HAS_INVALID_STRUCTURE;
		}

		$payload['customerData'] = json_decode($payload['customerData']);

		return $payload;
	}

	/**
	 * @param $body
	 *
	 * @return false|string
	 */
	private function decryptCustomerData($body)
	{

		$data = base64_decode($body);

		$key = $this->secret;
		$key_length = strlen($key);

		if($key_length > 32) {
			$key = substr($key, 0, self::AES_KEY_LENGTH);
		} else {
			$key = str_pad($key, self::AES_KEY_LENGTH, chr(0));
		}

		$cipher = self::ALGO_AES_256_GCM;

		$iv_len = openssl_cipher_iv_length($cipher);

		$ciphertext = substr($data, $iv_len, -16);
		$tag = substr($data, -16);

		return openssl_decrypt(
			$ciphertext,
			$cipher,
			$key,
			OPENSSL_RAW_DATA,
			substr($data, 0, $iv_len),
			$tag
		);
	}
}
