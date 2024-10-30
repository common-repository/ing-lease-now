<?php

namespace Leasenow\Payment;

/**
 * Class Availability
 *
 * @package Leasenow\Payment
 */
class Availability
{

	/**
	 * @var array
	 */
	private $products;

	/**
	 * @var string
	 */
	private $currencyIsoName;

	/**
	 * @var string
	 */
	private $notificationUrl;

	/**
	 * @var string
	 */
	private $redirectUrl;

	/**
	 * @param string $url
	 * @param string $name
	 * @param number $valueNet
	 * @param int    $quantity
	 * @param string $id
	 * @param string $categoryId
	 * @param int    $valueVatPercent
	 *
	 * @return void
	 */
	public function addItem($url, $name, $valueNet, $quantity, $id, $categoryId, $valueVatPercent)
	{

		$this->products[] = [
			'url'             => $url,
			'name'            => $name,
			'valueNet'        => $valueNet,
			'quantity'        => $quantity,
			'id'              => $id,
			'categoryId'      => $categoryId,
			'valueVatPercent' => $valueVatPercent,
		];
	}

	/**
	 * @param int $currency
	 *
	 * @return void
	 */
	public function setCurrencyIsoName($currency)
	{

		$this->currencyIsoName = $currency;
	}

	/**
	 * @param string $notificationUrl
	 *
	 * @return void
	 */
	public function setNotificationUrl($notificationUrl)
	{

		$this->notificationUrl = $notificationUrl;
	}

	/**
	 * @param string $redirectUrl
	 *
	 * @return void
	 */
	public function setRedirectUrl($redirectUrl)
	{

		$this->redirectUrl = $redirectUrl;
	}

	/**
	 * @return string
	 */
	public function prepareData()
	{

		$data = [
			'products'        => $this->products,
			'notificationUrl' => $this->notificationUrl,
			'redirectUrl'     => $this->redirectUrl,
			'currencyIsoName' => $this->currencyIsoName,
		];

		if($this->redirectUrl) {
			$data['redirectUrl'] = $this->redirectUrl;
		}

		return json_encode($data);
	}
}
