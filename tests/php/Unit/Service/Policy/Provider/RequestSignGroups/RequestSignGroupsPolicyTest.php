<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\RequestSignGroups;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicy;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicyValue;
use PHPUnit\Framework\TestCase;

final class RequestSignGroupsPolicyTest extends TestCase {
	public function testProviderBuildsGroupsRequestSignDefinition(): void {
		$provider = new RequestSignGroupsPolicy();
		$this->assertSame([RequestSignGroupsPolicy::KEY], $provider->keys());
		$definition = $provider->get(RequestSignGroupsPolicy::KEY);

		$this->assertSame(RequestSignGroupsPolicy::KEY, $definition->key());
		$this->assertSame(
			RequestSignGroupsPolicyValue::encode(RequestSignGroupsPolicyValue::DEFAULT_GROUPS),
			$definition->defaultSystemValue(),
		);
		$this->assertSame([], $definition->allowedValues(new PolicyContext()));
	}

	public function testProviderNormalizesGroupListPayload(): void {
		$provider = new RequestSignGroupsPolicy();
		$definition = $provider->get(RequestSignGroupsPolicy::KEY);

		$this->assertSame(
			'["admin","finance"]',
			$definition->normalizeValue([' finance ', 'admin', 'finance']),
		);
		$this->assertSame(
			'["admin","legal"]',
			$definition->normalizeValue('["legal", "admin"]'),
		);
	}
}
