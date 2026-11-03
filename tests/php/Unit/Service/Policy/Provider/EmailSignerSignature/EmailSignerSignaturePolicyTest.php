<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\EmailSignerSignature;

use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Provider\EmailSignerSignature\EmailSignerSignaturePolicy;
use PHPUnit\Framework\TestCase;

final class EmailSignerSignaturePolicyTest extends TestCase {
	public function testProviderBuildsDisabledBySystemOnlyDefinition(): void {
		$provider = new EmailSignerSignaturePolicy();
		$this->assertSame([EmailSignerSignaturePolicy::KEY], $provider->keys());

		$definition = $provider->get(EmailSignerSignaturePolicy::KEY);
		$this->assertSame(EmailSignerSignaturePolicy::KEY, $definition->key());
		$this->assertFalse($definition->defaultSystemValue());
		$this->assertSame([PolicySpec::SCOPE_SYSTEM], $definition->supportedScopes());
		$this->assertFalse($definition->supportsUserPreference());
		$this->assertFalse($definition->supportsRequestOverride());
		$this->assertFalse($definition->supportsGroupAdminDelegation());
		$this->assertTrue($definition->normalizeValue(1));
		$this->assertFalse($definition->normalizeValue(0));
		$this->assertFalse($definition->normalizeValue(''));
	}

	public function testProviderRejectsUnknownKey(): void {
		$this->expectException(\InvalidArgumentException::class);
		(new EmailSignerSignaturePolicy())->get('unknown');
	}
}
