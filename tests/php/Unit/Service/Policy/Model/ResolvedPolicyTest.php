<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Model;

use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
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
			'inheritedValue' => null,
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

	/**
	 * @dataProvider providerGetEffectiveValueAsBool
	 */
	public function testGetEffectiveValueAsBool(mixed $effectiveValue, bool $expected, bool $default = false): void {
		$policy = (new ResolvedPolicy())
			->setEffectiveValue($effectiveValue);

		$this->assertSame($expected, $policy->getEffectiveValueAsBool($default));
	}

	/** @return array<string, array{0: mixed, 1: bool, 2?: bool}> */
	public static function providerGetEffectiveValueAsBool(): array {
		return [
			'bool true' => [true, true],
			'bool false' => [false, false],
			'string true' => ['true', true],
			'string false' => ['false', false],
			'int 1' => [1, true],
			'int 0' => [0, false],
			'float non zero' => [0.5, true],
			'float zero' => [0.0, false],
			'invalid string uses default true' => ['not-bool', true, true],
			'null uses default false' => [null, false],
			'array uses default true' => [['unexpected'], true, true],
		];
	}
}
