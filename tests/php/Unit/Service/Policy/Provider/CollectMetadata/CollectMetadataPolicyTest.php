<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\CollectMetadata;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Provider\CollectMetadata\CollectMetadataPolicy;
use PHPUnit\Framework\TestCase;

final class CollectMetadataPolicyTest extends TestCase {
	public function testProviderBuildsCollectMetadataDefinition(): void {
		$provider = new CollectMetadataPolicy();
		$this->assertSame([CollectMetadataPolicy::KEY], $provider->keys());
		$definition = $provider->get(CollectMetadataPolicy::KEY);

		$this->assertSame(CollectMetadataPolicy::KEY, $definition->key());
		$this->assertFalse($definition->defaultSystemValue());
		$this->assertSame([false, true], $definition->allowedValues(new PolicyContext()));
	}

	public function testProviderNormalizesCollectMetadataBooleanInputs(): void {
		$provider = new CollectMetadataPolicy();
		$definition = $provider->get(CollectMetadataPolicy::KEY);

		$this->assertTrue($definition->normalizeValue('1'));
		$this->assertTrue($definition->normalizeValue('true'));
		$this->assertFalse($definition->normalizeValue('0'));
		$this->assertFalse($definition->normalizeValue('false'));
		$this->assertFalse($definition->normalizeValue('unexpected-value'));
	}
}
