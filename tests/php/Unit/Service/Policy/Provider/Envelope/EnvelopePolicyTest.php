<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Envelope;

use OCA\Libresign\Service\Policy\Provider\Envelope\EnvelopePolicy;
use PHPUnit\Framework\TestCase;

final class EnvelopePolicyTest extends TestCase {
	public function testProviderBuildsEnvelopeDefinition(): void {
		$provider = new EnvelopePolicy();
		$this->assertSame([EnvelopePolicy::KEY], $provider->keys());

		$definition = $provider->get(EnvelopePolicy::KEY);
		$this->assertSame(EnvelopePolicy::KEY, $definition->key());
		$this->assertTrue($definition->normalizeValue(true));
		$this->assertFalse($definition->normalizeValue(false));
		$this->assertFalse($definition->normalizeValue('invalid'));
	}
}
