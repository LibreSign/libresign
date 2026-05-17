<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\ApprovalGroups;

use OCA\Libresign\Service\Policy\Provider\ApprovalGroups\ApprovalGroupsPolicy;
use OCA\Libresign\Service\Policy\Provider\ApprovalGroups\ApprovalGroupsPolicyValue;
use PHPUnit\Framework\TestCase;

final class ApprovalGroupsPolicyTest extends TestCase {
	public function testDefinitionMetadata(): void {
		$provider = new ApprovalGroupsPolicy();
		$definition = $provider->get(ApprovalGroupsPolicy::KEY);

		$this->assertSame(ApprovalGroupsPolicy::KEY, $definition->key());
		$this->assertSame(
			ApprovalGroupsPolicyValue::encode(ApprovalGroupsPolicyValue::DEFAULT_GROUPS),
			$definition->defaultSystemValue(),
		);
		$this->assertSame([], $definition->allowedValues(new \OCA\Libresign\Service\Policy\Model\PolicyContext()));
	}

	public function testNormalizeValueEncodesGroupsList(): void {
		$provider = new ApprovalGroupsPolicy();
		$definition = $provider->get(ApprovalGroupsPolicy::KEY);

		$this->assertSame('["admin","finance"]', $definition->normalizeValue(['finance', 'admin']));
	}

	public function testValidateRejectsEmptyList(): void {
		$this->expectException(\InvalidArgumentException::class);

		$provider = new ApprovalGroupsPolicy();
		$definition = $provider->get(ApprovalGroupsPolicy::KEY);
		$definition->validateValue('[]', new \OCA\Libresign\Service\Policy\Model\PolicyContext());
	}
}
