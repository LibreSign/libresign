<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\LegalInformation;

use OCA\Libresign\Service\Policy\Provider\LegalInformation\LegalInformationPolicy;
use PHPUnit\Framework\TestCase;

final class LegalInformationPolicyTest extends TestCase {
	public function testProviderBuildsDefinition(): void {
		$provider = new LegalInformationPolicy();
		$this->assertSame([LegalInformationPolicy::KEY], $provider->keys());

		$definition = $provider->get(LegalInformationPolicy::KEY);
		$this->assertSame(LegalInformationPolicy::KEY, $definition->key());
		$this->assertSame('', $definition->defaultSystemValue());
	}

	public function testNormalizesMarkdownAsString(): void {
		$provider = new LegalInformationPolicy();
		$definition = $provider->get(LegalInformationPolicy::KEY);

		$this->assertSame('**Legal** _terms_', $definition->normalizeValue('**Legal** _terms_'));
		$this->assertSame('42', $definition->normalizeValue(42));
	}
}
