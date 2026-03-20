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
use OCA\Libresign\Service\Policy\PolicyService;
use OCP\AppFramework\Http;
use OCP\Group\ISubAdmin;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PolicyControllerTest extends TestCase {
	private IRequest&MockObject $request;
	private IL10N&MockObject $l10n;
	private PolicyService&MockObject $policyService;
	private IUserSession&MockObject $userSession;
	private IGroupManager&MockObject $groupManager;
	private ISubAdmin&MockObject $subAdmin;
	private IUser&MockObject $currentUser;
	private PolicyController $controller;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->policyService = $this->createMock(PolicyService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->subAdmin = $this->createMock(ISubAdmin::class);
		$this->currentUser = $this->createMock(IUser::class);
		$this->currentUser
			->method('getUID')
			->willReturn('admin');
		$this->userSession
			->method('getUser')
			->willReturn($this->currentUser);

		$this->controller = new PolicyController(
			$this->request,
			$this->l10n,
			$this->policyService,
			$this->userSession,
			$this->groupManager,
			$this->subAdmin,
		);
	}

	public function testEffectiveReturnsResolvedSignatureFlowPolicy(): void {
		$resolvedPolicy = (new ResolvedPolicy())
			->setPolicyKey('signature_flow')
			->setEffectiveValue('ordered_numeric')
			->setSourceScope('group')
			->setVisible(true)
			->setEditableByCurrentActor(false)
			->setAllowedValues(['ordered_numeric'])
			->setCanSaveAsUserDefault(false)
			->setCanUseAsRequestOverride(false)
			->setPreferenceWasCleared(false)
			->setBlockedBy('group');

		$this->policyService
			->expects($this->once())
			->method('resolveKnownPolicies')
			->willReturn([
				'signature_flow' => $resolvedPolicy,
			]);

		$response = $this->controller->effective();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([
			'policies' => [
				'signature_flow' => [
					'policyKey' => 'signature_flow',
					'effectiveValue' => 'ordered_numeric',
					'sourceScope' => 'group',
					'visible' => true,
					'editableByCurrentActor' => false,
					'allowedValues' => ['ordered_numeric'],
					'canSaveAsUserDefault' => false,
					'canUseAsRequestOverride' => false,
					'preferenceWasCleared' => false,
					'blockedBy' => 'group',
				],
			],
		], $response->getData());
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

	public function testSetSystemReturnsBadRequestWhenPolicyValueIsInvalid(): void {
		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Invalid value for signature_flow')
			->willReturn('Invalid value for signature_flow');

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

		$this->policyService
			->expects($this->once())
			->method('getGroupPolicy')
			->with('signature_flow', 'finance')
			->willReturn((new PolicyLayer())
				->setScope('group')
				->setValue('parallel')
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues([]));

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
		$group = $this->createMock(IGroup::class);
		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(false);
		$this->groupManager
			->method('get')
			->with('finance')
			->willReturn($group);
		$this->subAdmin
			->method('isSubAdminOfGroup')
			->with($this->currentUser, $group)
			->willReturn(true);

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

	public function testSetUserPolicyForTargetUserReturnsSavedResolvedPolicy(): void {
		$resolvedPolicy = (new ResolvedPolicy())
			->setPolicyKey('signature_flow')
			->setEffectiveValue('ordered_numeric')
			->setSourceScope('user')
			->setVisible(true)
			->setEditableByCurrentActor(true)
			->setAllowedValues(['none', 'parallel', 'ordered_numeric'])
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
			->method('saveUserPreferenceForUserId')
			->with('signature_flow', 'user1', 'ordered_numeric')
			->willReturn($resolvedPolicy);

		$response = $this->controller->setUserPolicyForUser('user1', 'signature_flow', 'ordered_numeric');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('user', $response->getData()['policy']['sourceScope']);
		$this->assertSame('ordered_numeric', $response->getData()['policy']['effectiveValue']);
	}
}
