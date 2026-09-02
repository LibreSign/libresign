<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\SignerGeolocation;

use OCA\Libresign\Enum\SignerGeolocationMode;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Provider\SignerGeolocation\SignerGeolocationPolicy;
use OCA\Libresign\Service\Policy\Provider\SignerGeolocation\SignerGeolocationPolicyValue;
use PHPUnit\Framework\TestCase;

final class SignerGeolocationPolicyTest extends TestCase {
	public function testProviderBuildsSignerGeolocationDefinition(): void {
		$provider = new SignerGeolocationPolicy();
		$this->assertSame([SignerGeolocationPolicy::KEY], $provider->keys());
		$definition = $provider->get(SignerGeolocationPolicy::KEY);

		$this->assertSame(SignerGeolocationPolicy::KEY, $definition->key());
		$this->assertSame(SignerGeolocationPolicyValue::defaults(), $definition->defaultSystemValue());
	}

	public function testProviderNormalizesSignerGeolocationInputs(): void {
		$provider = new SignerGeolocationPolicy();
		$definition = $provider->get(SignerGeolocationPolicy::KEY);

		$this->assertSame([
			'mode' => SignerGeolocationMode::REQUIRED->value,
		], $definition->normalizeValue([
			'mode' => 'required',
		]));
	}

	public function testProviderSupportsDelegatedGroupAdminOverlays(): void {
		$provider = new SignerGeolocationPolicy();
		$definition = $provider->get(SignerGeolocationPolicy::KEY);

		$this->assertTrue($definition->supportsGroupAdminDelegation());
		$this->assertSame([], $definition->allowedValues(new PolicyContext()));
	}

	public function testProviderRejectsInvalidMode(): void {
		$provider = new SignerGeolocationPolicy();
		$definition = $provider->get(SignerGeolocationPolicy::KEY);

		$this->expectException(\InvalidArgumentException::class);
		$definition->validateValue([
			'mode' => 'invalid',
		], new PolicyContext());
	}

	public function testProviderRejectsMissingMode(): void {
		$provider = new SignerGeolocationPolicy();
		$definition = $provider->get(SignerGeolocationPolicy::KEY);

		$this->expectException(\InvalidArgumentException::class);
		$definition->validateValue([], new PolicyContext());
	}
}
