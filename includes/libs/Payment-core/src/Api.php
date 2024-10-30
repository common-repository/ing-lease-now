<?php

namespace Leasenow\Payment;

/**
 * Class Api
 *
 * @package Leasenow\Payment
 */
class Api
{

	const PATH_AVAILABILITY = 'availability';
	const PATH_STATUS = 'status';

	const S_CREATED = 'CREATED';
	const S_ASSIGNED = 'ASSIGNED';
	const S_FILLED = 'FILLED';
	const S_SETTLED = 'SETTLED';
	const S_DECLINED = 'DECLINED';

	/**
	 * @var array
	 */
	private static $serviceUrls = [
		Util::ENVIRONMENT_PRODUCTION => 'https://ecommerce.leasenow.pl/api/ecommerce',
		Util::ENVIRONMENT_SANDBOX    => 'https://acc-ecommerce.leasenow.pl/api/ecommerce',
	];

	/**
	 * @var string
	 */
	private $storeId;

	/**
	 * @var string
	 */
	private $secret;

	/**
	 * @var string
	 */
	private $environment;

	/**
	 * Api constructor.
	 *
	 * @param string $storeId
	 * @param string $secret
	 * @param string $environment
	 */
	public function __construct($storeId, $secret, $environment = '')
	{

		$this->storeId = $storeId;
		$this->secret = $secret;

		if(!$environment) {
			$environment = Util::ENVIRONMENT_PRODUCTION;
		}

		$this->environment = $environment;
	}

	/**
	 * @param string $body
	 *
	 * @return array
	 */
	public function getAvailability($body)
	{

		return $this->call(
			$this->getPathUrl($this->getPath(self::PATH_AVAILABILITY)),
			Util::METHOD_REQUEST_POST,
			$body
		);
	}

	/**
	 * @param string $body
	 *
	 * @return array
	 */
	public function getStatus($body, $leasingId)
	{

		return $this->call(
			$this->getPathUrl($this->getPath(self::PATH_STATUS, $leasingId)),
			Util::METHOD_REQUEST_GET,
			$body
		);
	}

	/**
	 * @param string $url
	 * @param string $methodRequest
	 * @param string $body
	 *
	 * @return array
	 */
	private function call($url, $methodRequest, $body)
	{

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $methodRequest);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);
		curl_setopt($curl, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Hmac: ' . Util::createHmac($body . parse_url($url, PHP_URL_PATH), $this->secret),
		]);

		$resultCurl = json_decode(curl_exec($curl), true);

		$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if(($httpCode !== 200) || !$resultCurl) {

			$array = [
				'success' => false,
				'data'    => [
					'httpCode'  => $httpCode,
					'curlError' => curl_error($curl),
					'body'      => [
						'message' => '',
						'code'    => '',
					],
				],
			];

			if(isset($resultCurl['error']['message']) && $resultCurl['error']['message']) {
				$array['data']['body']['message'] = $resultCurl['error']['message'];
			}

			if(isset($resultCurl['error']['code']) && $resultCurl['error']['code']) {
				$array['data']['body']['code'] = $resultCurl['error']['code'];
			}

			return $array;
		}

		return [
			'success' => true,
			'body'    => $resultCurl,
		];
	}

	/**
	 * @param string $path
	 *
	 * @return string
	 */
	private function getPathUrl($path)
	{

		$baseUrl = self::getServiceUrl();

		if(!$baseUrl) {
			return '';
		}

		return $baseUrl
			. $path;
	}

	/**
	 * @return string
	 */
	private function getServiceUrl()
	{

		if(isset(self::$serviceUrls[$this->environment])) {
			return self::$serviceUrls[$this->environment];
		}

		return '';
	}

	/**
	 * @param string $path
	 *
	 * @return string
	 */
	private function getPath($path, $leasingId = '')
	{

		$rPath = '';
		switch($path) {

			case self::PATH_AVAILABILITY:
				$rPath = '/' . $this->storeId . '/leasing/availability';
				break;
			case self::PATH_STATUS:
				$rPath = '/' . $this->storeId . '/leasing/' . $leasingId . '/status';
				break;
			default:
				break;
		}

		return $rPath;
	}
}
