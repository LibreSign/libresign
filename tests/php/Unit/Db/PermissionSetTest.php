<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Db;

use OCA\Libresign\Db\PermissionSet;
use PHPUnit\Framework\TestCase;

final class PermissionSetTest extends TestCase {
	public function testIsEnabledReturnsTrueByDefault(): void {
		$permissionSet = new PermissionSet();

		$this->assertTrue($permissionSet->isEnabled());
		$this->assertSame([], $permissionSet->getDecodedPolicyJson());
	}

	public function testSetEnabledStoresBooleanAsFlag(): void {
		$permissionSet = new PermissionSet();

		$permissionSet->setEnabled(false);
		$this->assertFalse($permissionSet->isEnabled());

		$permissionSet->setEnabled(true);
		$this->assertTrue($permissionSet->isEnabled());
	}

	public function testSetCreatedAtAndUpdatedAtAcceptStrings(): void {
		$permissionSet = new PermissionSet();

		$permissionSet->setCreatedAt('2026-03-17 10:00:00');
		$permissionSet->setUpdatedAt('2026-03-17 11:00:00');

		$this->assertInstanceOf(\DateTime::class, $permissionSet->getCreatedAt());
		$this->assertInstanceOf(\DateTime::class, $permissionSet->getUpdatedAt());
		$this->assertSame('2026-03-17 10:00:00', $permissionSet->getCreatedAt()->format('Y-m-d H:i:s'));
		$this->assertSame('2026-03-17 11:00:00', $permissionSet->getUpdatedAt()->format('Y-m-d H:i:s'));
	}

	public function testPolicyJsonStoresStructuredPolicyData(): void {
		$permissionSet = new PermissionSet();
		$policyJson = [
			'signature_flow' => [
				'defaultValue' => ['type' => 'parallel'],
				'allowChildOverride' => true,
			],
		];

		$permissionSet->setPolicyJson($policyJson);

		$this->assertSame($policyJson, $permissionSet->getDecodedPolicyJson());
		$this->assertSame(json_encode($policyJson, JSON_THROW_ON_ERROR), $permissionSet->getPolicyJson());
	}

	public function testPolicyJsonIsDecodedWhenHydratingFromDatabaseRow(): void {
		$permissionSet = PermissionSet::fromRow([
			'id' => 7,
			'name' => 'finance',
			'description' => null,
			'scope_type' => 'organization',
			'enabled' => 1,
			'priority' => 10,
			'policy_json' => '{"signature_flow":{"defaultValue":"parallel","allowChildOverride":true}}',
			'created_at' => '2026-03-17 10:00:00',
			'updated_at' => '2026-03-17 11:00:00',
		]);

		$this->assertSame([
			'signature_flow' => [
				'defaultValue' => 'parallel',
				'allowChildOverride' => true,
			],
		], $permissionSet->getDecodedPolicyJson());
	}

	public function testPolicyJsonFallsBackToEmptyArrayWhenLegacyValueIsInvalid(): void {
		$permissionSet = PermissionSet::fromRow([
			'id' => 8,
			'name' => 'finance',
			'description' => null,
			'scope_type' => 'organization',
			'enabled' => 1,
			'priority' => 10,
			'policy_json' => 'Array',
			'created_at' => '2026-03-17 10:00:00',
			'updated_at' => '2026-03-17 11:00:00',
		]);

		$this->assertSame([], $permissionSet->getDecodedPolicyJson());
	}
}
