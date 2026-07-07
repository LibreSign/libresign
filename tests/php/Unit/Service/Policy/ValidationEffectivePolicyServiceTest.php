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

	public function testAppendEffectivePoliciesPrefersLegalInformationSnapshot(): void {
		$this->policyService
			->expects($this->once())
			->method('resolveKnownPolicyStatesForUserId')
			->with('admin')
			->willReturn([
				LegalInformationPolicy::KEY => [
					'policyKey' => LegalInformationPolicy::KEY,
					'effectiveValue' => 'Current requester copy',
					'inheritedValue' => null,
					'sourceScope' => 'user_policy',
					'visible' => true,
					'editableByCurrentActor' => true,
					'allowedValues' => [],
					'canSaveAsUserDefault' => true,
					'canUseAsRequestOverride' => true,
					'preferenceWasCleared' => false,
					'blockedBy' => null,
					'groupCount' => 0,
					'userCount' => 0,
					'everyoneCount' => 0,
				],
			]);

		$payload = [
			'requested_by' => ['userId' => 'admin'],
			'metadata' => [
				'policy_snapshot' => [
					LegalInformationPolicy::KEY => [
						'effectiveValue' => 'Snapshot legal copy',
						'sourceScope' => 'group',
					],
				],
			],
		];

		$result = $this->service->appendEffectivePolicies($payload);

		$this->assertSame('Snapshot legal copy', $result['effective_policies']['policies'][LegalInformationPolicy::KEY]['effectiveValue']);
		$this->assertSame('group', $result['effective_policies']['policies'][LegalInformationPolicy::KEY]['sourceScope']);
	}

	public function testAppendEffectivePoliciesKeepsResolvedLegalInformationWhenSnapshotMissing(): void {
		$this->policyService
			->expects($this->once())
			->method('resolveKnownPolicyStatesForUserId')
			->with('admin')
			->willReturn([
				LegalInformationPolicy::KEY => [
					'policyKey' => LegalInformationPolicy::KEY,
					'effectiveValue' => 'Current requester copy',
					'inheritedValue' => null,
					'sourceScope' => 'user_policy',
					'visible' => true,
					'editableByCurrentActor' => true,
					'allowedValues' => [],
					'canSaveAsUserDefault' => true,
					'canUseAsRequestOverride' => true,
					'preferenceWasCleared' => false,
					'blockedBy' => null,
					'groupCount' => 0,
					'userCount' => 0,
					'everyoneCount' => 0,
				],
			]);

		$payload = [
			'requested_by' => ['userId' => 'admin'],
			'metadata' => [],
		];

		$result = $this->service->appendEffectivePolicies($payload);

		$this->assertSame('Current requester copy', $result['effective_policies']['policies'][LegalInformationPolicy::KEY]['effectiveValue']);
		$this->assertSame('user_policy', $result['effective_policies']['policies'][LegalInformationPolicy::KEY]['sourceScope']);
	}

	public function testAppendEffectivePoliciesUsesCurrentUserResolutionWhenRequesterIsMissing(): void {
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

		$this->assertSame('System legal copy', $result['effective_policies']['policies'][LegalInformationPolicy::KEY]['effectiveValue']);
		$this->assertSame('system', $result['effective_policies']['policies'][LegalInformationPolicy::KEY]['sourceScope']);
	}
}
