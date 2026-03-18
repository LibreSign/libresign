<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy;

use OCA\Libresign\Service\Policy\ResolvedPolicy;
use PHPUnit\Framework\TestCase;

final class ResolvedPolicyTest extends TestCase {
	public function testGettersReturnDefaults(): void {
		$policy = new ResolvedPolicy();

		$this->assertSame('', $policy->getPolicyKey());
		$this->assertNull($policy->getEffectiveValue());
		$this->assertSame('', $policy->getSourceScope());
		$this->assertFalse($policy->isVisible());
		$this->assertFalse($policy->isEditableByCurrentActor());
		$this->assertSame([], $policy->getAllowedValues());
		$this->assertFalse($policy->canSaveAsUserDefault());
		$this->assertFalse($policy->canUseAsRequestOverride());
		$this->assertFalse($policy->wasPreferenceCleared());
		$this->assertNull($policy->getBlockedBy());
	}

	public function testSettersStoreValues(): void {
		$policy = new ResolvedPolicy();
		$policy
			->setPolicyKey('signature_flow')
			->setEffectiveValue(['type' => 'parallel'])
			->setSourceScope('group')
			->setVisible(true)
			->setEditableByCurrentActor(true)
			->setAllowedValues([['type' => 'parallel'], ['type' => 'ordered_numeric']])
			->setCanSaveAsUserDefault(true)
			->setCanUseAsRequestOverride(true)
			->setPreferenceWasCleared(true)
			->setBlockedBy('system');

		$this->assertSame('signature_flow', $policy->getPolicyKey());
		$this->assertSame(['type' => 'parallel'], $policy->getEffectiveValue());
		$this->assertSame('group', $policy->getSourceScope());
		$this->assertTrue($policy->isVisible());
		$this->assertTrue($policy->isEditableByCurrentActor());
		$this->assertSame([['type' => 'parallel'], ['type' => 'ordered_numeric']], $policy->getAllowedValues());
		$this->assertTrue($policy->canSaveAsUserDefault());
		$this->assertTrue($policy->canUseAsRequestOverride());
		$this->assertTrue($policy->wasPreferenceCleared());
		$this->assertSame('system', $policy->getBlockedBy());
	}

	public function testToArrayExportsFrontendPayload(): void {
		$policy = (new ResolvedPolicy())
			->setPolicyKey('signature_flow')
			->setEffectiveValue('parallel')
			->setSourceScope('group')
			->setVisible(true)
			->setEditableByCurrentActor(true)
			->setAllowedValues(['parallel', 'ordered_numeric'])
			->setCanSaveAsUserDefault(true)
			->setCanUseAsRequestOverride(false)
			->setPreferenceWasCleared(true)
			->setBlockedBy('group');

		$this->assertSame([
			'policyKey' => 'signature_flow',
			'effectiveValue' => 'parallel',
			'sourceScope' => 'group',
			'visible' => true,
			'editableByCurrentActor' => true,
			'allowedValues' => ['parallel', 'ordered_numeric'],
			'canSaveAsUserDefault' => true,
			'canUseAsRequestOverride' => false,
			'preferenceWasCleared' => true,
			'blockedBy' => 'group',
		], $policy->toArray());
	}
}
