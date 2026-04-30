<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\IdentifyMethods;

use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\IdentifyMethodsPolicy;
use PHPUnit\Framework\TestCase;

final class IdentifyMethodsPolicyTest extends TestCase {
	public function testProviderBuildsIdentifyMethodsDefinition(): void {
		$provider = new IdentifyMethodsPolicy();
		$this->assertSame([IdentifyMethodsPolicy::KEY], $provider->keys());

		$definition = $provider->get(IdentifyMethodsPolicy::KEY);
		$this->assertSame(IdentifyMethodsPolicy::KEY, $definition->key());
		$this->assertSame([], $definition->defaultSystemValue());
	}

	public function testProviderNormalizesIdentifyMethodsPayload(): void {
		$provider = new IdentifyMethodsPolicy();
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
				'can_create_account' => false,
			],
		], $normalized);
	}
}
