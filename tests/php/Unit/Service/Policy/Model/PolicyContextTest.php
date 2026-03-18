<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Model;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use PHPUnit\Framework\TestCase;

final class PolicyContextTest extends TestCase {
	public function testGettersReturnDefaults(): void {
		$context = new PolicyContext();

		$this->assertNull($context->getUserId());
		$this->assertSame([], $context->getGroups());
		$this->assertSame([], $context->getCircles());
		$this->assertNull($context->getActiveContext());
		$this->assertSame([], $context->getRequestOverrides());
		$this->assertSame([], $context->getActorCapabilities());
	}

	public function testSettersStoreValues(): void {
		$context = new PolicyContext();
		$activeContext = [
			'type' => 'group',
			'id' => 'finance',
		];

		$context
			->setUserId('john')
			->setGroups(['finance', 'legal'])
			->setCircles(['board'])
			->setActiveContext($activeContext)
			->setRequestOverrides(['signature_flow' => 'parallel'])
			->setActorCapabilities(['canManageOrganizationPolicies' => true]);

		$this->assertSame('john', $context->getUserId());
		$this->assertSame(['finance', 'legal'], $context->getGroups());
		$this->assertSame(['board'], $context->getCircles());
		$this->assertSame($activeContext, $context->getActiveContext());
		$this->assertSame(['signature_flow' => 'parallel'], $context->getRequestOverrides());
		$this->assertSame(['canManageOrganizationPolicies' => true], $context->getActorCapabilities());
	}

	public function testFromUserIdCreatesContextWithUserId(): void {
		$context = PolicyContext::fromUserId('john');

		$this->assertSame('john', $context->getUserId());
		$this->assertSame([], $context->getGroups());
	}
}
