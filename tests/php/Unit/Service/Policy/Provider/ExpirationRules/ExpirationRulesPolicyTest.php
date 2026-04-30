<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\ExpirationRules;

use OCA\Libresign\Service\Policy\Provider\ExpirationRules\ExpirationRulesPolicy;
use PHPUnit\Framework\TestCase;

final class ExpirationRulesPolicyTest extends TestCase {
	public function testProviderExposesAllExpirationKeys(): void {
		$provider = new ExpirationRulesPolicy();
		$this->assertSame([
			ExpirationRulesPolicy::KEY_MAXIMUM_VALIDITY,
			ExpirationRulesPolicy::KEY_RENEWAL_INTERVAL,
			ExpirationRulesPolicy::KEY_EXPIRY_IN_DAYS,
		], $provider->keys());
	}

	public function testNormalizesMaximumValidityToNonNegativeInteger(): void {
		$provider = new ExpirationRulesPolicy();
		$definition = $provider->get(ExpirationRulesPolicy::KEY_MAXIMUM_VALIDITY);

		$this->assertSame(600, $definition->normalizeValue('600'));
		$this->assertSame(0, $definition->normalizeValue(-10));
	}

	public function testNormalizesRenewalIntervalToNonNegativeInteger(): void {
		$provider = new ExpirationRulesPolicy();
		$definition = $provider->get(ExpirationRulesPolicy::KEY_RENEWAL_INTERVAL);

		$this->assertSame(1200, $definition->normalizeValue(1200));
		$this->assertSame(0, $definition->normalizeValue('-5'));
	}

	public function testNormalizesExpiryInDaysToPositiveInteger(): void {
		$provider = new ExpirationRulesPolicy();
		$definition = $provider->get(ExpirationRulesPolicy::KEY_EXPIRY_IN_DAYS);

		$this->assertSame(90, $definition->normalizeValue('90'));
		$this->assertSame(ExpirationRulesPolicy::DEFAULT_EXPIRY_IN_DAYS, $definition->normalizeValue(0));
	}
}
