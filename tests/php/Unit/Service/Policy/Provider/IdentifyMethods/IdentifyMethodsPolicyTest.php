<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\IdentifyMethods;

use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\IdentifyMethodsPolicy;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class IdentifyMethodsPolicyTest extends TestCase {
	private IdentifyMethodService&MockObject $identifyMethodService;

	public function setUp(): void {
		parent::setUp();
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
	}

	public function testProviderBuildsIdentifyMethodsDefinition(): void {
		$provider = new IdentifyMethodsPolicy($this->identifyMethodService);
		$this->assertSame([IdentifyMethodsPolicy::KEY], $provider->keys());

		$definition = $provider->get(IdentifyMethodsPolicy::KEY);
		$this->assertSame(IdentifyMethodsPolicy::KEY, $definition->key());
		$this->assertSame([], $definition->defaultSystemValue());
	}

	public function testProviderNormalizesIdentifyMethodsPayload(): void {
		$provider = new IdentifyMethodsPolicy($this->identifyMethodService);
		$definition = $provider->get(IdentifyMethodsPolicy::KEY);

		$normalized = $definition->normalizeValue([
			[
				'name' => 'email',
				'friendly_name' => 'Email',
				'enabled' => 1,
				'can_create_account' => '0',
				'signatureMethods' => [
					'email' => [
						'enabled' => true,
						'label' => 'Email token',
					],
					'clickToSign' => [
						'enabled' => false,
					],
				],
			],
		]);

		$this->assertSame([
			'factors' => [
				[
					'name' => 'email',
					'enabled' => true,
					'signatureMethods' => [
						'email' => [
							'enabled' => true,
							'label' => 'Email token',
						],
						'clickToSign' => [
							'enabled' => false,
						],
					],
					'friendly_name' => 'Email',
				],
			],
			'can_create_account' => false,
		], $normalized);
	}

	public function testProviderReturnsDefaultFactorsWhenPayloadIsEmpty(): void {
		$this->identifyMethodService
			->method('getFriendlyNamesMap')
			->willReturn([
				'account' => 'Account',
				'email' => 'Email',
			]);
		$this->identifyMethodService
			->method('getDefaultIdentifyMethodsPolicy')
			->willReturn([
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

		$provider = new IdentifyMethodsPolicy($this->identifyMethodService);
		$definition = $provider->get(IdentifyMethodsPolicy::KEY);

		$normalized = $definition->normalizeValue([]);

		$this->assertCount(2, $normalized['factors']);
		$this->assertSame('account', $normalized['factors'][0]['name']);
		$this->assertSame('email', $normalized['factors'][1]['name']);
		$this->assertArrayNotHasKey('can_create_account', $normalized);
	}
}
