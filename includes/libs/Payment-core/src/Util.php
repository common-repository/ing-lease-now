<?php

namespace Leasenow\Payment;

/**
 * Class Util
 *
 * @package Leasenow\Payment
 */
class Util
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
	const NC_MISSING_TWISTO_RESPONSE_IN_POST = 16;
	const NC_MISSING_ORDER_ID_IN_POST = 17;
	const NC_CURL_IS_NOT_INSTALLED = 18;
	const NC_MISSING_SIGNATURE_IN_POST = 19;
	const NC_UNKNOWN = 100;
	// endregion

	// region notification status
	const NS_OK = 'ok';
	const NS_ERROR = 'error';
	// endregion

	// region error codes while processing leasing
	const EC_A = 'a'; // availability is not set/true
	const EC_CR = 'cr'; // error with creating reservation in db
	const EC_S = 's'; // curl response from api is not success
	const EC_BC = 'bc'; // empty body or missing credentials in database
	const EC_P = 'p'; // missing product
	// endregion

	const METHOD_REQUEST_POST = 'POST';
	const METHOD_REQUEST_GET = 'GET';
	const ENVIRONMENT_PRODUCTION = 'production';
	const ENVIRONMENT_SANDBOX = 'sandbox';
	const HMAC_ALGO = 'sha512';
	/**
	 * @var array
	 */
	private static $transactionStatuses = [
		'new'        => 'new',
		'authorized' => 'authorized',
		'pending'    => 'pending',
		'submitted_for_settlement'
		             => 'submitted_for_settlement',
		'rejected'   => 'rejected',
		'settled'    => 'settled',
		'error'      => 'error',
		'cancelled'  => 'cancelled',
	];

	/**
	 * @param string $body
	 * @param string $secret
	 *
	 * @return false|string
	 */
	public static function createHmac($body, $secret)
	{
		return hash_hmac(self::HMAC_ALGO, $body, $secret);
	}

	/**
	 * @param string $string
	 *
	 * @return array
	 */
	public static function parseStringToArray($string)
	{

		$array = [];

		parse_str($string, $array);

		return $array;
	}

	/**
	 * @param string $variable
	 *
	 * @return bool
	 */
	public static function isJson($variable)
	{
		json_decode($variable);

		return (json_last_error() === JSON_ERROR_NONE);
	}

	/**
	 * @return array
	 */
	public static function getTransactionStatuses()
	{
		return self::$transactionStatuses;
	}

	/**
	 * @param float $amount
	 *
	 * @return int
	 */
	public static function convertAmountToFractional($amount)
	{
		return (int) self::multiplyValues(round($amount, 2), 100, 0);
	}

	/**
	 * @param array $leasing
	 *
	 * @return bool
	 */
	public static function isEveryProductAvailable($leasing)
	{

		foreach($leasing['products'] as $product) {
			if(!$product['availability']) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array $leasing
	 *
	 * @return bool
	 */
	public static function displayTooltip($leasing, $isEveryProductAvailable)
	{

		return $isEveryProductAvailable && !$leasing['availability'];
	}

	/**
	 * @param int $amount
	 *
	 * @return float
	 */
	public static function convertAmountToMain($amount)
	{

		return round($amount / 100, 2);
	}

	/**
	 * @param number $firstValue
	 * @param number $secondValue
	 * @param number $precision
	 *
	 * @return float
	 */
	public static function multiplyValues($firstValue, $secondValue, $precision)
	{
		return round($firstValue * $secondValue, $precision);
	}
}
