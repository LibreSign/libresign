<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\ObserverProfile;

use OCA\Libresign\Service\Policy\Provider\ObserverProfile\ObserverProfilePolicy;
use PHPUnit\Framework\TestCase;

final class ObserverProfilePolicyTest extends TestCase {
	public function testProviderBuildsObserverProfileDefinition(): void {
		$provider = new ObserverProfilePolicy();
		$this->assertSame([ObserverProfilePolicy::KEY], $provider->keys());

		$definition = $provider->get(ObserverProfilePolicy::KEY);
		$this->assertSame(ObserverProfilePolicy::KEY, $definition->key());
		$this->assertFalse($definition->normalizeValue(0));
		$this->assertTrue($definition->normalizeValue(1));
	}
}
