<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\DocMdp;

use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Provider\DocMdp\DocMdpPolicy;
use PHPUnit\Framework\TestCase;

final class DocMdpPolicyTest extends TestCase {
	public function testProviderBuildsDocMdpDefinition(): void {
		$provider = new DocMdpPolicy();
		$this->assertSame([DocMdpPolicy::KEY], $provider->keys());
		$definition = $provider->get(DocMdpPolicy::KEY);

		$this->assertSame(DocMdpPolicy::KEY, $definition->key());
		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $definition->defaultSystemValue());
		$this->assertSame([0, 1, 2, 3], $definition->allowedValues(new PolicyContext()));
	}

	public function testProviderNormalizesDocMdpLevelValues(): void {
		$provider = new DocMdpPolicy();
		$definition = $provider->get(DocMdpPolicy::KEY);

		$this->assertSame(2, $definition->normalizeValue(2));
		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS->value, $definition->normalizeValue(DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS));
	}
}
