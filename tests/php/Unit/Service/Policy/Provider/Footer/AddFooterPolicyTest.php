<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Footer;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Provider\Footer\AddFooterPolicy;
use PHPUnit\Framework\TestCase;

final class AddFooterPolicyTest extends TestCase {
	public function testProviderBuildsAddFooterDefinition(): void {
		$provider = new AddFooterPolicy();
		$this->assertSame([AddFooterPolicy::KEY], $provider->keys());
		$definition = $provider->get(AddFooterPolicy::KEY);

		$this->assertSame(AddFooterPolicy::KEY, $definition->key());
		$this->assertTrue($definition->defaultSystemValue());
		$this->assertSame([true, false], $definition->allowedValues(new PolicyContext()));
	}

	public function testProviderNormalizesBooleanLikeValues(): void {
		$provider = new AddFooterPolicy();
		$definition = $provider->get(AddFooterPolicy::KEY);

		$this->assertTrue($definition->normalizeValue(true));
		$this->assertFalse($definition->normalizeValue(false));
		$this->assertTrue($definition->normalizeValue('1'));
		$this->assertFalse($definition->normalizeValue('0'));
		$this->assertTrue($definition->normalizeValue('true'));
		$this->assertFalse($definition->normalizeValue('false'));
	}
}
