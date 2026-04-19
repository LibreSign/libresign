<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Signature;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Provider\Signature\SignatureFlowPolicy;
use PHPUnit\Framework\TestCase;

final class SignatureFlowPolicyTest extends TestCase {
	public function testProviderBuildsSignatureFlowDefinition(): void {
		$provider = new SignatureFlowPolicy();
		$this->assertSame([SignatureFlowPolicy::KEY], $provider->keys());
		$definition = $provider->get(SignatureFlowPolicy::KEY);

		$this->assertSame(SignatureFlowPolicy::KEY, $definition->key());
		$this->assertSame('none', $definition->defaultSystemValue());
		$this->assertSame(['none', 'parallel', 'ordered_numeric'], $definition->allowedValues(new PolicyContext()));
		$this->assertSame('ordered_numeric', $definition->normalizeValue('ordered_numeric'));
	}
}
