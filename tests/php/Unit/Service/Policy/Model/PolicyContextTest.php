<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Model;

use OCA\Libresign\Service\Policy\Model\ActiveGroupScope;
use OCA\Libresign\Service\Policy\Model\ActorRole;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use PHPUnit\Framework\TestCase;

final class PolicyContextTest extends TestCase {
	public function testGettersReturnDefaults(): void {
		$context = new PolicyContext();

		$this->assertNull($context->getUserId());
		$this->assertSame([], $context->getGroups());
		$this->assertSame([], $context->getCircles());
		$this->assertNull($context->getActiveGroupScope());
		$this->assertSame([], $context->getRequestOverrides());
		$role = $context->getActorRole();
		$this->assertFalse($role->canManageSystemPolicies);
		$this->assertFalse($role->canManageGroupPolicies);
		$this->assertSame(0, $role->manageableGroupCount);
	}

	public function testSettersStoreValues(): void {
		$context = new PolicyContext();
		$activeGroupScope = new ActiveGroupScope('finance');

		$context
			->setUserId('john')
			->setGroups(['finance', 'legal'])
			->setCircles(['board'])
			->setActiveGroupScope($activeGroupScope)
			->setRequestOverrides(['signature_flow' => 'parallel'])
			->setActorRole(ActorRole::systemAdmin());

		$this->assertSame('john', $context->getUserId());
		$this->assertSame(['finance', 'legal'], $context->getGroups());
		$this->assertSame(['board'], $context->getCircles());
		$this->assertSame($activeGroupScope, $context->getActiveGroupScope());
		$this->assertSame('finance', $context->getActiveGroupScope()?->groupId);
		$this->assertSame(['signature_flow' => 'parallel'], $context->getRequestOverrides());
		$role = $context->getActorRole();
		$this->assertTrue($role->canManageSystemPolicies);
		$this->assertTrue($role->canManageGroupPolicies);
	}

	public function testFromUserIdCreatesContextWithUserId(): void {
		$context = PolicyContext::fromUserId('john');

		$this->assertSame('john', $context->getUserId());
		$this->assertSame([], $context->getGroups());
	}
}
