<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\IdentifyMethods;

use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\IdentifyMethodsPolicyValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdentifyMethodsPolicyValueTest extends TestCase {
	#[DataProvider('provideNormalizeCases')]
	public function testNormalizeWithDataProvider(mixed $rawValue, array $expected): void {
		self::assertSame($expected, IdentifyMethodsPolicyValue::normalize($rawValue));
	}

	/**
	 * @return iterable<string, array{0: mixed, 1: array<string, mixed>}>
	 */
	public static function provideNormalizeCases(): iterable {
		yield 'preserves canonical requirement from payload' => [
			[
				'factors' => [
					[
						'name' => 'email',
						'enabled' => true,
						'requirement' => 'required',
						'signatureMethods' => [
							'emailToken' => ['enabled' => false],
						],
					],
				],
			],
			[
				'factors' => [
					[
						'name' => 'email',
						'enabled' => true,
						'signatureMethods' => [
							'emailToken' => ['enabled' => false],
						],
						'requirement' => 'required',
					],
				],
			],
		];

		yield 'normalizes object payload and shared minimum factors' => [
			[
				'minimumTotalVerifiedFactors' => 2,
				'factors' => [
					[
						'name' => 'email',
						'enabled' => true,
						'requirement' => 'required',
						'signatureMethods' => [
							'emailToken' => ['enabled' => false],
						],
					],
				],
			],
			[
				'factors' => [
					[
						'name' => 'email',
						'enabled' => true,
						'signatureMethods' => [
							'emailToken' => ['enabled' => false],
						],
						'minimumTotalVerifiedFactors' => 2,
						'requirement' => 'required',
					],
				],
			],
		];

		yield 'accepts minimumTotalVerifiedFactors numeric string' => [
			[
				'minimumTotalVerifiedFactors' => '2',
				'factors' => [
					[
						'name' => 'sms',
						'enabled' => true,
						'signatureMethods' => [
							'smsToken' => ['enabled' => false],
						],
					],
				],
			],
			[
				'factors' => [
					[
						'name' => 'sms',
						'enabled' => true,
						'signatureMethods' => [
							'smsToken' => ['enabled' => false],
						],
						'minimumTotalVerifiedFactors' => 2,
					],
				],
			],
		];

		yield 'defaults enabled to true and supports signature methods labels' => [
			[
				'factors' => [
					[
						'name' => 'email',
						'signatureMethods' => [
							'emailToken' => 'Email token',
						],
					],
				],
			],
			[
				'factors' => [
					[
						'name' => 'email',
						'enabled' => true,
						'signatureMethods' => [
							'emailToken' => [
								'enabled' => false,
								'label' => 'Email token',
							],
						],
					],
				],
			],
		];

		yield 'ignores root string list entries' => [
			['email', 'sms'],
			[
				'factors' => [],
			],
		];

		yield 'ignores available signature methods when signatureMethods is absent' => [
			[
				'factors' => [
					[
						'name' => 'email',
						'availableSignatureMethods' => ['emailToken'],
					],
				],
			],
			[
				'factors' => [
					[
						'name' => 'email',
						'enabled' => true,
						'signatureMethods' => [],
					],
				],
			],
		];

		yield 'ignores can_create_account from legacy factor settings' => [
			[
				'factors' => [
					[
						'name' => 'email',
						'enabled' => true,
						'can_create_account' => false,
						'signatureMethods' => [
							'emailToken' => ['enabled' => false],
						],
					],
				],
			],
			[
				'factors' => [
					[
						'name' => 'email',
						'enabled' => true,
						'signatureMethods' => [
							'emailToken' => ['enabled' => false],
						],
					],
				],
			],
		];

		yield 'supports json payload with factors and top-level can_create_account' => [
			json_encode([
				'can_create_account' => true,
				'factors' => [
					[
						'name' => 'email',
						'signatureMethods' => [
							'emailToken' => ['enabled' => false],
						],
					],
				],
			], JSON_THROW_ON_ERROR),
			[
				'factors' => [
					[
						'name' => 'email',
						'enabled' => true,
						'signatureMethods' => [
							'emailToken' => ['enabled' => false],
						],
					],
				],
				'can_create_account' => true,
			],
		];

		yield 'returns empty factors when payload is empty without service' => [
			[],
			[
				'factors' => [],
			],
		];
	}

	public function testReturnDefaultsWhenPayloadIsEmptyWithService(): void {
		$identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$identifyMethodService->method('getIdentifyMethodsCatalogSettings')->willReturn([
			[
				'name' => 'account',
				'friendly_name' => 'Account',
				'enabled' => true,
				'requirement' => 'required',
				'signatureMethods' => [
					'clickToSign' => ['enabled' => true],
				],
			],
			[
				'name' => 'email',
				'friendly_name' => 'Email',
				'enabled' => true,
				'requirement' => 'optional',
				'signatureMethods' => [
					'emailToken' => ['enabled' => true],
				],
			],
		]);
		$identifyMethodService->method('getFriendlyNamesMap')->willReturn([
			'account' => 'Account',
			'email' => 'Email',
		]);
		$identifyMethodService->method('getDefaultIdentifyMethodsPolicy')->willReturn([
			[
				'name' => 'account',
				'enabled' => true,
				'requirement' => 'required',
				'signatureMethods' => [
					'clickToSign' => ['enabled' => true],
				],
			],
			[
				'name' => 'email',
				'enabled' => true,
				'requirement' => 'optional',
				'signatureMethods' => [
					'emailToken' => ['enabled' => true],
				],
			],
		]);

		$normalized = IdentifyMethodsPolicyValue::normalize([], $identifyMethodService);

		self::assertCount(2, $normalized['factors']);
		self::assertSame('account', $normalized['factors'][0]['name']);
		self::assertSame('email', $normalized['factors'][1]['name']);
		self::assertSame('Account', $normalized['factors'][0]['friendly_name']);
		self::assertSame('Email', $normalized['factors'][1]['friendly_name']);
	}

	public function testEmptyPayloadMatchesNormalizationOfServiceDefaults(): void {
		$identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$identifyMethodService->method('getIdentifyMethodsCatalogSettings')->willReturn([
			[
				'name' => 'account',
				'friendly_name' => 'Account',
				'enabled' => true,
				'requirement' => 'required',
				'signatureMethods' => [
					'password' => ['enabled' => true],
				],
			],
			[
				'name' => 'email',
				'friendly_name' => 'Email',
				'enabled' => false,
				'requirement' => 'optional',
				'signatureMethods' => [
					'emailToken' => ['enabled' => true],
				],
			],
		]);
		$identifyMethodService->method('getFriendlyNamesMap')->willReturn([
			'account' => 'Account',
			'email' => 'Email',
		]);
		$identifyMethodService->method('getDefaultIdentifyMethodsPolicy')->willReturn([
			[
				'name' => 'account',
				'enabled' => true,
				'requirement' => 'required',
				'signatureMethods' => [
					'password' => ['enabled' => true],
				],
				'signatureMethodEnabled' => 'password',
			],
			[
				'name' => 'email',
				'enabled' => false,
				'requirement' => 'optional',
				'signatureMethods' => [
					'emailToken' => ['enabled' => true],
				],
				'signatureMethodEnabled' => 'emailToken',
			],
		]);

		$normalizedDefault = IdentifyMethodsPolicyValue::normalize([], $identifyMethodService);
		$normalizedServiceDefaults = IdentifyMethodsPolicyValue::normalize(
			[
				'factors' => $identifyMethodService->getDefaultIdentifyMethodsPolicy(),
			],
			$identifyMethodService,
		);

		self::assertSame($normalizedServiceDefaults, $normalizedDefault);
	}

	public function testReturnsEmptyFactorsWhenPayloadIsEmptyWithoutService(): void {
		$normalized = IdentifyMethodsPolicyValue::normalize([]);

		self::assertCount(0, $normalized['factors']);
		self::assertArrayNotHasKey('can_create_account', $normalized);
	}

	public function testKeepsProvidedFriendlyNameAndOnlyEnrichesMissingOnes(): void {
		$identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$identifyMethodService->method('getIdentifyMethodsCatalogSettings')->willReturn([
			[
				'name' => 'account',
				'friendly_name' => 'Conta',
				'enabled' => true,
				'requirement' => 'required',
				'signatureMethods' => [],
			],
			[
				'name' => 'email',
				'friendly_name' => 'Email',
				'enabled' => true,
				'requirement' => 'required',
				'signatureMethods' => [],
			],
		]);
		$identifyMethodService->method('getFriendlyNamesMap')->willReturn([
			'account' => 'Conta',
			'email' => 'Email',
		]);

		$normalized = IdentifyMethodsPolicyValue::normalize([
			'factors' => [
				[
					'name' => 'account',
					'friendly_name' => 'Account',
					'signatureMethods' => [],
				],
				[
					'name' => 'email',
					'signatureMethods' => [],
				],
			],
		], $identifyMethodService);

		self::assertCount(2, $normalized['factors']);
		self::assertSame('Account', $normalized['factors'][0]['friendly_name']);
		self::assertSame('Email', $normalized['factors'][1]['friendly_name']);
	}

	public function testEnrichesExplicitFactorsFromCatalogWithoutAddingMissingFactors(): void {
		$identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$identifyMethodService->expects($this->never())
			->method('getIdentifyMethodsSettings');
		$identifyMethodService->expects($this->once())
			->method('getIdentifyMethodsCatalogSettings')
			->willReturn([
				[
					'name' => 'account',
					'friendly_name' => 'Account',
					'enabled' => true,
					'requirement' => 'required',
					'signatureMethods' => [
						'password' => [
							'enabled' => true,
							'label' => 'Certificate with password',
						],
					],
				],
				[
					'name' => 'email',
					'friendly_name' => 'Email',
					'enabled' => false,
					'requirement' => 'required',
					'signatureMethods' => [
						'emailToken' => [
							'enabled' => true,
							'label' => 'Email code',
						],
					],
				],
			]);
		$identifyMethodService->method('getFriendlyNamesMap')->willReturn([
			'account' => 'Account',
			'email' => 'Email',
		]);

		$normalized = IdentifyMethodsPolicyValue::normalize([
			'factors' => [
				[
					'name' => 'account',
					'enabled' => true,
					'signatureMethods' => [
						'password' => [
							'enabled' => true,
						],
					],
					'signatureMethodEnabled' => 'password',
				],
			],
		], $identifyMethodService);

		self::assertSame([
			'factors' => [
				[
					'name' => 'account',
					'enabled' => true,
					'signatureMethods' => [
						'password' => [
							'enabled' => true,
							'label' => 'Certificate with password',
							'name' => 'Certificate with password',
						],
					],
					'friendly_name' => 'Account',
					'requirement' => 'required',
					'signatureMethodEnabled' => 'password',
				],
			],
		], $normalized);
	}
}
