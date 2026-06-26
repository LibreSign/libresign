<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\PolicyController;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyAuthorizationService;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\Confetti\ConfettiPolicy;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicy;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicyGuard;
use OCP\AppFramework\Http;
use OCP\Group\ISubAdmin;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PolicyControllerTest extends TestCase {
	private IRequest&MockObject $request;
	private IL10N&MockObject $l10n;
	private PolicyService&MockObject $policyService;
	private IUserSession&MockObject $userSession;
	private IGroupManager&MockObject $groupManager;
	private IUserManager&MockObject $userManager;
	private ISubAdmin&MockObject $subAdmin;
	private IUser&MockObject $currentUser;
	private RequestSignGroupsPolicyGuard $requestSignGroupsPolicyGuard;
	private PolicyAuthorizationService $policyAuthorizationService;
	private PolicyController $controller;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->request
			->method('getParam')
			->willReturnCallback(static fn (string $key, mixed $default = null): mixed => $default);
		$this->request
			->method('getParams')
			->willReturn([]);
		$this->l10n = $this->createMock(IL10N::class);
		$this->policyService = $this->createMock(PolicyService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->subAdmin = $this->createMock(ISubAdmin::class);
		$this->currentUser = $this->createMock(IUser::class);
		$this->currentUser
			->method('getUID')
			->willReturn('admin');
		$this->userSession
			->method('getUser')
			->willReturn($this->currentUser);
		$this->policyAuthorizationService = new PolicyAuthorizationService(
			$this->groupManager,
			$this->subAdmin,
			$this->policyService,
		);
		$this->requestSignGroupsPolicyGuard = new RequestSignGroupsPolicyGuard(
			$this->l10n,
			$this->userSession,
			$this->groupManager,
			$this->subAdmin,
			$this->policyAuthorizationService,
		);

		$this->controller = new PolicyController(
			$this->request,
			$this->l10n,
			$this->policyService,
			$this->requestSignGroupsPolicyGuard,
			$this->policyAuthorizationService,
			$this->userSession,
			$this->groupManager,
			$this->userManager,
			$this->subAdmin,
		);
	}

	public function testEffectiveReturnsResolvedSignatureFlowPolicy(): void {
		$this->policyService
			->expects($this->once())
			->method('resolveKnownPolicyStatesWithRuleCounts')
			->with([])
			->willReturn([
				'signature_flow' => [
					'policyKey' => 'signature_flow',
					'effectiveValue' => 'ordered_numeric',
					'inheritedValue' => null,
					'sourceScope' => 'group',
					'visible' => true,
					'editableByCurrentActor' => false,
					'allowedValues' => ['ordered_numeric'],
					'canSaveAsUserDefault' => false,
					'canUseAsRequestOverride' => false,
					'preferenceWasCleared' => false,
					'blockedBy' => 'group',
					'groupCount' => 0,
					'userCount' => 0,
					'everyoneCount' => 0,
				],
			]);

		$response = $this->controller->effective();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([
			'policies' => [
				'signature_flow' => [
					'policyKey' => 'signature_flow',
					'effectiveValue' => 'ordered_numeric',
					'inheritedValue' => null,
					'sourceScope' => 'group',
					'visible' => true,
					'editableByCurrentActor' => false,
					'allowedValues' => ['ordered_numeric'],
					'canSaveAsUserDefault' => false,
					'canUseAsRequestOverride' => false,
					'preferenceWasCleared' => false,
					'blockedBy' => 'group',
					'groupCount' => 0,
					'userCount' => 0,
					'everyoneCount' => 0,
				],
			],
		], $response->getData());
	}

	public function testEffectiveEmbedsSytemAdminRuleCounts(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$this->policyService
			->expects($this->once())
			->method('getAllRuleCounts')
			->willReturn($ruleCounts = [
				'signature_flow' => ['groupCount' => 3, 'userCount' => 7, 'everyoneCount' => 1],
			]);

		$this->policyService
			->expects($this->once())
			->method('resolveKnownPolicyStatesWithRuleCounts')
			->with($ruleCounts)
			->willReturn([
				'signature_flow' => [
					'policyKey' => 'signature_flow',
					'effectiveValue' => 'ordered_numeric',
					'inheritedValue' => null,
					'sourceScope' => 'system',
					'visible' => true,
					'editableByCurrentActor' => true,
					'allowedValues' => ['ordered_numeric'],
					'canSaveAsUserDefault' => false,
					'canUseAsRequestOverride' => false,
					'preferenceWasCleared' => false,
					'blockedBy' => null,
					'groupCount' => 3,
					'userCount' => 7,
					'everyoneCount' => 1,
				],
			]);

		$response = $this->controller->effective();

		$this->assertSame(3, $response->getData()['policies']['signature_flow']['groupCount']);
		$this->assertSame(7, $response->getData()['policies']['signature_flow']['userCount']);
		$this->assertSame(1, $response->getData()['policies']['signature_flow']['everyoneCount']);
	}

	public function testEffectiveEmbedsSubAdminRuleCountsForManagedGroupsOnly(): void {
		$this->groupManager->method('isAdmin')->with('admin')->willReturn(false);
		$this->subAdmin->method('isSubAdmin')->with($this->currentUser)->willReturn(true);
		$this->groupManager->method('getUserGroupIds')->with($this->currentUser)->willReturn(['finance']);

		$this->policyService
			->expects($this->once())
			->method('shouldFilterVisibleGroupCountsForCurrentActor')
			->with('signature_flow')
			->willReturn(false);

		$this->policyService
			->expects($this->once())
			->method('getRuleCounts')
			->with(['finance'], [])
			->willReturn($ruleCounts = ['signature_flow' => ['groupCount' => 1, 'userCount' => 0, 'everyoneCount' => 0]]);

		$this->policyService
			->expects($this->once())
			->method('resolveKnownPolicyStatesWithRuleCounts')
			->with($ruleCounts)
			->willReturn([
				'signature_flow' => [
					'policyKey' => 'signature_flow',
					'effectiveValue' => 'ordered_numeric',
					'inheritedValue' => null,
					'sourceScope' => 'group',
					'visible' => true,
					'editableByCurrentActor' => true,
					'allowedValues' => ['ordered_numeric'],
					'canSaveAsUserDefault' => false,
					'canUseAsRequestOverride' => false,
					'preferenceWasCleared' => false,
					'blockedBy' => null,
					'groupCount' => 1,
					'userCount' => 0,
					'everyoneCount' => 0,
				],
			]);

		$response = $this->controller->effective();

		$this->assertSame(1, $response->getData()['policies']['signature_flow']['groupCount']);
		$this->assertSame(0, $response->getData()['policies']['signature_flow']['userCount']);
		$this->assertSame(0, $response->getData()['policies']['signature_flow']['everyoneCount']);
	}

	public function testEffectiveHidesHiddenRequestAccessRuleCountsForSubAdmin(): void {
		$this->groupManager->method('isAdmin')->with('admin')->willReturn(false);
		$this->subAdmin->method('isSubAdmin')->with($this->currentUser)->willReturn(true);
		$this->groupManager->method('getUserGroupIds')->with($this->currentUser)->willReturn(['company', 'board']);
		$this->mockDelegatedRequestSignGroups(['company', 'board'], ['company', 'board']);

		$this->policyService
			->expects($this->exactly(2))
			->method('shouldFilterVisibleGroupCountsForCurrentActor')
			->willReturnCallback(static fn (string $policyKey): bool => $policyKey === RequestSignGroupsPolicy::KEY);

		$this->policyService
			->expects($this->once())
			->method('getRuleCounts')
			->with(['company', 'board'], [])
			->willReturn($rawRuleCounts = [
				RequestSignGroupsPolicy::KEY => ['groupCount' => 1, 'userCount' => 0, 'everyoneCount' => 0],
				'signature_flow' => ['groupCount' => 2, 'userCount' => 0, 'everyoneCount' => 0],
			]);

		$this->policyService
			->expects($this->once())
			->method('countVisibleGroupPoliciesForTargets')
			->with(RequestSignGroupsPolicy::KEY, ['company', 'board'])
			->willReturn(0);

		$this->policyService
			->expects($this->once())
			->method('resolveKnownPolicyStatesWithRuleCounts')
			->with([
				RequestSignGroupsPolicy::KEY => ['groupCount' => 0, 'userCount' => 0, 'everyoneCount' => 0],
				'signature_flow' => $rawRuleCounts['signature_flow'],
			])
			->willReturn([
				RequestSignGroupsPolicy::KEY => [
					'policyKey' => RequestSignGroupsPolicy::KEY,
					'effectiveValue' => '["board"]',
					'inheritedValue' => null,
					'sourceScope' => 'group',
					'visible' => true,
					'editableByCurrentActor' => true,
					'allowedValues' => [],
					'canSaveAsUserDefault' => false,
					'canUseAsRequestOverride' => false,
					'preferenceWasCleared' => false,
					'blockedBy' => null,
					'groupCount' => 0,
					'userCount' => 0,
					'everyoneCount' => 0,
				],
				'signature_flow' => [
					'policyKey' => 'signature_flow',
					'effectiveValue' => 'parallel',
					'inheritedValue' => null,
					'sourceScope' => 'system',
					'visible' => true,
					'editableByCurrentActor' => true,
					'allowedValues' => ['parallel'],
					'canSaveAsUserDefault' => false,
					'canUseAsRequestOverride' => false,
					'preferenceWasCleared' => false,
					'blockedBy' => null,
					'groupCount' => 2,
					'userCount' => 0,
					'everyoneCount' => 0,
				],
			]);

		$response = $this->controller->effective();

		$this->assertSame(0, $response->getData()['policies'][RequestSignGroupsPolicy::KEY]['groupCount']);
		$this->assertSame(2, $response->getData()['policies']['signature_flow']['groupCount']);
	}

	public function testEffectiveHidesHiddenConfettiRuleCountsForSubAdmin(): void {
		$this->groupManager->method('isAdmin')->with('admin')->willReturn(false);
		$this->subAdmin->method('isSubAdmin')->with($this->currentUser)->willReturn(true);
		$this->groupManager->method('getUserGroupIds')->with($this->currentUser)->willReturn(['board']);

		$this->policyService
			->expects($this->exactly(2))
			->method('shouldFilterVisibleGroupCountsForCurrentActor')
			->willReturnCallback(static fn (string $policyKey): bool => $policyKey === ConfettiPolicy::KEY);

		$this->policyService
			->expects($this->once())
			->method('getRuleCounts')
			->with(['board'], [])
			->willReturn($rawRuleCounts = [
				ConfettiPolicy::KEY => ['groupCount' => 1, 'userCount' => 0, 'everyoneCount' => 0],
				'signature_flow' => ['groupCount' => 2, 'userCount' => 0, 'everyoneCount' => 0],
			]);

		$this->policyService
			->expects($this->once())
			->method('countVisibleGroupPoliciesForTargets')
			->with(ConfettiPolicy::KEY, ['board'])
			->willReturn(0);

		$this->policyService
			->expects($this->once())
			->method('resolveKnownPolicyStatesWithRuleCounts')
			->with([
				ConfettiPolicy::KEY => ['groupCount' => 0, 'userCount' => 0, 'everyoneCount' => 0],
				'signature_flow' => $rawRuleCounts['signature_flow'],
			])
			->willReturn([
				ConfettiPolicy::KEY => [
					'policyKey' => ConfettiPolicy::KEY,
					'effectiveValue' => false,
					'inheritedValue' => null,
					'sourceScope' => 'group',
					'visible' => true,
					'editableByCurrentActor' => false,
					'allowedValues' => [false, true],
					'canSaveAsUserDefault' => true,
					'canUseAsRequestOverride' => false,
					'preferenceWasCleared' => false,
					'blockedBy' => null,
					'groupCount' => 0,
					'userCount' => 0,
					'everyoneCount' => 0,
				],
				'signature_flow' => [
					'policyKey' => 'signature_flow',
					'effectiveValue' => 'parallel',
					'inheritedValue' => null,
					'sourceScope' => 'system',
					'visible' => true,
					'editableByCurrentActor' => true,
					'allowedValues' => ['parallel'],
					'canSaveAsUserDefault' => false,
					'canUseAsRequestOverride' => false,
					'preferenceWasCleared' => false,
					'blockedBy' => null,
					'groupCount' => 2,
					'userCount' => 0,
					'everyoneCount' => 0,
				],
			]);

		$response = $this->controller->effective();

		$this->assertSame(0, $response->getData()['policies'][ConfettiPolicy::KEY]['groupCount']);
		$this->assertSame(2, $response->getData()['policies']['signature_flow']['groupCount']);
	}

	public function testEffectiveKeepsNonEditablePoliciesVisibleForSubAdmin(): void {
		$this->groupManager->method('isAdmin')->with('admin')->willReturn(false);
		$this->subAdmin->method('isSubAdmin')->with($this->currentUser)->willReturn(true);
		$this->groupManager->method('getUserGroupIds')->with($this->currentUser)->willReturn(['finance']);

		$this->policyService
			->expects($this->exactly(2))
			->method('shouldFilterVisibleGroupCountsForCurrentActor')
			->willReturn(false);

		$this->policyService
			->expects($this->once())
			->method('getRuleCounts')
			->with(['finance'], [])
			->willReturn($ruleCounts = [
				'signature_flow' => ['groupCount' => 1, 'userCount' => 0, 'everyoneCount' => 0],
				'signature_text' => ['groupCount' => 2, 'userCount' => 0, 'everyoneCount' => 0],
			]);

		$this->policyService
			->expects($this->once())
			->method('resolveKnownPolicyStatesWithRuleCounts')
			->with($ruleCounts)
			->willReturn([
				'signature_flow' => [
					'policyKey' => 'signature_flow',
					'effectiveValue' => 'ordered_numeric',
					'inheritedValue' => null,
					'sourceScope' => 'group',
					'visible' => true,
					'editableByCurrentActor' => true,
					'allowedValues' => ['ordered_numeric'],
					'canSaveAsUserDefault' => false,
					'canUseAsRequestOverride' => false,
					'preferenceWasCleared' => false,
					'blockedBy' => null,
					'groupCount' => 1,
					'userCount' => 0,
					'everyoneCount' => 0,
				],
				'signature_text' => [
					'policyKey' => 'signature_text',
					'effectiveValue' => 'Hello',
					'inheritedValue' => null,
					'sourceScope' => 'system',
					'visible' => true,
					'editableByCurrentActor' => false,
					'allowedValues' => [],
					'canSaveAsUserDefault' => false,
					'canUseAsRequestOverride' => false,
					'preferenceWasCleared' => false,
					'blockedBy' => 'system',
					'groupCount' => 2,
					'userCount' => 0,
					'everyoneCount' => 0,
				],
			]);

		$response = $this->controller->effective();
		$policies = $response->getData()['policies'];

		$this->assertArrayHasKey('signature_flow', $policies);
		$this->assertArrayHasKey('signature_text', $policies);
		$this->assertFalse($policies['signature_text']['editableByCurrentActor']);
	}

	public function testEffectiveEmbedsZeroCountsForRegularUser(): void {
		$this->groupManager->method('isAdmin')->with('admin')->willReturn(false);
		$this->subAdmin->method('isSubAdmin')->with($this->currentUser)->willReturn(false);

		$this->policyService->expects($this->never())->method('getRuleCounts');

		$this->policyService
			->expects($this->once())
			->method('resolveKnownPolicyStatesWithRuleCounts')
			->with([])
			->willReturn([
				'signature_flow' => [
					'policyKey' => 'signature_flow',
					'effectiveValue' => 'parallel',
					'inheritedValue' => null,
					'sourceScope' => 'system',
					'visible' => true,
					'editableByCurrentActor' => true,
					'allowedValues' => ['parallel', 'ordered_numeric'],
					'canSaveAsUserDefault' => true,
					'canUseAsRequestOverride' => false,
					'preferenceWasCleared' => false,
					'blockedBy' => null,
					'groupCount' => 0,
					'userCount' => 0,
					'everyoneCount' => 0,
				],
			]);

		$response = $this->controller->effective();

		$this->assertSame(0, $response->getData()['policies']['signature_flow']['groupCount']);
		$this->assertSame(0, $response->getData()['policies']['signature_flow']['userCount']);
		$this->assertSame(0, $response->getData()['policies']['signature_flow']['everyoneCount']);
	}

	public function testSetSystemReturnsSavedResolvedPolicy(): void {
		$resolvedPolicy = (new ResolvedPolicy())
			->setPolicyKey('signature_flow')
			->setEffectiveValue('ordered_numeric')
			->setSourceScope('system')
			->setVisible(true)
			->setEditableByCurrentActor(true)
			->setAllowedValues(['none', 'parallel', 'ordered_numeric'])
			->setCanSaveAsUserDefault(true)
			->setCanUseAsRequestOverride(false)
			->setPreferenceWasCleared(false)
			->setBlockedBy(null);

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Settings saved')
			->willReturn('Settings saved');

		$this->policyService
			->expects($this->once())
			->method('saveSystem')
			->with('signature_flow', 'ordered_numeric', false)
			->willReturn($resolvedPolicy);

		$response = $this->controller->setSystem('signature_flow', 'ordered_numeric');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([
			'message' => 'Settings saved',
			'policy' => [
				'policyKey' => 'signature_flow',
				'effectiveValue' => 'ordered_numeric',
				'inheritedValue' => null,
				'sourceScope' => 'system',
				'visible' => true,
				'editableByCurrentActor' => true,
				'allowedValues' => ['none', 'parallel', 'ordered_numeric'],
				'canSaveAsUserDefault' => true,
				'canUseAsRequestOverride' => false,
				'preferenceWasCleared' => false,
				'blockedBy' => null,
			],
		], $response->getData());
	}

	public function testSetSystemAcceptsDescriptionOnlySignatureStampPayload(): void {
		$payload = '{"template":"Signed with LibreSign","template_font_size":9.8,"signature_font_size":20,"signature_width":350,"signature_height":100,"background_type":"default","render_mode":"description_only"}';
		$resolvedPolicy = (new ResolvedPolicy())
			->setPolicyKey('signature_stamp')
			->setEffectiveValue($payload)
			->setSourceScope('system')
			->setVisible(true)
			->setEditableByCurrentActor(true)
			->setAllowedValues([])
			->setCanSaveAsUserDefault(true)
			->setCanUseAsRequestOverride(false)
			->setPreferenceWasCleared(false)
			->setBlockedBy(null);

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Settings saved')
			->willReturn('Settings saved');

		$this->policyService
			->expects($this->once())
			->method('saveSystem')
			->with('signature_stamp', $payload, false)
			->willReturn($resolvedPolicy);

		$response = $this->controller->setSystem('signature_stamp', $payload);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$effectiveValue = json_decode((string)$response->getData()['policy']['effectiveValue'], true, flags: JSON_THROW_ON_ERROR);
		$this->assertSame('description_only', $effectiveValue['render_mode']);
		$this->assertSame([], $response->getData()['policy']['allowedValues']);
	}

	public function testSetSystemForwardsAllowChildOverrideWhenProvided(): void {
		$resolvedPolicy = (new ResolvedPolicy())
			->setPolicyKey('signature_flow')
			->setEffectiveValue('ordered_numeric')
			->setSourceScope('system')
			->setVisible(true)
			->setEditableByCurrentActor(true)
			->setAllowedValues([])
			->setCanSaveAsUserDefault(true)
			->setCanUseAsRequestOverride(true)
			->setPreferenceWasCleared(false)
			->setBlockedBy(null);

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Settings saved')
			->willReturn('Settings saved');

		$this->policyService
			->expects($this->once())
			->method('saveSystem')
			->with('signature_flow', 'ordered_numeric', true)
			->willReturn($resolvedPolicy);

		$response = $this->controller->setSystem('signature_flow', 'ordered_numeric', true);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testSetSystemKeepsResolvedPolicyMetaWhenPresent(): void {
		$resolvedPolicy = (new ResolvedPolicy())
			->setPolicyKey('signature_stamp')
			->setEffectiveValue('ordered_numeric')
			->setSourceScope('system')
			->setVisible(true)
			->setEditableByCurrentActor(true)
			->setAllowedValues([])
			->setCanSaveAsUserDefault(true)
			->setCanUseAsRequestOverride(false)
			->setPreferenceWasCleared(false)
			->setBlockedBy(null)
			->setMeta(['defaultSystemValue' => 'canonical']);

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Settings saved')
			->willReturn('Settings saved');

		$this->policyService
			->expects($this->once())
			->method('saveSystem')
			->with('signature_stamp', 'ordered_numeric', false)
			->willReturn($resolvedPolicy);

		$response = $this->controller->setSystem('signature_stamp', 'ordered_numeric');

		$this->assertSame(['defaultSystemValue' => 'canonical'], $response->getData()['policy']['meta']);
	}
	public function testSetSystemReturnsBadRequestWhenPolicyValueIsInvalid(): void {
		$this->policyService
			->expects($this->once())
			->method('saveSystem')
			->with('signature_flow', 'banana', false)
			->willThrowException(new \InvalidArgumentException('Invalid value for signature_flow'));

		$response = $this->controller->setSystem('signature_flow', 'banana');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame([
			'error' => 'Invalid value for signature_flow',
		], $response->getData());
	}

	public function testSetSystemBubblesUnexpectedExceptions(): void {
		$this->policyService
			->expects($this->once())
			->method('saveSystem')
			->with('signature_flow', 'ordered_numeric', false)
			->willThrowException(new \RuntimeException('Unexpected policy failure'));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Unexpected policy failure');

		$this->controller->setSystem('signature_flow', 'ordered_numeric');
	}

	public function testSetSystemAllowsNullResetForRequestSignGroups(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$resolvedPolicy = (new ResolvedPolicy())
			->setPolicyKey(RequestSignGroupsPolicy::KEY)
			->setEffectiveValue('["admin"]')
			->setSourceScope('system')
			->setVisible(true)
			->setEditableByCurrentActor(true)
			->setAllowedValues([])
			->setCanSaveAsUserDefault(false)
			->setCanUseAsRequestOverride(false)
			->setPreferenceWasCleared(false)
			->setBlockedBy(null);

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Settings saved')
			->willReturn('Settings saved');

		$this->policyService
			->expects($this->once())
			->method('saveSystem')
			->with(RequestSignGroupsPolicy::KEY, null, false)
			->willReturn($resolvedPolicy);

		$response = $this->controller->setSystem(RequestSignGroupsPolicy::KEY, null);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testSetUserPreferenceReturnsSavedResolvedPolicy(): void {
		$resolvedPolicy = (new ResolvedPolicy())
			->setPolicyKey('signature_flow')
			->setEffectiveValue('parallel')
			->setSourceScope('user')
			->setVisible(true)
			->setEditableByCurrentActor(true)
			->setAllowedValues(['parallel', 'ordered_numeric'])
			->setCanSaveAsUserDefault(true)
			->setCanUseAsRequestOverride(true)
			->setPreferenceWasCleared(false)
			->setBlockedBy(null);

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Settings saved')
			->willReturn('Settings saved');

		$this->policyService
			->expects($this->once())
			->method('saveUserPreference')
			->with('signature_flow', 'parallel')
			->willReturn($resolvedPolicy);

		$response = $this->controller->setUserPreference('signature_flow', 'parallel');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('user', $response->getData()['policy']['sourceScope']);
	}

	public function testGetGroupReturnsStoredGroupPolicy(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$policyLayer = (new PolicyLayer())
			->setScope('group')
			->setValue('parallel')
			->setAllowChildOverride(true)
			->setVisibleToChild(true)
			->setAllowedValues([]);

		$this->policyService
			->expects($this->once())
			->method('getGroupPolicy')
			->with('signature_flow', 'finance')
			->willReturn($policyLayer);

		$this->policyService
			->expects($this->once())
			->method('canViewGroupPolicy')
			->with('signature_flow', 'finance', $policyLayer)
			->willReturn(true);

		$this->policyService
			->expects($this->once())
			->method('canDeleteGroupPolicy')
			->with('signature_flow', 'finance', $policyLayer)
			->willReturn(false);

		$response = $this->controller->getGroup('finance', 'signature_flow');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([
			'policy' => [
				'policyKey' => 'signature_flow',
				'scope' => 'group',
				'targetId' => 'finance',
				'value' => 'parallel',
				'allowChildOverride' => true,
				'visibleToChild' => true,
				'allowedValues' => [],
				'deletableByCurrentActor' => false,
			],
		], $response->getData());
	}

	public function testSetGroupReturnsSavedGroupPolicy(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Settings saved')
			->willReturn('Settings saved');

		$this->policyService
			->expects($this->once())
			->method('saveGroupPolicy')
			->with('signature_flow', 'finance', 'ordered_numeric', false)
			->willReturn((new PolicyLayer())
				->setScope('group')
				->setValue('ordered_numeric')
				->setAllowChildOverride(false)
				->setVisibleToChild(true)
				->setAllowedValues(['ordered_numeric']));

		$response = $this->controller->setGroup('finance', 'signature_flow', 'ordered_numeric', false);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('group', $response->getData()['policy']['scope']);
		$this->assertSame('finance', $response->getData()['policy']['targetId']);
	}

	public function testSetGroupReadsBodyParamsFromRequest(): void {
		$request = $this->createMock(IRequest::class);
		$request
			->method('getParam')
			->willReturnCallback(static fn (string $key, mixed $default = null): mixed => $default);
		$request
			->method('getParams')
			->willReturn([
				'value' => 'ordered_numeric',
				'allowChildOverride' => false,
			]);
		$controller = new PolicyController(
			$request,
			$this->l10n,
			$this->policyService,
			$this->requestSignGroupsPolicyGuard,
			$this->policyAuthorizationService,
			$this->userSession,
			$this->groupManager,
			$this->userManager,
			$this->subAdmin,
		);

		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Settings saved')
			->willReturn('Settings saved');

		$this->policyService
			->expects($this->once())
			->method('saveGroupPolicy')
			->with('signature_flow', 'finance', 'ordered_numeric', false)
			->willReturn((new PolicyLayer())
				->setScope('group')
				->setValue('ordered_numeric')
				->setAllowChildOverride(false)
				->setVisibleToChild(true)
				->setAllowedValues(['ordered_numeric']));

		$response = $controller->setGroup('finance', 'signature_flow');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('ordered_numeric', $response->getData()['policy']['value']);
		$this->assertFalse($response->getData()['policy']['allowChildOverride']);
	}

	public function testGetGroupReturnsForbiddenWhenRequestAccessRuleIsHiddenFromSubAdmin(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(false);
		$this->subAdmin
			->method('isSubAdmin')
			->with($this->currentUser)
			->willReturn(true);
		$this->groupManager
			->method('getUserGroupIds')
			->with($this->currentUser)
			->willReturn(['company', 'board']);
		$this->mockDelegatedRequestSignGroups(['company', 'board'], ['company', 'board']);

		$policyLayer = (new PolicyLayer())
			->setScope('group')
			->setValue('["board","company"]')
			->setAllowChildOverride(true)
			->setVisibleToChild(true)
			->setAllowedValues([]);

		$this->policyService
			->expects($this->once())
			->method('getGroupPolicy')
			->with(RequestSignGroupsPolicy::KEY, 'company')
			->willReturn($policyLayer);

		$this->policyService
			->expects($this->once())
			->method('canViewGroupPolicy')
			->with(RequestSignGroupsPolicy::KEY, 'company', $policyLayer)
			->willReturn(false);

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Not allowed to manage this group policy')
			->willReturn('Not allowed to manage this group policy');

		$response = $this->controller->getGroup('company', RequestSignGroupsPolicy::KEY);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame([
			'error' => 'Not allowed to manage this group policy',
		], $response->getData());
	}

	public function testGetGroupReturnsForbiddenWhenConfettiSeedRuleIsHiddenFromSubAdmin(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(false);
		$this->subAdmin
			->method('isSubAdmin')
			->with($this->currentUser)
			->willReturn(true);
		$this->groupManager
			->method('getUserGroupIds')
			->with($this->currentUser)
			->willReturn(['board']);

		$policyLayer = (new PolicyLayer())
			->setScope('group')
			->setValue(false)
			->setAllowChildOverride(true)
			->setVisibleToChild(true)
			->setAllowedValues([false, true]);

		$this->policyService
			->expects($this->once())
			->method('getGroupPolicy')
			->with(ConfettiPolicy::KEY, 'board')
			->willReturn($policyLayer);

		$this->policyService
			->expects($this->once())
			->method('canViewGroupPolicy')
			->with(ConfettiPolicy::KEY, 'board', $policyLayer)
			->willReturn(false);

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Not allowed to manage this group policy')
			->willReturn('Not allowed to manage this group policy');

		$response = $this->controller->getGroup('board', ConfettiPolicy::KEY);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame([
			'error' => 'Not allowed to manage this group policy',
		], $response->getData());
	}

	public function testListGroupPoliciesHidesSysadminDelegationRulesFromSubAdmin(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(false);
		$this->subAdmin
			->method('isSubAdmin')
			->with($this->currentUser)
			->willReturn(true);
		$this->groupManager
			->method('getUserGroupIds')
			->with($this->currentUser)
			->willReturn(['company', 'board']);
		$this->mockDelegatedRequestSignGroups(['company', 'board'], ['company', 'board']);

		$hiddenPolicy = (new PolicyLayer())
			->setScope('group')
			->setValue('["board","company"]')
			->setAllowChildOverride(true)
			->setVisibleToChild(true)
			->setAllowedValues([]);
		$visiblePolicy = (new PolicyLayer())
			->setScope('group')
			->setValue('["board"]')
			->setAllowChildOverride(true)
			->setVisibleToChild(true)
			->setAllowedValues([]);

		$this->policyService
			->expects($this->once())
			->method('listGroupPoliciesForTargets')
			->with(RequestSignGroupsPolicy::KEY, ['company', 'board'])
			->willReturn([
				['targetId' => 'company', 'policy' => $hiddenPolicy],
				['targetId' => 'board', 'policy' => $visiblePolicy],
			]);

		$this->policyService
			->expects($this->exactly(2))
			->method('canViewGroupPolicy')
			->willReturnCallback(static function (string $policyKey, string $groupId, PolicyLayer $policy) use ($hiddenPolicy, $visiblePolicy): bool {
				self::assertSame(RequestSignGroupsPolicy::KEY, $policyKey);
				return match ($groupId) {
					'company' => $policy === $hiddenPolicy ? false : false,
					'board' => $policy === $visiblePolicy,
					default => false,
				};
			});

		$this->policyService
			->expects($this->once())
			->method('canDeleteGroupPolicy')
			->with(RequestSignGroupsPolicy::KEY, 'board', $visiblePolicy)
			->willReturn(true);

		$response = $this->controller->listGroupPolicies(RequestSignGroupsPolicy::KEY);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([
			'policies' => [[
				'policyKey' => RequestSignGroupsPolicy::KEY,
				'scope' => 'group',
				'targetId' => 'board',
				'value' => '["board"]',
				'allowChildOverride' => true,
				'visibleToChild' => true,
				'allowedValues' => [],
				'deletableByCurrentActor' => true,
			]],
		], $response->getData());
	}

	public function testListGroupPoliciesHidesConfettiSeedRulesFromSubAdmin(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(false);
		$this->subAdmin
			->method('isSubAdmin')
			->with($this->currentUser)
			->willReturn(true);
		$this->groupManager
			->method('getUserGroupIds')
			->with($this->currentUser)
			->willReturn(['board']);

		$hiddenPolicy = (new PolicyLayer())
			->setScope('group')
			->setValue(false)
			->setAllowChildOverride(true)
			->setVisibleToChild(true)
			->setAllowedValues([false, true]);

		$this->policyService
			->expects($this->once())
			->method('listGroupPoliciesForTargets')
			->with(ConfettiPolicy::KEY, ['board'])
			->willReturn([
				['targetId' => 'board', 'policy' => $hiddenPolicy],
			]);

		$this->policyService
			->expects($this->once())
			->method('canViewGroupPolicy')
			->with(ConfettiPolicy::KEY, 'board', $hiddenPolicy)
			->willReturn(false);

		$response = $this->controller->listGroupPolicies(ConfettiPolicy::KEY);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([
			'policies' => [],
		], $response->getData());
	}

	public function testSetGroupIgnoresStringAllowChildOverrideFromRequest(): void {
		$request = $this->createMock(IRequest::class);
		$request
			->method('getParam')
			->willReturnCallback(static fn (string $key, mixed $default = null): mixed => $default);
		$request
			->method('getParams')
			->willReturn([
				'value' => 'ordered_numeric',
				'allowChildOverride' => 'true',
			]);
		$controller = new PolicyController(
			$request,
			$this->l10n,
			$this->policyService,
			$this->requestSignGroupsPolicyGuard,
			$this->policyAuthorizationService,
			$this->userSession,
			$this->groupManager,
			$this->userManager,
			$this->subAdmin,
		);

		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Settings saved')
			->willReturn('Settings saved');

		$this->policyService
			->expects($this->once())
			->method('saveGroupPolicy')
			->with('signature_flow', 'finance', 'ordered_numeric', false)
			->willReturn((new PolicyLayer())
				->setScope('group')
				->setValue('ordered_numeric')
				->setAllowChildOverride(false)
				->setVisibleToChild(true)
				->setAllowedValues(['ordered_numeric']));

		$response = $controller->setGroup('finance', 'signature_flow');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertFalse($response->getData()['policy']['allowChildOverride']);
	}

	public function testGetGroupReturnsForbiddenWhenUserCannotManageTargetGroup(): void {
		$this->userSession
			->method('getUser')
			->willReturn(null);
		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Not allowed to manage this group policy')
			->willReturn('Not allowed to manage this group policy');

		$response = $this->controller->getGroup('finance', 'signature_flow');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame([
			'error' => 'Not allowed to manage this group policy',
		], $response->getData());
	}

	public function testSetGroupAllowsSubAdminOfTargetGroup(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(false);
		$this->subAdmin
			->method('isSubAdmin')
			->with($this->currentUser)
			->willReturn(true);
		$this->groupManager
			->method('getUserGroupIds')
			->with($this->currentUser)
			->willReturn(['finance', 'board']);

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Settings saved')
			->willReturn('Settings saved');
		$this->policyService
			->expects($this->once())
			->method('saveGroupPolicy')
			->with('signature_flow', 'finance', 'parallel', true)
			->willReturn((new PolicyLayer())
				->setScope('group')
				->setValue('parallel')
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues(['parallel', 'ordered_numeric']));

		$response = $this->controller->setGroup('finance', 'signature_flow', 'parallel', true);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testSetGroupReturnsForbiddenWhenGlobalDefaultBlocksLowerLevelOverrides(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$this->policyService
			->expects($this->once())
			->method('saveGroupPolicy')
			->with('signature_flow', 'finance', 'parallel', true)
			->willThrowException(new \DomainException('Lower-level overrides are not allowed for this policy'));

		$response = $this->controller->setGroup('finance', 'signature_flow', 'parallel', true);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame([
			'error' => 'Lower-level overrides are not allowed for this policy',
		], $response->getData());
	}

	public function testSetGroupBubblesUnexpectedExceptions(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$this->policyService
			->expects($this->once())
			->method('saveGroupPolicy')
			->with('signature_flow', 'finance', 'ordered_numeric', false)
			->willThrowException(new \RuntimeException('Unexpected policy failure'));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Unexpected policy failure');

		$this->controller->setGroup('finance', 'signature_flow', 'ordered_numeric');
	}

	public function testClearGroupBubblesUnexpectedExceptions(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$this->policyService
			->expects($this->once())
			->method('clearGroupPolicy')
			->with('signature_flow', 'finance')
			->willThrowException(new \RuntimeException('Unexpected policy failure'));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Unexpected policy failure');

		$this->controller->clearGroup('finance', 'signature_flow');
	}

	public function testClearGroupReturnsForbiddenWhenGlobalDefaultBlocksLowerLevelOverrides(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$this->policyService
			->expects($this->once())
			->method('clearGroupPolicy')
			->with('signature_flow', 'finance')
			->willThrowException(new \DomainException('Lower-level overrides are not allowed for this policy'));

		$response = $this->controller->clearGroup('finance', 'signature_flow');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame([
			'error' => 'Lower-level overrides are not allowed for this policy',
		], $response->getData());
	}

	public function testClearGroupReturnsBadRequestWhenPolicyDoesNotSupportGroupScope(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$this->policyService
			->expects($this->once())
			->method('clearGroupPolicy')
			->with('signing_mode', 'finance')
			->willThrowException(new \InvalidArgumentException('Group-level scope is not supported for this policy'));

		$response = $this->controller->clearGroup('finance', 'signing_mode');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame([
			'error' => 'Group-level scope is not supported for this policy',
		], $response->getData());
	}

	public function testClearGroupReturnsForbiddenForSubAdminEvenOnManagedGroup(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(false);
		$this->subAdmin
			->method('isSubAdmin')
			->with($this->currentUser)
			->willReturn(true);
		$this->groupManager
			->method('getUserGroupIds')
			->with($this->currentUser)
			->willReturn(['finance', 'board']);

		$this->policyService
			->expects($this->once())
			->method('clearGroupPolicy')
			->with('signature_flow', 'finance')
			->willThrowException(new \DomainException('Only system administrators can delete group rules created by a system administrator'));

		$response = $this->controller->clearGroup('finance', 'signature_flow');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame([
			'error' => 'Only system administrators can delete group rules created by a system administrator',
		], $response->getData());
	}

	public function testGetGroupExposesDeleteCapabilityForCurrentActor(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$policyLayer = (new PolicyLayer())
			->setScope('group')
			->setValue('parallel')
			->setAllowChildOverride(true)
			->setVisibleToChild(true)
			->setAllowedValues(['parallel', 'ordered_numeric']);

		$this->policyService
			->expects($this->once())
			->method('getGroupPolicy')
			->with('signature_flow', 'finance')
			->willReturn($policyLayer);

		$this->policyService
			->expects($this->once())
			->method('canViewGroupPolicy')
			->with('signature_flow', 'finance', $policyLayer)
			->willReturn(true);

		$this->policyService
			->expects($this->once())
			->method('canDeleteGroupPolicy')
			->with('signature_flow', 'finance', $policyLayer)
			->willReturn(true);

		$response = $this->controller->getGroup('finance', 'signature_flow');

		$this->assertTrue($response->getData()['policy']['deletableByCurrentActor']);
	}

	public function testSetUserPolicyForTargetUserReturnsSavedExplicitPolicy(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$this->policyService
			->expects($this->once())
			->method('canManageUserPolicyForUserId')
			->with('signature_flow', 'user1')
			->willReturn(true);

		$persistedPolicy = (new PolicyLayer())
			->setScope('user_policy')
			->setValue('ordered_numeric')
			->setAllowChildOverride(false);

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Settings saved')
			->willReturn('Settings saved');

		$this->policyService
			->expects($this->once())
			->method('saveUserPolicyForUserId')
			->with('signature_flow', 'user1', 'ordered_numeric', false)
			->willReturn($persistedPolicy);

		$response = $this->controller->setUserPolicyForUser('user1', 'signature_flow', 'ordered_numeric');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('user_policy', $response->getData()['policy']['scope']);
		$this->assertSame('user1', $response->getData()['policy']['targetId']);
		$this->assertSame('ordered_numeric', $response->getData()['policy']['value']);
		$this->assertFalse($response->getData()['policy']['allowChildOverride']);
	}

	public function testClearUserPolicyForTargetUserReturnsClearedExplicitPolicy(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$this->policyService
			->expects($this->once())
			->method('canManageUserPolicyForUserId')
			->with('signature_flow', 'user1')
			->willReturn(true);

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Settings saved')
			->willReturn('Settings saved');

		$this->policyService
			->expects($this->once())
			->method('clearUserPolicyForUserId')
			->with('signature_flow', 'user1')
			->willReturn(null);

		$response = $this->controller->clearUserPolicyForUser('user1', 'signature_flow');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('user_policy', $response->getData()['policy']['scope']);
		$this->assertSame('user1', $response->getData()['policy']['targetId']);
		$this->assertNull($response->getData()['policy']['value']);
		$this->assertTrue($response->getData()['policy']['allowChildOverride']);
	}

	public function testSetUserPolicyForTargetUserAllowsSubAdminWhenPolicyDelegationIsPresent(): void {
		$this->groupManager
			->method('isAdmin')
			->willReturn(false);
		$this->subAdmin
			->method('isSubAdmin')
			->with($this->currentUser)
			->willReturn(true);
		$this->userManager
			->method('get')
			->with('user1')
			->willReturn($targetUser = $this->createMock(IUser::class));
		$this->subAdmin
			->method('getSubAdminsGroups')
			->with($this->currentUser)
			->willReturn([$managedGroup = $this->createMock(\OCP\IGroup::class)]);
		$managedGroup->method('getGID')->willReturn('board');
		$this->groupManager
			->method('getUserGroupIds')
			->with($targetUser)
			->willReturn(['board']);

		$this->policyService
			->expects($this->once())
			->method('canManageUserPolicyForUserId')
			->with(ConfettiPolicy::KEY, 'user1')
			->willReturn(true);

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Settings saved')
			->willReturn('Settings saved');

		$this->policyService
			->expects($this->once())
			->method('saveUserPolicyForUserId')
			->with(ConfettiPolicy::KEY, 'user1', true, false)
			->willReturn((new PolicyLayer())
				->setScope('user_policy')
				->setValue(true)
				->setAllowChildOverride(false));

		$response = $this->controller->setUserPolicyForUser('user1', ConfettiPolicy::KEY, true, false);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([
			'message' => 'Settings saved',
			'policy' => [
				'policyKey' => ConfettiPolicy::KEY,
				'scope' => 'user_policy',
				'targetId' => 'user1',
				'value' => true,
				'allowChildOverride' => false,
			],
		], $response->getData());
	}

	public function testClearUserPolicyForTargetUserAllowsSubAdminWhenPolicyDelegationIsPresent(): void {
		$this->groupManager
			->method('isAdmin')
			->willReturn(false);
		$this->subAdmin
			->method('isSubAdmin')
			->with($this->currentUser)
			->willReturn(true);
		$this->userManager
			->method('get')
			->with('user1')
			->willReturn($targetUser = $this->createMock(IUser::class));
		$this->subAdmin
			->method('getSubAdminsGroups')
			->with($this->currentUser)
			->willReturn([$managedGroup = $this->createMock(\OCP\IGroup::class)]);
		$managedGroup->method('getGID')->willReturn('board');
		$this->groupManager
			->method('getUserGroupIds')
			->with($targetUser)
			->willReturn(['board']);

		$this->policyService
			->expects($this->once())
			->method('canManageUserPolicyForUserId')
			->with(ConfettiPolicy::KEY, 'user1')
			->willReturn(true);

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Settings saved')
			->willReturn('Settings saved');

		$this->policyService
			->expects($this->once())
			->method('clearUserPolicyForUserId')
			->with(ConfettiPolicy::KEY, 'user1')
			->willReturn(null);

		$response = $this->controller->clearUserPolicyForUser('user1', ConfettiPolicy::KEY);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([
			'message' => 'Settings saved',
			'policy' => [
				'policyKey' => ConfettiPolicy::KEY,
				'scope' => 'user_policy',
				'targetId' => 'user1',
				'value' => null,
				'allowChildOverride' => true,
			],
		], $response->getData());
	}

	public function testSetUserPolicyForTargetUserReturnsBadRequestWhenServiceBlocksSave(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$this->policyService
			->expects($this->once())
			->method('canManageUserPolicyForUserId')
			->with('signature_flow', 'user1')
			->willReturn(true);

		$this->policyService
			->expects($this->once())
			->method('saveUserPolicyForUserId')
			->with('signature_flow', 'user1', 'ordered_numeric', false)
			->willThrowException(new \InvalidArgumentException('Invalid value for signature_flow'));

		$response = $this->controller->setUserPolicyForUser('user1', 'signature_flow', 'ordered_numeric');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame([
			'error' => 'Invalid value for signature_flow',
		], $response->getData());
	}

	public function testSetUserPreferenceBubblesUnexpectedExceptions(): void {
		$this->policyService
			->expects($this->once())
			->method('saveUserPreference')
			->with('signature_flow', 'parallel')
			->willThrowException(new \RuntimeException('Unexpected policy failure'));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Unexpected policy failure');

		$this->controller->setUserPreference('signature_flow', 'parallel');
	}

	public function testSetUserPolicyForTargetUserBubblesUnexpectedExceptions(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$this->policyService
			->expects($this->once())
			->method('canManageUserPolicyForUserId')
			->with('signature_flow', 'user1')
			->willReturn(true);

		$this->policyService
			->expects($this->once())
			->method('saveUserPolicyForUserId')
			->with('signature_flow', 'user1', 'ordered_numeric', false)
			->willThrowException(new \RuntimeException('Unexpected policy failure'));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Unexpected policy failure');

		$this->controller->setUserPolicyForUser('user1', 'signature_flow', 'ordered_numeric');
	}

	public function testClearUserPreferenceBubblesUnexpectedExceptions(): void {
		$this->policyService
			->expects($this->once())
			->method('clearUserPreference')
			->with('signature_flow')
			->willThrowException(new \RuntimeException('Unexpected policy failure'));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Unexpected policy failure');

		$this->controller->clearUserPreference('signature_flow');
	}

	public function testClearUserPolicyForTargetUserBubblesUnexpectedExceptions(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$this->policyService
			->expects($this->once())
			->method('canManageUserPolicyForUserId')
			->with('signature_flow', 'user1')
			->willReturn(true);

		$this->policyService
			->expects($this->once())
			->method('clearUserPolicyForUserId')
			->with('signature_flow', 'user1')
			->willThrowException(new \RuntimeException('Unexpected policy failure'));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Unexpected policy failure');

		$this->controller->clearUserPolicyForUser('user1', 'signature_flow');
	}

	public function testSetUserPolicyForTargetUserReturnsForbiddenWhenCurrentActorCannotManageTargetUser(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(false);
		$this->subAdmin
			->method('isSubAdmin')
			->with($this->currentUser)
			->willReturn(false);
		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Not allowed to manage this user policy')
			->willReturn('Not allowed to manage this user policy');

		$this->policyService->expects($this->never())->method('saveUserPolicyForUserId');

		$response = $this->controller->setUserPolicyForUser('user1', 'signature_flow', 'ordered_numeric');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame([
			'error' => 'Not allowed to manage this user policy',
		], $response->getData());
	}

	public function testSetUserPolicyForTargetUserReturnsForbiddenWhenPolicyDelegationIsMissing(): void {
		$this->groupManager
			->method('isAdmin')
			->willReturn(false);
		$this->subAdmin
			->method('isSubAdmin')
			->with($this->currentUser)
			->willReturn(true);
		$this->userManager
			->method('get')
			->with('user1')
			->willReturn($targetUser = $this->createMock(IUser::class));
		$this->subAdmin
			->method('getSubAdminsGroups')
			->with($this->currentUser)
			->willReturn([$managedGroup = $this->createMock(\OCP\IGroup::class)]);
		$managedGroup->method('getGID')->willReturn('board');
		$this->groupManager
			->method('getUserGroupIds')
			->with($targetUser)
			->willReturn(['board']);

		$this->policyService
			->expects($this->once())
			->method('canManageUserPolicyForUserId')
			->with(ConfettiPolicy::KEY, 'user1')
			->willReturn(false);

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Not allowed to manage this user policy')
			->willReturn('Not allowed to manage this user policy');

		$this->policyService->expects($this->never())->method('saveUserPolicyForUserId');

		$response = $this->controller->setUserPolicyForUser('user1', ConfettiPolicy::KEY, true);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame([
			'error' => 'Not allowed to manage this user policy',
		], $response->getData());
	}

	public function testClearUserPolicyForTargetUserReturnsForbiddenWhenCurrentActorCannotManageTargetUser(): void {
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(false);
		$this->subAdmin
			->method('isSubAdmin')
			->with($this->currentUser)
			->willReturn(false);
		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Not allowed to manage this user policy')
			->willReturn('Not allowed to manage this user policy');

		$this->policyService->expects($this->never())->method('clearUserPolicyForUserId');

		$response = $this->controller->clearUserPolicyForUser('user1', 'signature_flow');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame([
			'error' => 'Not allowed to manage this user policy',
		], $response->getData());
	}

	public function testSetUserPreferenceReadsBodyParamsFromRequest(): void {
		$request = $this->createMock(IRequest::class);
		$request
			->method('getParam')
			->willReturnCallback(static fn (string $key, mixed $default = null): mixed => $default);
		$request
			->method('getParams')
			->willReturn([
				'value' => 'parallel',
			]);
		$controller = new PolicyController(
			$request,
			$this->l10n,
			$this->policyService,
			$this->requestSignGroupsPolicyGuard,
			$this->policyAuthorizationService,
			$this->userSession,
			$this->groupManager,
			$this->userManager,
			$this->subAdmin,
		);

		$resolvedPolicy = (new ResolvedPolicy())
			->setPolicyKey('signature_flow')
			->setEffectiveValue('parallel')
			->setSourceScope('user')
			->setVisible(true)
			->setEditableByCurrentActor(true)
			->setAllowedValues(['parallel', 'ordered_numeric'])
			->setCanSaveAsUserDefault(true)
			->setCanUseAsRequestOverride(true)
			->setPreferenceWasCleared(false)
			->setBlockedBy(null);

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Settings saved')
			->willReturn('Settings saved');

		$this->policyService
			->expects($this->once())
			->method('saveUserPreference')
			->with('signature_flow', 'parallel')
			->willReturn($resolvedPolicy);

		$response = $controller->setUserPreference('signature_flow');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('parallel', $response->getData()['policy']['effectiveValue']);
	}

	public function testSetUserPreferenceBlocksRequestSignGroupsUserScope(): void {
		$this->l10n
			->expects($this->once())
			->method('t')
			->with('User-level scope is not supported for this policy')
			->willReturn('User-level scope is not supported for this policy');

		$this->policyService->expects($this->never())->method('saveUserPreference');

		$response = $this->controller->setUserPreference(RequestSignGroupsPolicy::KEY, '["finance"]');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame([
			'error' => 'User-level scope is not supported for this policy',
		], $response->getData());
	}

	public function testSetUserPolicyForUserBlocksRequestSignGroupsUserScope(): void {
		$this->groupManager
			->method('isAdmin')
			->willReturn(true);

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('User-level scope is not supported for this policy')
			->willReturn('User-level scope is not supported for this policy');

		$this->policyService->expects($this->never())->method('saveUserPolicyForUserId');

		$response = $this->controller->setUserPolicyForUser('user1', RequestSignGroupsPolicy::KEY, '["finance"]');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame([
			'error' => 'User-level scope is not supported for this policy',
		], $response->getData());
	}

	public function testClearUserPreferenceBlocksRequestSignGroupsUserScope(): void {
		$this->l10n
			->expects($this->once())
			->method('t')
			->with('User-level scope is not supported for this policy')
			->willReturn('User-level scope is not supported for this policy');

		$this->policyService->expects($this->never())->method('clearUserPreference');

		$response = $this->controller->clearUserPreference(RequestSignGroupsPolicy::KEY);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame([
			'error' => 'User-level scope is not supported for this policy',
		], $response->getData());
	}

	public function testClearUserPolicyForUserBlocksRequestSignGroupsUserScope(): void {
		$this->groupManager
			->method('isAdmin')
			->willReturn(true);

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('User-level scope is not supported for this policy')
			->willReturn('User-level scope is not supported for this policy');

		$this->policyService->expects($this->never())->method('clearUserPolicyForUserId');

		$response = $this->controller->clearUserPolicyForUser('user1', RequestSignGroupsPolicy::KEY);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame([
			'error' => 'User-level scope is not supported for this policy',
		], $response->getData());
	}

	public function testSetGroupRejectsRequestSignGroupsOutsideDelegatedPolicyScope(): void {

		$this->groupManager
			->method('isAdmin')
			->willReturn(false);

		$this->subAdmin
			->method('isSubAdmin')
			->with($this->currentUser)
			->willReturn(true);
		$this->subAdmin
			->method('getSubAdminsGroups')
			->with($this->currentUser)
			->willReturn([$this->createGroup('finance')]);
		$this->policyService
			->method('resolveForUser')
			->with(RequestSignGroupsPolicy::KEY, $this->currentUser)
			->willReturn((new ResolvedPolicy())
				->setEffectiveValue('{"allowGroups":["finance"],"denyGroups":[]}')
				->setEditableByCurrentActor(true));

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('One or more selected groups are not allowed for your administration scope')
			->willReturn('One or more selected groups are not allowed for your administration scope');

		$this->policyService->expects($this->never())->method('saveGroupPolicy');

		$response = $this->controller->setGroup('finance', RequestSignGroupsPolicy::KEY, '["finance","legal"]');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame([
			'error' => 'One or more selected groups are not allowed for your administration scope',
		], $response->getData());
	}

	public function testSetGroupAllowsRequestSignGroupForManagedGroupOutsideInheritedPolicyScope(): void {
		$this->groupManager
			->method('isAdmin')
			->willReturn(false);

		$this->subAdmin
			->method('isSubAdmin')
			->with($this->currentUser)
			->willReturn(true);
		$this->subAdmin
			->method('getSubAdminsGroups')
			->with($this->currentUser)
			->willReturn([
				$this->createGroup('board'),
				$this->createGroup('company'),
			]);
		$this->policyService
			->method('resolveForUser')
			->with(RequestSignGroupsPolicy::KEY, $this->currentUser)
			->willReturn((new ResolvedPolicy())
				->setEffectiveValue('{"allowGroups":["board"],"denyGroups":[]}')
				->setEditableByCurrentActor(true));

		$this->policyService
			->expects($this->once())
			->method('saveGroupPolicy')
			->with(RequestSignGroupsPolicy::KEY, 'company', '{"allowGroups":["company"],"denyGroups":[]}', true)
			->willReturn((new PolicyLayer())
				->setScope('group')
				->setValue('{"allowGroups":["company"],"denyGroups":[]}')
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues([]));

		$response = $this->controller->setGroup(
			'company',
			RequestSignGroupsPolicy::KEY,
			'{"allowGroups":["company"],"denyGroups":[]}',
			true,
		);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('company', $response->getData()['policy']['targetId']);
		$this->assertSame('{"allowGroups":["company"],"denyGroups":[]}', $response->getData()['policy']['value']);
	}

	public function testClearGroupReturnsForbiddenForRequestSignGroupOutsideSubAdminScope(): void {
		$this->groupManager
			->method('isAdmin')
			->willReturn(false);

		$this->subAdmin
			->method('isSubAdmin')
			->with($this->currentUser)
			->willReturn(true);
		$this->subAdmin
			->method('getSubAdminsGroups')
			->with($this->currentUser)
			->willReturn([
				$this->createGroup('board'),
				$this->createGroup('company'),
			]);
		$this->policyService
			->method('resolveForUser')
			->with(RequestSignGroupsPolicy::KEY, $this->currentUser)
			->willReturn((new ResolvedPolicy())
				->setEffectiveValue('{"allowGroups":["board"],"denyGroups":[]}')
				->setEditableByCurrentActor(true));

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Not allowed to manage this group policy')
			->willReturn('Not allowed to manage this group policy');

		$this->policyService->expects($this->never())->method('clearGroupPolicy');

		$response = $this->controller->clearGroup('legal', RequestSignGroupsPolicy::KEY);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame([
			'error' => 'Not allowed to manage this group policy',
		], $response->getData());
	}

	private function createGroup(string $groupId): IGroup {
		$group = $this->createMock(IGroup::class);
		$group->method('getGID')->willReturn($groupId);
		return $group;
	}

	/**
	 * @param list<string> $managedGroupIds
	 * @param list<string> $allowGroups
	 * @param list<string> $denyGroups
	 */
	private function mockDelegatedRequestSignGroups(array $managedGroupIds, array $allowGroups, array $denyGroups = []): void {
		$this->subAdmin
			->method('getSubAdminsGroups')
			->with($this->currentUser)
			->willReturn(array_map(fn (string $groupId): IGroup => $this->createGroup($groupId), $managedGroupIds));

		$this->policyService
			->method('resolveForUser')
			->with(RequestSignGroupsPolicy::KEY, $this->currentUser)
			->willReturn((new ResolvedPolicy())
				->setEditableByCurrentActor(true)
				->setEffectiveValue(json_encode([
					'allowGroups' => $allowGroups,
					'denyGroups' => $denyGroups,
				], JSON_THROW_ON_ERROR)));
	}

}
