<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy;

use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\LegalInformation\LegalInformationPolicy;
use OCA\Libresign\Service\Policy\ValidationEffectivePolicyService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ValidationEffectivePolicyServiceTest extends TestCase {
	private PolicyService&MockObject $policyService;
	private ValidationEffectivePolicyService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->policyService = $this->createMock(PolicyService::class);
		$this->service = new ValidationEffectivePolicyService($this->policyService);
	}

	public function testAppendEffectivePoliciesPrefersSnapshotAndProjectsPublicFields(): void {
		$this->policyService
			->expects($this->once())
			->method('resolveKnownPolicyStatesForUserIdWithoutUserScope')
			->with('admin')
			->willReturn([
				LegalInformationPolicy::KEY => [
					'policyKey' => LegalInformationPolicy::KEY,
					'effectiveValue' => 'Current inherited copy',
					'inheritedValue' => null,
					'sourceScope' => 'group',
					'visible' => true,
					'editableByCurrentActor' => false,
					'allowedValues' => [],
					'canSaveAsUserDefault' => false,
					'canUseAsRequestOverride' => false,
					'preferenceWasCleared' => false,
					'blockedBy' => null,
					'groupCount' => 1,
					'userCount' => 2,
					'everyoneCount' => 3,
				],
				'private_policy' => [
					'policyKey' => 'private_policy',
					'effectiveValue' => 'must not be exposed',
					'sourceScope' => 'group',
					'visible' => true,
					'editableByCurrentActor' => false,
					'allowedValues' => [],
					'canSaveAsUserDefault' => false,
					'canUseAsRequestOverride' => false,
					'preferenceWasCleared' => false,
					'blockedBy' => null,
					'groupCount' => 1,
					'userCount' => 2,
					'everyoneCount' => 3,
				],
			]);

		$payload = [
			'requested_by' => ['userId' => 'admin'],
			'metadata' => [
				'policy_snapshot' => [
					LegalInformationPolicy::KEY => [
						'effectiveValue' => 'Snapshot legal copy',
						'sourceScope' => 'user_policy',
					],
					'private_policy' => [
						'effectiveValue' => 'private snapshot value',
						'sourceScope' => 'system',
					],
				],
			],
			'files' => [
				[
					'metadata' => [
						'policy_snapshot' => [
							'private_policy' => [
								'effectiveValue' => 'nested private snapshot value',
								'sourceScope' => 'system',
							],
						],
					],
				],
			],
		];

		$result = $this->service->appendEffectivePolicies($payload);

		$this->assertSame([
			LegalInformationPolicy::KEY => [
				'policyKey' => LegalInformationPolicy::KEY,
				'effectiveValue' => 'Snapshot legal copy',
			],
		], $result['effective_policies']['policies']);
		$this->assertArrayNotHasKey('policy_snapshot', $result['metadata']);
		$this->assertArrayNotHasKey('policy_snapshot', $result['files'][0]['metadata']);
	}

	public function testAppendEffectivePoliciesUsesRequesterResolutionWithoutUserScopeWhenSnapshotMissing(): void {
		$this->policyService
			->expects($this->once())
			->method('resolveKnownPolicyStatesForUserIdWithoutUserScope')
			->with('admin')
			->willReturn([
				LegalInformationPolicy::KEY => [
					'policyKey' => LegalInformationPolicy::KEY,
					'effectiveValue' => 'Inherited legal copy',
					'inheritedValue' => null,
					'sourceScope' => 'group',
					'visible' => true,
					'editableByCurrentActor' => false,
					'allowedValues' => [],
					'canSaveAsUserDefault' => false,
					'canUseAsRequestOverride' => false,
					'preferenceWasCleared' => false,
					'blockedBy' => null,
					'groupCount' => 1,
					'userCount' => 0,
					'everyoneCount' => 0,
				],
				'tsa_settings' => [
					'policyKey' => 'tsa_settings',
					'effectiveValue' => [
						'url' => 'https://tsa.internal.example',
						'username' => 'internal-user',
					],
					'sourceScope' => 'system',
					'visible' => true,
					'editableByCurrentActor' => false,
					'allowedValues' => [],
					'canSaveAsUserDefault' => false,
					'canUseAsRequestOverride' => false,
					'preferenceWasCleared' => false,
					'blockedBy' => null,
					'groupCount' => 0,
					'userCount' => 0,
					'everyoneCount' => 0,
				],
			]);

		$result = $this->service->appendEffectivePolicies([
			'requested_by' => ['userId' => 'admin'],
			'metadata' => [],
		]);

		$this->assertSame([
			LegalInformationPolicy::KEY => [
				'policyKey' => LegalInformationPolicy::KEY,
				'effectiveValue' => 'Inherited legal copy',
			],
		], $result['effective_policies']['policies']);
		$this->assertArrayNotHasKey(
			'tsa_settings',
			$result['effective_policies']['policies'],
		);
	}

	public function testAppendEffectivePoliciesUsesCurrentResolutionWhenRequesterIsMissing(): void {
		$this->policyService
			->expects($this->once())
			->method('resolveKnownPolicyStates')
			->willReturn([
				LegalInformationPolicy::KEY => [
					'policyKey' => LegalInformationPolicy::KEY,
					'effectiveValue' => 'System legal copy',
					'inheritedValue' => null,
					'sourceScope' => 'system',
					'visible' => true,
					'editableByCurrentActor' => false,
					'allowedValues' => [],
					'canSaveAsUserDefault' => false,
					'canUseAsRequestOverride' => false,
					'preferenceWasCleared' => false,
					'blockedBy' => null,
					'groupCount' => 0,
					'userCount' => 0,
					'everyoneCount' => 0,
				],
			]);

		$result = $this->service->appendEffectivePolicies(['metadata' => []]);

		$this->assertSame([
			LegalInformationPolicy::KEY => [
				'policyKey' => LegalInformationPolicy::KEY,
				'effectiveValue' => 'System legal copy',
			],
		], $result['effective_policies']['policies']);
	}
}
