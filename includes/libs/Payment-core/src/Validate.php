<?php

namespace Leasenow\Payment;

use Exception;
use JsonSchema\Validator;

/**
 * Class Validate
 *
 * @package Leasenow\Payment
 */
class Validate
{

	/**
	 * @var string[]
	 */
	private static $contactTypeEnum = [
		'PHONE',
		'EMAIL',
	];

	/**
	 * @param string $data
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function notification($data)
	{

		$schema = [
			'type'       => 'object',
			'properties' => [
				'status'        => [
					'type'      => 'string',
					'minLength' => '1',
				],
				'reservationId' => [
					'type'      => 'string',
					'minLength' => '1',

				],
				'redirectUrl'   => [
					'type'      => 'string',
					'minLength' => '1',

				],
				'customerData'  => [
					'type'      => 'string',
					'minLength' => '1',

				],

			],
			'required'   => [
				'status',
				'reservationId',
				'redirectUrl',
				'customerData',
			],

		];

		return self::validate($data, $schema, 'notification');
	}

	/**
	 * @param string $data
	 * @param array  $schema
	 * @param string $schemaType
	 *
	 * @return bool
	 * @throws Exception
	 */
	private static function validate($data, $schema, $schemaType)
	{

		$data = json_decode($data);

		$validator = new Validator();
		$validator->validate($data, json_decode(json_encode($schema)));

		if($validator->isValid()) {
			return true;
		}

		$errors = [
			'schema' => $schemaType,
		];

		foreach($validator->getErrors() as $error) {
			$errors[$error['property']] = $error['message'];
		}

		throw new Exception(json_encode($errors));
	}

	/**
	 * @param string $data
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function customerData($data)
	{

		$schema = [
			'type'       => 'object',
			'properties' => [
				'name'           => [
					'type'      => 'string',
					'minLength' => '1',
				],
				'lastName'       => [
					'type'      => 'string',
					'minLength' => '1',
				],
				'billingAddress' => [
					'type'       => 'object',
					'properties' => [
						'streetString'   => [
							'type'      => 'string',
							'minLength' => '1',
						],
						'countryIsoCode' => [
							'type'      => 'string',
							'minLength' => '1',
						],
						'postCode'       => [
							'type'      => 'string',
							'minLength' => '1',
						],
						'city'           => [
							'type'      => 'string',
							'minLength' => '1',
						],
					],
					'required'   => [
						'streetString',
						'countryIsoCode',
						'postCode',
						'city',
					],

				],

				'deliveryAddress' => [
					'type'       =>
						[
							'object',
							'null',
						],
					'properties' => [
						'streetString'   => [
							'type'      => 'string',
							'minLength' => '1',
						],
						'countryIsoCode' => [
							'type'      => 'string',
							'minLength' => '1',
						],
						'postCode'       => [
							'type'      => 'string',
							'minLength' => '1',
						],
						'city'           => [
							'type'      => 'string',
							'minLength' => '1',
						],
					],
					'required'   => [
						'streetString',
						'countryIsoCode',
						'postCode',
						'city',
					],
				],

				'contacts' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'type'  => [
								'type' => 'string',
								'enum' => self::$contactTypeEnum,
							],
							'value' => [
								'type'      => 'string',
								'minLength' => '1',
							],
						],
						'required'   => [
							'type',
							'value',
						],
					],
				],

			],
			'required'   => [
				'name',
				'lastName',
				'billingAddress',
				'contacts',
			],
		];

		return self::validate($data, $schema, 'customerData');
	}
}
