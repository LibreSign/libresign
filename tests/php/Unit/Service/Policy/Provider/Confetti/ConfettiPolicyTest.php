<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Confetti;

use OCA\Libresign\Service\Policy\Provider\Confetti\ConfettiPolicy;
use PHPUnit\Framework\TestCase;

final class ConfettiPolicyTest extends TestCase {
	public function testProviderBuildsConfettiDefinition(): void {
		$provider = new ConfettiPolicy();
		$this->assertSame([ConfettiPolicy::KEY], $provider->keys());

		$definition = $provider->get(ConfettiPolicy::KEY);
		$this->assertSame(ConfettiPolicy::KEY, $definition->key());
		$this->assertTrue($definition->normalizeValue(1));
		$this->assertFalse($definition->normalizeValue(0));
		$this->assertFalse($definition->normalizeValue(''));
	}
}
