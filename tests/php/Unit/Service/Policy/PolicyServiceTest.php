<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy;

use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\ApprovalGroups\ApprovalGroupsPolicy;
use OCA\Libresign\Service\Policy\Provider\Confetti\ConfettiPolicy;
use OCA\Libresign\Service\Policy\Provider\DocMdp\DocMdpPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\IdentifyMethodsPolicy;
use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\IdentifyMethodsPolicyValue;
use OCA\Libresign\Service\Policy\Provider\LegalInformation\LegalInformationPolicy;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicy;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicyValue;
use OCA\Libresign\Service\Policy\Provider\Signature\SignatureFlowPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureText\SignatureTextPolicy;
use OCA\Libresign\Service\Policy\Provider\Worker\SigningModePolicy;
use OCA\Libresign\Service\Policy\Provider\Worker\WorkerConfigPolicy;
use OCA\Libresign\Service\Policy\Runtime\PolicyContextFactory;
use OCA\Libresign\Service\Policy\Runtime\PolicyRegistry;
use OCA\Libresign\Service\Policy\Runtime\PolicySource;
use OCP\Group\ISubAdmin;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class PolicyServiceTest extends TestCase {
	private IdentifyMethodService&MockObject $identifyMethodService;
	private IUserManager&MockObject $userManager;
	private IGroupManager&MockObject $groupManager;
	private ISubAdmin&MockObject $subAdmin;
	private IUserSession&MockObject $userSession;
	private PolicySource&MockObject $source;
	private IL10N&MockObject $l10n;
	private PolicyRegistry $registry;
	private PolicyContextFactory $contextFactory;

	protected function setUp(): void {
		parent::setUp();
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->identifyMethodService->method('getFriendlyNamesMap')->willReturn([
			'account' => 'Account',
			'email' => 'Email',
		]);
		$this->identifyMethodService->method('getDefaultIdentifyMethodsPolicy')->willReturn([
			[
				'name' => 'account',
				'enabled' => true,
				'requirement' => 'required',
				'signatureMethods' => [
					'password' => ['enabled' => true],
				],
				'signatureMethodEnabled' => 'password',
			],
			[
				'name' => 'email',
				'enabled' => false,
				'requirement' => 'optional',
				'signatureMethods' => [
					'emailToken' => ['enabled' => true],
				],
				'signatureMethodEnabled' => 'emailToken',
			],
		]);
		$this->identifyMethodService->method('getIdentifyMethodsCatalogSettings')->willReturn([
			[
				'name' => 'account',
				'friendly_name' => 'Account',
				'enabled' => true,
				'requirement' => 'required',
				'signatureMethods' => [
					'password' => ['enabled' => true],
				],
				'signatureMethodEnabled' => 'password',
			],
			[
				'name' => 'email',
				'friendly_name' => 'Email',
				'enabled' => false,
				'requirement' => 'optional',
				'signatureMethods' => [
					'emailToken' => ['enabled' => true],
				],
				'signatureMethodEnabled' => 'emailToken',
			],
		]);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->subAdmin = $this->createMock(ISubAdmin::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->source = $this->createMock(PolicySource::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(static function (string $text, array $parameters = []): string {
			if ($parameters !== [] && array_is_list($parameters)) {
				return vsprintf($text, array_map(static fn (mixed $value): string => (string)$value, $parameters));
			}

			foreach ($parameters as $key => $value) {
				$text = str_replace('{' . $key . '}', (string)$value, $text);
			}

			return $text;
		});
		$container = $this->createMock(ContainerInterface::class);
		$container
			->method('get')
			->willReturnCallback(function (string $class): object {
				return match ($class) {
					ApprovalGroupsPolicy::class => new ApprovalGroupsPolicy(),
					ConfettiPolicy::class => new ConfettiPolicy(),
					FooterPolicy::class => new FooterPolicy(),
					IdentifyMethodsPolicy::class => new IdentifyMethodsPolicy($this->identifyMethodService),
					LegalInformationPolicy::class => new LegalInformationPolicy(),
					RequestSignGroupsPolicy::class => new RequestSignGroupsPolicy(),
					SignatureFlowPolicy::class => new SignatureFlowPolicy(),
					SignatureTextPolicy::class => new SignatureTextPolicy($this->l10n),
					DocMdpPolicy::class => new DocMdpPolicy(),
					SigningModePolicy::class => new SigningModePolicy(),
					WorkerConfigPolicy::class => new WorkerConfigPolicy(),
					default => throw new \RuntimeException('Unexpected provider class: ' . $class),
				};
			});
		$this->registry = new PolicyRegistry($container, [
			ApprovalGroupsPolicy::class,
			ConfettiPolicy::class,
			FooterPolicy::class,
			IdentifyMethodsPolicy::class,
			LegalInformationPolicy::class,
			RequestSignGroupsPolicy::class,
			SignatureFlowPolicy::class,
			SignatureTextPolicy::class,
			DocMdpPolicy::class,
			SigningModePolicy::class,
			WorkerConfigPolicy::class,
		]);
		$this->contextFactory = new PolicyContextFactory($this->userManager, $this->groupManager, $this->subAdmin, $this->userSession);
	}

	public function testResolveForUserIdUsesDocMdpGroupPolicyWhenSystemAllowsOverride(): void {
		$user = $this->createMock(IUser::class);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('john')
			->willReturn($user);

		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->willReturn(['finance']);

		$this->source
			->method('loadSystemPolicy')
			->with(DocMdpPolicy::KEY)
			->willReturn((new PolicyLayer())
				->setScope('system')
				->setValue(0)
				->setAllowChildOverride(true)
				->setVisibleToChild(true));

		$this->source
			->method('loadGroupPolicies')
			->with(DocMdpPolicy::KEY, $this->callback(static function ($context): bool {
				return $context->getUserId() === 'john' && $context->getGroups() === ['finance'];
			}))
			->willReturn([(new PolicyLayer())
				->setScope('group')
				->setValue(2)
				->setAllowChildOverride(false)
				->setVisibleToChild(true)
				->setAllowedValues([2])]);

		$this->source->method('loadCirclePolicies')->willReturn([]);
		$this->source->method('loadUserPreference')->willReturn(null);
		$this->source->method('loadRequestOverride')->willReturn(null);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$resolved = $service->resolveForUserId(DocMdpPolicy::KEY, 'john');

		$this->assertSame(2, $resolved->getEffectiveValue());
		$this->assertSame('group', $resolved->getSourceScope());
		$this->assertFalse($resolved->canUseAsRequestOverride());
	}

	public function testResolveForUserIdBuildsContextWithGroupsAndRequestOverride(): void {
		$user = $this->createMock(IUser::class);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('john')
			->willReturn($user);

		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->willReturn(['finance']);

		$this->source
			->method('loadSystemPolicy')
			->willReturn((new PolicyLayer())
				->setScope('system')
				->setValue('none')
				->setAllowChildOverride(true)
				->setVisibleToChild(true));

		$this->source
			->method('loadGroupPolicies')
			->willReturn([(new PolicyLayer())
				->setScope('group')
				->setValue('parallel')
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues(['parallel', 'ordered_numeric'])]);

		$this->source->method('loadCirclePolicies')->willReturn([]);
		$this->source->method('loadUserPreference')->willReturn(null);
		$this->source
			->method('loadRequestOverride')
			->willReturn((new PolicyLayer())
				->setScope('request')
				->setValue('ordered_numeric'));

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$resolved = $service->resolveForUserId(SignatureFlowPolicy::KEY, 'john', [SignatureFlowPolicy::KEY => 'ordered_numeric']);

		$this->assertInstanceOf(ResolvedPolicy::class, $resolved);
		$this->assertSame('ordered_numeric', $resolved->getEffectiveValue());
		$this->assertSame('request', $resolved->getSourceScope());
	}

	public function testResolveForUserIdWithoutUserFallsBackToSystem(): void {
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('ghost')
			->willReturn(null);

		$this->groupManager
			->expects($this->never())
			->method('getUserGroupIds');

		$this->source
			->method('loadSystemPolicy')
			->willReturn((new PolicyLayer())
				->setScope('system')
				->setValue('parallel')
				->setAllowChildOverride(false)
				->setVisibleToChild(true)
				->setAllowedValues(['parallel']));

		$this->source->method('loadGroupPolicies')->willReturn([]);
		$this->source->method('loadCirclePolicies')->willReturn([]);
		$this->source->method('loadUserPreference')->willReturn(null);
		$this->source->method('loadRequestOverride')->willReturn(null);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$resolved = $service->resolveForUserId(SignatureFlowPolicy::KEY, 'ghost');

		$this->assertSame('parallel', $resolved->getEffectiveValue());
		$this->assertSame('system', $resolved->getSourceScope());
	}

	public function testResolveUsesCurrentUserFromSession(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('john');

		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->userManager
			->expects($this->never())
			->method('get')
			->with('john');

		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->willReturn(['finance']);

		$this->source
			->method('loadSystemPolicy')
			->willReturn((new PolicyLayer())
				->setScope('system')
				->setValue('none')
				->setAllowChildOverride(true)
				->setVisibleToChild(true));

		$this->source
			->method('loadGroupPolicies')
			->willReturn([(new PolicyLayer())
				->setScope('group')
				->setValue('parallel')
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues(['parallel', 'ordered_numeric'])]);

		$this->source->method('loadCirclePolicies')->willReturn([]);
		$this->source->method('loadUserPreference')->willReturn(null);
		$this->source->method('loadRequestOverride')->willReturn(null);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$resolved = $service->resolve(SignatureFlowPolicy::KEY);

		$this->assertInstanceOf(ResolvedPolicy::class, $resolved);
		$this->assertSame('parallel', $resolved->getEffectiveValue());
		$this->assertSame('group', $resolved->getSourceScope());
	}

	public function testResolveSignatureStampMergesLegacyChildHelperPoliciesIntoComposite(): void {
		$this->source
			->method('loadSystemPolicy')
			->willReturnCallback(static function (string $policyKey): ?PolicyLayer {
				return match ($policyKey) {
					SignatureTextPolicy::KEY_RENDER_MODE => (new PolicyLayer())
						->setScope('global')
						->setValue('description_only')
						->setAllowChildOverride(true)
						->setVisibleToChild(true),
					default => null,
				};
			});

		$this->source->method('loadGroupPolicies')->willReturn([]);
		$this->source->method('loadCirclePolicies')->willReturn([]);
		$this->source->method('loadUserPolicy')->willReturn(null);
		$this->source->method('loadUserPreference')->willReturn(null);
		$this->source->method('loadRequestOverride')->willReturn(null);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$resolved = $service->resolve(SignatureTextPolicy::KEY);
		$effective = json_decode((string)$resolved->getEffectiveValue(), true, flags: JSON_THROW_ON_ERROR);

		$this->assertSame('description_only', $effective['render_mode']);
		$this->assertSame('global', $resolved->getSourceScope());
	}

	public function testResolveWorkerConfigMergesLegacyChildHelperPoliciesIntoComposite(): void {
		$this->source
			->method('loadSystemPolicy')
			->willReturnCallback(static function (string $policyKey): ?PolicyLayer {
				return match ($policyKey) {
					SigningModePolicy::KEY_WORKER_TYPE => (new PolicyLayer())
						->setScope('global')
						->setValue('external')
						->setAllowChildOverride(true)
						->setVisibleToChild(true),
					SigningModePolicy::KEY_PARALLEL_WORKERS => (new PolicyLayer())
						->setScope('global')
						->setValue(8)
						->setAllowChildOverride(true)
						->setVisibleToChild(true),
					default => null,
				};
			});

		$this->source->method('loadGroupPolicies')->willReturn([]);
		$this->source->method('loadCirclePolicies')->willReturn([]);
		$this->source->method('loadUserPolicy')->willReturn(null);
		$this->source->method('loadUserPreference')->willReturn(null);
		$this->source->method('loadRequestOverride')->willReturn(null);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$resolved = $service->resolve(WorkerConfigPolicy::KEY);
		$effective = json_decode((string)$resolved->getEffectiveValue(), true, flags: JSON_THROW_ON_ERROR);

		$this->assertSame('external', $effective['worker_type']);
		$this->assertSame(8, $effective['parallel_workers']);
		$this->assertSame('global', $resolved->getSourceScope());
	}

	public function testSaveUserPolicyForUserIdPersistsForTargetUser(): void {
		$targetUser = $this->createMock(IUser::class);
		$targetUser->method('getUID')->willReturn('user1');

		$persistedPolicy = (new PolicyLayer())
			->setScope('user_policy')
			->setValue('ordered_numeric')
			->setAllowChildOverride(true);

		$this->userManager
			->expects($this->once())
			->method('get')
			->with('user1')
			->willReturn($targetUser);

		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($targetUser)
			->willReturn(['finance']);

		$this->source
			->expects($this->once())
			->method('saveUserPolicy')
			->with(
				SignatureFlowPolicy::KEY,
				$this->callback(static function ($context): bool {
					return $context->getUserId() === 'user1';
				}),
				'ordered_numeric',
				true,
			);

		$this->source
			->method('loadSystemPolicy')
			->willReturn((new PolicyLayer())
				->setScope('system')
				->setValue('parallel')
				->setAllowChildOverride(true)
				->setVisibleToChild(true));

		$this->source->method('loadGroupPolicies')->willReturn([]);
		$this->source->method('loadCirclePolicies')->willReturn([]);
		$this->source
			->method('loadUserPolicy')
			->willReturn($persistedPolicy);
		$this->source->method('loadUserPreference')->willReturn(null);
		$this->source->method('loadRequestOverride')->willReturn(null);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$policy = $service->saveUserPolicyForUserId(SignatureFlowPolicy::KEY, 'user1', 'ordered_numeric', true);

		$this->assertInstanceOf(PolicyLayer::class, $policy);
		$this->assertSame($persistedPolicy, $policy);
		$this->assertSame('ordered_numeric', $policy->getValue());
		$this->assertSame('user_policy', $policy->getScope());
		$this->assertTrue($policy->isAllowChildOverride());
	}

	public function testSaveUserPolicyForUserIdAllowsExplicitAssignmentWhenGroupBlocksUsers(): void {
		$targetUser = $this->createMock(IUser::class);
		$targetUser->method('getUID')->willReturn('user1');

		$persistedPolicy = (new PolicyLayer())
			->setScope('user_policy')
			->setValue('ordered_numeric')
			->setAllowChildOverride(false);

		$actor = $this->createMock(IUser::class);
		$actor->method('getUID')->willReturn('admin');
		$this->userSession
			->method('getUser')
			->willReturn($actor);

		$this->userManager
			->expects($this->once())
			->method('get')
			->with('user1')
			->willReturn($targetUser);

		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($targetUser)
			->willReturn(['finance']);

		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$this->source
			->expects($this->once())
			->method('saveUserPolicy')
			->with(
				SignatureFlowPolicy::KEY,
				$this->callback(static function ($context): bool {
					return $context->getUserId() === 'user1';
				}),
				'ordered_numeric',
				false,
			);

		$this->source
			->method('loadSystemPolicy')
			->willReturn((new PolicyLayer())
				->setScope('system')
				->setValue('none')
				->setAllowChildOverride(true)
				->setVisibleToChild(true));

		$this->source
			->method('loadGroupPolicies')
			->willReturn([(new PolicyLayer())
				->setScope('group')
				->setValue('ordered_numeric')
				->setAllowChildOverride(false)
				->setVisibleToChild(true)
				->setAllowedValues(['ordered_numeric'])]);

		$this->source->method('loadCirclePolicies')->willReturn([]);
		$this->source
			->method('loadUserPolicy')
			->willReturn($persistedPolicy);
		$this->source->method('loadUserPreference')->willReturn(null);
		$this->source->method('loadRequestOverride')->willReturn(null);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$policy = $service->saveUserPolicyForUserId(SignatureFlowPolicy::KEY, 'user1', 'ordered_numeric', false);

		$this->assertInstanceOf(PolicyLayer::class, $policy);
		$this->assertSame($persistedPolicy, $policy);
		$this->assertSame('ordered_numeric', $policy->getValue());
		$this->assertSame('user_policy', $policy->getScope());
		$this->assertFalse($policy->isAllowChildOverride());
	}

	public function testSaveUserPolicyForUserIdDoesNotDependOnGroupChildOverride(): void {
		$targetUser = $this->createMock(IUser::class);
		$targetUser->method('getUID')->willReturn('user1');

		$this->userManager
			->expects($this->once())
			->method('get')
			->with('user1')
			->willReturn($targetUser);

		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($targetUser)
			->willReturn(['finance']);

		$this->source
			->expects($this->once())
			->method('saveUserPolicy')
			->with(
				SignatureFlowPolicy::KEY,
				$this->callback(static function ($context): bool {
					return $context->getUserId() === 'user1';
				}),
				'ordered_numeric',
				false,
			);

		$this->source
			->method('loadSystemPolicy')
			->willReturn((new PolicyLayer())
				->setScope('system')
				->setValue('none')
				->setAllowChildOverride(true)
				->setVisibleToChild(true));

		$this->source
			->method('loadGroupPolicies')
			->willReturn([(new PolicyLayer())
				->setScope('group')
				->setValue('ordered_numeric')
				->setAllowChildOverride(false)
				->setVisibleToChild(true)
				->setAllowedValues(['ordered_numeric'])]);

		$this->source->method('loadCirclePolicies')->willReturn([]);
		$this->source->method('loadUserPolicy')->willReturn((new PolicyLayer())
			->setScope('user_policy')
			->setValue('ordered_numeric')
			->setAllowChildOverride(false));
		$this->source->method('loadUserPreference')->willReturn(null);
		$this->source->method('loadRequestOverride')->willReturn(null);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$policy = $service->saveUserPolicyForUserId(SignatureFlowPolicy::KEY, 'user1', 'ordered_numeric', false);

		$this->assertSame('user_policy', $policy?->getScope());
		$this->assertFalse($policy?->isAllowChildOverride() ?? true);
	}

	public function testSaveUserPreferenceRejectsRequestSignGroupsUserScope(): void {
		$this->source
			->expects($this->never())
			->method('saveUserPreference');

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('User-level scope is not supported for this policy');

		$service->saveUserPreference(RequestSignGroupsPolicy::KEY, '{"allowGroups":["admin"],"denyGroups":[]}');
	}

	public function testClearUserPreferenceRejectsRequestSignGroupsUserScope(): void {
		$this->source
			->expects($this->never())
			->method('clearUserPreference');

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('User-level scope is not supported for this policy');

		$service->clearUserPreference(RequestSignGroupsPolicy::KEY);
	}

	public function testSaveUserPolicyForUserIdRejectsRequestSignGroupsUserScope(): void {
		$this->source
			->expects($this->never())
			->method('saveUserPolicy');

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('User-level scope is not supported for this policy');

		$service->saveUserPolicyForUserId(RequestSignGroupsPolicy::KEY, 'user1', '{"allowGroups":["admin"],"denyGroups":[]}', false);
	}

	public function testClearUserPolicyForUserIdRejectsRequestSignGroupsUserScope(): void {
		$this->source
			->expects($this->never())
			->method('clearUserPolicy');

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('User-level scope is not supported for this policy');

		$service->clearUserPolicyForUserId(RequestSignGroupsPolicy::KEY, 'user1');
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('provideSystemOnlyPolicyKeys')]
	public function testSaveGroupPolicyRejectsSystemOnlyPolicies(string $policyKey): void {
		$this->source
			->expects($this->never())
			->method('saveGroupPolicy');

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Group-level scope is not supported for this policy');

		$service->saveGroupPolicy($policyKey, 'finance', 'async', false);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('provideSystemOnlyPolicyKeys')]
	public function testClearGroupPolicyRejectsSystemOnlyPolicies(string $policyKey): void {
		$this->source
			->expects($this->never())
			->method('clearGroupPolicy');

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Group-level scope is not supported for this policy');

		$service->clearGroupPolicy($policyKey, 'finance');
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('provideSystemOnlyPolicyKeys')]
	public function testSaveUserPreferenceRejectsSystemOnlyPolicies(string $policyKey): void {
		$this->source
			->expects($this->never())
			->method('saveUserPreference');

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('User-level scope is not supported for this policy');

		$service->saveUserPreference($policyKey, 'async');
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('provideSystemOnlyPolicyKeys')]
	public function testSaveUserPolicyForUserIdRejectsSystemOnlyPolicies(string $policyKey): void {
		$this->source
			->expects($this->never())
			->method('saveUserPolicy');

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('User-level scope is not supported for this policy');

		$service->saveUserPolicyForUserId($policyKey, 'user1', 'async', false);
	}

	public function testSaveSystemPersistsAllowChildOverrideWhenEnabled(): void {
		$this->source
			->expects($this->once())
			->method('saveSystemPolicy')
			->with(SignatureFlowPolicy::KEY, 'ordered_numeric', true);

		$this->source
			->method('loadSystemPolicy')
			->willReturn((new PolicyLayer())
				->setScope('system')
				->setValue('ordered_numeric')
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues([]));

		$this->source->method('loadGroupPolicies')->willReturn([]);
		$this->source->method('loadCirclePolicies')->willReturn([]);
		$this->source->method('loadUserPreference')->willReturn(null);
		$this->source->method('loadRequestOverride')->willReturn(null);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$resolved = $service->saveSystem(SignatureFlowPolicy::KEY, 'ordered_numeric', true);

		$this->assertSame('ordered_numeric', $resolved->getEffectiveValue());
		$this->assertSame('system', $resolved->getSourceScope());
	}

	/** @return iterable<string, array{0: string}> */
	public static function provideSystemOnlyPolicyKeys(): iterable {
		yield 'signing_mode card' => [SigningModePolicy::KEY_SIGNING_MODE];
		yield 'worker_config helper' => [WorkerConfigPolicy::KEY];
	}

	public function testSaveSystemIgnoresCurrentActorUserPolicyInReturnedState(): void {
		$admin = $this->createMock(IUser::class);
		$admin->method('getUID')->willReturn('admin');

		$this->userSession
			->method('getUser')
			->willReturn($admin);

		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$this->source
			->expects($this->once())
			->method('saveSystemPolicy')
			->with(SignatureFlowPolicy::KEY, 'ordered_numeric', true);

		$this->source
			->method('loadSystemPolicy')
			->willReturn((new PolicyLayer())
				->setScope('system')
				->setValue('ordered_numeric')
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues([]));

		$this->source->method('loadGroupPolicies')->willReturn([]);
		$this->source->method('loadCirclePolicies')->willReturn([]);
		$this->source->method('loadRequestOverride')->willReturn(null);
		$this->source
			->method('loadUserPreference')
			->willReturnCallback(static function (string $policyKey, $context): ?PolicyLayer {
				if ($policyKey !== SignatureFlowPolicy::KEY) {
					return null;
				}

				if (!is_object($context) || !method_exists($context, 'getUserId')) {
					return null;
				}

				return $context->getUserId() === 'admin'
					? (new PolicyLayer())
						->setScope('user')
						->setValue('parallel')
						->setAllowChildOverride(false)
					: null;
			});

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$resolved = $service->saveSystem(SignatureFlowPolicy::KEY, 'ordered_numeric', true);

		$this->assertSame('ordered_numeric', $resolved->getEffectiveValue());
		$this->assertSame('system', $resolved->getSourceScope());
	}

	public function testResolveKnownPolicyStatesForUserIdWithoutUserScopeUsesRequesterGroupsButIgnoresUserPolicy(): void {
		$requester = $this->createMock(IUser::class);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('admin')
			->willReturn($requester);

		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($requester)
			->willReturn(['admin']);

		$this->source
			->method('loadSystemPolicy')
			->willReturnCallback(static function (string $policyKey): ?PolicyLayer {
				if ($policyKey !== LegalInformationPolicy::KEY) {
					return null;
				}

				return (new PolicyLayer())
					->setScope('system')
					->setValue('System legal copy')
					->setAllowChildOverride(true)
					->setVisibleToChild(true);
			});

		$this->source
			->method('loadAllGroupPolicies')
			->willReturn([
				LegalInformationPolicy::KEY => [(new PolicyLayer())
					->setScope('group')
					->setValue('Admin group legal copy')
					->setAllowChildOverride(true)
					->setVisibleToChild(true)
					->setAllowedValues([])],
			]);

		$this->source->method('loadCirclePolicies')->willReturn([]);
		$this->source->method('loadRequestOverride')->willReturn(null);
		$this->source->method('loadAllUserPolicies')->willReturn([]);
		$this->source->method('loadAllUserPreferences')->willReturn([]);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$states = $service->resolveKnownPolicyStatesForUserIdWithoutUserScope('admin');

		$this->assertSame('Admin group legal copy', $states[LegalInformationPolicy::KEY]['effectiveValue']);
		$this->assertSame('group', $states[LegalInformationPolicy::KEY]['sourceScope']);
	}

	public function testClearSystemRemovesExplicitRuleAndReturnsResolvedDefault(): void {
		$this->source
			->expects($this->once())
			->method('clearSystemPolicy')
			->with(SignatureFlowPolicy::KEY);

		$this->source
			->method('loadSystemPolicy')
			->willReturn((new PolicyLayer())
				->setScope('system')
				->setValue('none')
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues(['none', 'parallel', 'ordered_numeric']));

		$this->source->method('loadGroupPolicies')->willReturn([]);
		$this->source->method('loadCirclePolicies')->willReturn([]);
		$this->source->method('loadUserPreference')->willReturn(null);
		$this->source->method('loadRequestOverride')->willReturn(null);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$resolved = $service->clearSystem(SignatureFlowPolicy::KEY);

		$this->assertSame('none', $resolved->getEffectiveValue());
		$this->assertSame('system', $resolved->getSourceScope());
	}

	public function testSaveGroupPolicyBlocksSubAdminWhenGlobalDefaultDisallowsOverrides(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('group-admin');

		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->groupManager
			->method('isAdmin')
			->with('group-admin')
			->willReturn(false);

		$this->source
			->expects($this->once())
			->method('loadSystemPolicy')
			->with(FooterPolicy::KEY)
			->willReturn((new PolicyLayer())
				->setScope('system')
				->setValue('{"enabled":true}')
				->setAllowChildOverride(false)
				->setVisibleToChild(true));

		$this->source
			->expects($this->never())
			->method('saveGroupPolicy');

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$this->expectException(\DomainException::class);
		$this->expectExceptionMessage('Group policy management requires explicit delegation from the system administrator');

		$service->saveGroupPolicy(FooterPolicy::KEY, 'finance', '{"enabled":false}', false);
	}

	public function testClearGroupPolicyBlocksSubAdminWhenGlobalDefaultDisallowsOverrides(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('group-admin');

		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->groupManager
			->method('isAdmin')
			->with('group-admin')
			->willReturn(false);

		$this->source
			->expects($this->once())
			->method('loadGroupPolicyConfig')
			->with(FooterPolicy::KEY, 'finance')
			->willReturn((new PolicyLayer())->setCreatedBySystemAdmin(true));

		$this->source
			->expects($this->never())
			->method('clearGroupPolicy');

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$this->expectException(\DomainException::class);
		$this->expectExceptionMessage('Only system administrators can delete group rules created by a system administrator');

		$service->clearGroupPolicy(FooterPolicy::KEY, 'finance');
	}

	public function testSaveGroupPolicyBlocksSubAdminWithoutExplicitSystemDelegation(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('group-admin');

		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->groupManager
			->method('isAdmin')
			->with('group-admin')
			->willReturn(false);

		$this->source
			->expects($this->once())
			->method('loadSystemPolicy')
			->with(FooterPolicy::KEY)
			->willReturn((new PolicyLayer())
				->setScope('system')
				->setValue('{"enabled":true}')
				->setAllowChildOverride(true)
				->setVisibleToChild(true));

		$this->source
			->expects($this->never())
			->method('saveGroupPolicy');

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$this->expectException(\DomainException::class);
		$this->expectExceptionMessage('Group policy management requires explicit delegation from the system administrator');

		$service->saveGroupPolicy(FooterPolicy::KEY, 'finance', '{"enabled":false}', false);
	}

	public function testSaveRequestSignGroupsAllowsSubAdminWithExplicitSystemDelegationAndMultipleGroups(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('group-admin');

		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->groupManager
			->method('isAdmin')
			->with('group-admin')
			->willReturn(false);

		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->willReturn(['board', 'company']);

		$this->subAdmin
			->expects($this->once())
			->method('isSubAdmin')
			->with($user)
			->willReturn(true);

		$this->source
			->expects($this->once())
			->method('loadSystemPolicy')
			->with(RequestSignGroupsPolicy::KEY)
			->willReturn((new PolicyLayer())
				->setScope('global')
				->setValue(RequestSignGroupsPolicyValue::encode(['board', 'company']))
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues([]));

		$this->source
			->expects($this->once())
			->method('loadGroupPolicies')
			->with(RequestSignGroupsPolicy::KEY, $this->callback(static function ($context): bool {
				$role = $context->getActorRole();

				return $context->getUserId() === 'group-admin'
					&& $context->getGroups() === ['board', 'company']
					&& $role->canManageSystemPolicies === false
					&& $role->canManageGroupPolicies === true
					&& $role->manageableGroupCount === 2;
			}))
			->willReturn([
				(new PolicyLayer())
					->setScope('group')
					->setValue(RequestSignGroupsPolicyValue::encode(['board']))
					->setAllowChildOverride(true)
					->setVisibleToChild(true)
					->setAllowedValues([]),
			]);

		$this->source
			->expects($this->once())
			->method('saveGroupPolicy')
			->with(RequestSignGroupsPolicy::KEY, 'company', RequestSignGroupsPolicyValue::encode(['board', 'company']), true, false);

		$this->source
			->expects($this->exactly(2))
			->method('loadGroupPolicyConfig')
			->with(RequestSignGroupsPolicy::KEY, 'company')
			->willReturnOnConsecutiveCalls(
				null,
				(new PolicyLayer())
					->setScope('group')
					->setValue(RequestSignGroupsPolicyValue::encode(['board', 'company']))
					->setAllowChildOverride(true)
					->setVisibleToChild(true)
					->setAllowedValues([]),
			);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$policy = $service->saveGroupPolicy(RequestSignGroupsPolicy::KEY, 'company', ['board', 'company'], true);

		self::assertInstanceOf(PolicyLayer::class, $policy);
		self::assertSame(RequestSignGroupsPolicyValue::encode(['board', 'company']), $policy->getValue());
	}

	public function testSaveRequestSignGroupsAllowsSubAdminWithSystemGrantAndSingleManagedGroup(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('group-admin');

		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->groupManager
			->method('isAdmin')
			->with('group-admin')
			->willReturn(false);

		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->willReturn(['company']);

		$this->subAdmin
			->expects($this->once())
			->method('isSubAdmin')
			->with($user)
			->willReturn(true);

		$this->source
			->expects($this->once())
			->method('loadSystemPolicy')
			->with(RequestSignGroupsPolicy::KEY)
			->willReturn((new PolicyLayer())
				->setScope('global')
				->setValue(RequestSignGroupsPolicyValue::encode(['company']))
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues([]));

		$this->source
			->expects($this->once())
			->method('loadGroupPolicies')
			->with(RequestSignGroupsPolicy::KEY, $this->callback(static function ($context): bool {
				$role = $context->getActorRole();

				return $context->getUserId() === 'group-admin'
					&& $context->getGroups() === ['company']
					&& $role->canManageSystemPolicies === false
					&& $role->canManageGroupPolicies === true
					&& $role->manageableGroupCount === 1;
			}))
			->willReturn([]);

		$this->source
			->expects($this->once())
			->method('saveGroupPolicy')
			->with(RequestSignGroupsPolicy::KEY, 'company', RequestSignGroupsPolicyValue::encode(['company']), true, false);

		$this->source
			->expects($this->exactly(2))
			->method('loadGroupPolicyConfig')
			->with(RequestSignGroupsPolicy::KEY, 'company')
			->willReturnOnConsecutiveCalls(
				null,
				(new PolicyLayer())
					->setScope('group')
					->setValue(RequestSignGroupsPolicyValue::encode(['company']))
					->setAllowChildOverride(true)
					->setVisibleToChild(true)
					->setAllowedValues([]),
			);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$policy = $service->saveGroupPolicy(RequestSignGroupsPolicy::KEY, 'company', ['company'], true);

		self::assertInstanceOf(PolicyLayer::class, $policy);
		self::assertSame(RequestSignGroupsPolicyValue::encode(['company']), $policy->getValue());
	}

	public function testSaveRequestSignGroupsAllowsSubAdminEditingSystemCreatedDelegationRuleWhenExplicitlyDelegated(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('group-admin');

		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->groupManager
			->method('isAdmin')
			->with('group-admin')
			->willReturn(false);

		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->willReturn(['board', 'company']);

		$this->subAdmin
			->expects($this->once())
			->method('isSubAdmin')
			->with($user)
			->willReturn(true);

		$this->source
			->expects($this->exactly(2))
			->method('loadSystemPolicy')
			->with(RequestSignGroupsPolicy::KEY)
			->willReturn((new PolicyLayer())
				->setScope('global')
				->setValue(RequestSignGroupsPolicyValue::encode(['board', 'company']))
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues([]));

		$this->source
			->expects($this->once())
			->method('loadGroupPolicies')
			->with(RequestSignGroupsPolicy::KEY, $this->anything())
			->willReturn([
				(new PolicyLayer())
					->setScope('group')
					->setValue(RequestSignGroupsPolicyValue::encode(['board']))
					->setAllowChildOverride(true)
					->setVisibleToChild(true)
					->setAllowedValues([]),
			]);

		$this->source
			->expects($this->exactly(2))
			->method('loadGroupPolicyConfig')
			->with(RequestSignGroupsPolicy::KEY, 'company')
			->willReturn((new PolicyLayer())
				->setScope('group')
				->setValue(RequestSignGroupsPolicyValue::encode(['board', 'company']))
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setCreatedBySystemAdmin(true));

		$this->source
			->expects($this->once())
			->method('saveGroupPolicy')
			->with(
				RequestSignGroupsPolicy::KEY,
				'company',
				RequestSignGroupsPolicyValue::encode(['board', 'company']),
				false,
				false,
			);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$policy = $service->saveGroupPolicy(RequestSignGroupsPolicy::KEY, 'company', ['board', 'company'], false);

		self::assertInstanceOf(PolicyLayer::class, $policy);
		self::assertSame(RequestSignGroupsPolicyValue::encode(['board', 'company']), $policy->getValue());
	}

	public function testSaveIdentifyMethodsAllowsSubAdminToSaveDelegatedGroupOverrideWhenSystemCreatedRuleExists(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('group-admin');

		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->groupManager
			->method('isAdmin')
			->with('group-admin')
			->willReturn(false);

		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->willReturn(['board', 'company']);

		$this->subAdmin
			->expects($this->once())
			->method('isSubAdmin')
			->with($user)
			->willReturn(true);

		$systemSeed = IdentifyMethodsPolicyValue::normalize([
			[
				'name' => 'account',
				'enabled' => true,
				'requirement' => 'required',
				'signatureMethods' => [
					'password' => ['enabled' => true],
				],
				'signatureMethodEnabled' => 'password',
			],
			[
				'name' => 'email',
				'enabled' => true,
				'requirement' => 'optional',
				'signatureMethods' => [
					'emailToken' => ['enabled' => true],
				],
				'signatureMethodEnabled' => 'emailToken',
			],
		], $this->identifyMethodService);
		$groupOverride = IdentifyMethodsPolicyValue::normalize([
			[
				'name' => 'account',
				'enabled' => true,
				'requirement' => 'required',
				'signatureMethods' => [
					'password' => ['enabled' => true],
				],
				'signatureMethodEnabled' => 'password',
			],
			[
				'name' => 'email',
				'enabled' => false,
				'requirement' => 'optional',
				'signatureMethods' => [
					'emailToken' => ['enabled' => true],
				],
				'signatureMethodEnabled' => 'emailToken',
			],
		], $this->identifyMethodService);

		$this->source
			->expects($this->exactly(2))
			->method('loadSystemPolicy')
			->with(IdentifyMethodsPolicy::KEY)
			->willReturn((new PolicyLayer())
				->setScope('system')
				->setValue($systemSeed)
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues([]));

		$this->source
			->expects($this->once())
			->method('loadGroupPolicies')
			->with(IdentifyMethodsPolicy::KEY, $this->anything())
			->willReturn([
				(new PolicyLayer())
					->setScope('group')
					->setValue($systemSeed)
					->setAllowChildOverride(true)
					->setVisibleToChild(true)
					->setAllowedValues([])
					->setCreatedBySystemAdmin(true),
			]);

		$this->source
			->expects($this->exactly(2))
			->method('loadGroupPolicyConfig')
			->with(IdentifyMethodsPolicy::KEY, 'board')
			->willReturnOnConsecutiveCalls(
				(new PolicyLayer())
					->setScope('group')
					->setValue($systemSeed)
					->setAllowChildOverride(true)
					->setVisibleToChild(true)
					->setCreatedBySystemAdmin(true),
				(new PolicyLayer())
					->setScope('group')
					->setValue($groupOverride)
					->setAllowChildOverride(false)
					->setVisibleToChild(true)
					->setCreatedBySystemAdmin(false)
					->setDelegatedFromSystemCreatedSeed(true),
			);

		$this->source
			->expects($this->once())
			->method('saveGroupPolicy')
			->with(
				IdentifyMethodsPolicy::KEY,
				'board',
				$groupOverride,
				false,
				false,
			);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$policy = $service->saveGroupPolicy(IdentifyMethodsPolicy::KEY, 'board', [
			[
				'name' => 'account',
				'enabled' => true,
				'requirement' => 'required',
				'signatureMethods' => [
					'password' => ['enabled' => true],
				],
				'signatureMethodEnabled' => 'password',
			],
			[
				'name' => 'email',
				'enabled' => false,
				'requirement' => 'optional',
				'signatureMethods' => [
					'emailToken' => ['enabled' => true],
				],
				'signatureMethodEnabled' => 'emailToken',
			],
		], false);

		self::assertInstanceOf(PolicyLayer::class, $policy);
		self::assertSame($groupOverride, $policy->getValue());
	}

	public function testSaveRequestSignGroupsBlocksSubAdminWithoutExplicitGlobalSystemDelegationEvenWhenGroupRuleExists(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('group-admin');

		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->groupManager
			->method('isAdmin')
			->with('group-admin')
			->willReturn(false);

		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->willReturn(['board', 'company']);

		$this->subAdmin
			->expects($this->once())
			->method('isSubAdmin')
			->with($user)
			->willReturn(true);

		$this->source
			->expects($this->once())
			->method('loadSystemPolicy')
			->with(RequestSignGroupsPolicy::KEY)
			->willReturn((new PolicyLayer())
				->setScope('system')
				->setValue(RequestSignGroupsPolicyValue::encode(['board']))
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues([]));

		$this->source
			->expects($this->once())
			->method('loadGroupPolicies')
			->with(RequestSignGroupsPolicy::KEY, $this->anything())
			->willReturn([
				(new PolicyLayer())
					->setScope('group')
					->setValue(RequestSignGroupsPolicyValue::encode(['board']))
					->setAllowChildOverride(true)
					->setVisibleToChild(true)
					->setAllowedValues([]),
			]);

		$this->source
			->expects($this->never())
			->method('saveGroupPolicy');

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$this->expectException(\DomainException::class);
		$this->expectExceptionMessage('Group policy management requires explicit delegation from the system administrator');

		$service->saveGroupPolicy(RequestSignGroupsPolicy::KEY, 'company', ['board', 'company'], true);
	}

	public function testSaveRequestSignGroupsAllowsSubAdminToSaveDenyOverrideWhenSystemCreatedGroupRuleExists(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('group-admin');

		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->groupManager
			->method('isAdmin')
			->with('group-admin')
			->willReturn(false);

		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->willReturn(['board']);

		$this->subAdmin
			->expects($this->once())
			->method('isSubAdmin')
			->with($user)
			->willReturn(true);

		$this->source
			->expects($this->exactly(2))
			->method('loadSystemPolicy')
			->with(RequestSignGroupsPolicy::KEY)
			->willReturn((new PolicyLayer())
				->setScope('system')
				->setValue(RequestSignGroupsPolicyValue::encode(['board']))
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues([]));

		$this->source
			->expects($this->once())
			->method('loadGroupPolicies')
			->with(RequestSignGroupsPolicy::KEY, $this->anything())
			->willReturn([
				(new PolicyLayer())
					->setScope('group')
					->setValue(RequestSignGroupsPolicyValue::encode(['board']))
					->setAllowChildOverride(true)
					->setVisibleToChild(true)
					->setAllowedValues([])
					->setCreatedBySystemAdmin(true)
			]);

		$this->source
			->expects($this->exactly(2))
			->method('loadGroupPolicyConfig')
			->with(RequestSignGroupsPolicy::KEY, 'board')
			->willReturnOnConsecutiveCalls(
				(new PolicyLayer())
					->setScope('group')
					->setValue(RequestSignGroupsPolicyValue::encode(['board']))
					->setAllowChildOverride(true)
					->setVisibleToChild(true)
					->setAllowedValues([])
					->setCreatedBySystemAdmin(true),
				(new PolicyLayer())
					->setScope('group')
					->setValue(RequestSignGroupsPolicyValue::encode([
						'allowGroups' => ['board'],
						'denyGroups' => ['board'],
					]))
					->setAllowChildOverride(false)
					->setVisibleToChild(true)
					->setAllowedValues([
						RequestSignGroupsPolicyValue::encode([
							'allowGroups' => ['board'],
							'denyGroups' => ['board'],
						]),
					])
					->setCreatedBySystemAdmin(false)
			);

		$this->source
			->expects($this->once())
			->method('saveGroupPolicy')
			->with(
				RequestSignGroupsPolicy::KEY,
				'board',
				RequestSignGroupsPolicyValue::encode([
					'allowGroups' => ['board'],
					'denyGroups' => ['board'],
				]),
				false,
				false,
			);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$policy = $service->saveGroupPolicy(RequestSignGroupsPolicy::KEY, 'board', [
			'allowGroups' => ['board'],
			'denyGroups' => ['board'],
		], false);

		self::assertInstanceOf(PolicyLayer::class, $policy);
		self::assertSame(
			RequestSignGroupsPolicyValue::encode([
				'allowGroups' => ['board'],
				'denyGroups' => ['board'],
			]),
			$policy->getValue(),
		);
	}

	public function testCanViewRequestSignGroupsBlocksSystemCreatedDelegationRuleForSubAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('group-admin');

		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->groupManager
			->method('isAdmin')
			->with('group-admin')
			->willReturn(false);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		self::assertFalse($service->canViewGroupPolicy(
			RequestSignGroupsPolicy::KEY,
			'company',
			(new PolicyLayer())
				->setCreatedBySystemAdmin(true)
		));
	}

	public function testCanViewRequestSignGroupsBlocksSystemCreatedDelegationRuleEvenForDelegatedSubAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('group-admin');

		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->groupManager
			->method('isAdmin')
			->with('group-admin')
			->willReturn(false);

		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->willReturn(['board']);

		$this->subAdmin
			->expects($this->once())
			->method('isSubAdmin')
			->with($user)
			->willReturn(true);

		$this->source
			->expects($this->once())
			->method('loadSystemPolicy')
			->with(RequestSignGroupsPolicy::KEY)
			->willReturn((new PolicyLayer())
				->setScope('system')
				->setValue(RequestSignGroupsPolicyValue::encode(['board']))
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues([]));

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		self::assertFalse($service->canViewGroupPolicy(
			RequestSignGroupsPolicy::KEY,
			'board',
			(new PolicyLayer())
				->setValue(RequestSignGroupsPolicyValue::encode(['board']))
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setCreatedBySystemAdmin(true)
		));
	}

	public function testCanViewRequestSignGroupsAllowsGroupCreatedDelegationRuleForSubAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('group-admin');

		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->groupManager
			->method('isAdmin')
			->with('group-admin')
			->willReturn(false);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		self::assertTrue($service->canViewGroupPolicy(
			RequestSignGroupsPolicy::KEY,
			'company',
			(new PolicyLayer())
				->setCreatedBySystemAdmin(false)
		));
	}

	public function testCanViewConfettiBlocksSystemCreatedDelegationRuleWithoutExplicitSystemDelegation(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('group-admin');

		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->groupManager
			->method('isAdmin')
			->with('group-admin')
			->willReturn(false);

		$this->source
			->expects($this->once())
			->method('loadSystemPolicy')
			->with(ConfettiPolicy::KEY)
			->willReturn(null);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		self::assertFalse($service->canViewGroupPolicy(
			ConfettiPolicy::KEY,
			'company',
			(new PolicyLayer())
				->setCreatedBySystemAdmin(true)
		));
	}

	public function testCanViewConfettiAllowsSystemCreatedDelegationRuleWithExplicitSystemDelegation(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('group-admin');

		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->groupManager
			->method('isAdmin')
			->with('group-admin')
			->willReturn(false);

		$this->source
			->expects($this->once())
			->method('loadSystemPolicy')
			->with(ConfettiPolicy::KEY)
			->willReturn((new PolicyLayer())
				->setScope('global')
				->setValue(true)
				->setAllowChildOverride(true)
				->setVisibleToChild(true));

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		self::assertTrue($service->canViewGroupPolicy(
			ConfettiPolicy::KEY,
			'company',
			(new PolicyLayer())
				->setCreatedBySystemAdmin(true)
		));
	}

	public function testCountVisibleGroupPoliciesForTargetsSkipsHiddenRequestAccessRules(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('group-admin');

		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->groupManager
			->method('isAdmin')
			->with('group-admin')
			->willReturn(false);

		$visiblePolicy = (new PolicyLayer())
			->setCreatedBySystemAdmin(false);
		$hiddenPolicy = (new PolicyLayer())
			->setCreatedBySystemAdmin(true);

		$this->source
			->expects($this->once())
			->method('listGroupPoliciesByKeyForTargets')
			->with(RequestSignGroupsPolicy::KEY, ['board', 'company'])
			->willReturn([
				['targetId' => 'board', 'policy' => $visiblePolicy],
				['targetId' => 'company', 'policy' => $hiddenPolicy],
			]);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		self::assertSame(1, $service->countVisibleGroupPoliciesForTargets(
			RequestSignGroupsPolicy::KEY,
			['board', 'company'],
		));
	}

	public function testClearGroupPolicyBlocksSubAdminWithoutExplicitSystemDelegation(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('group-admin');

		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->groupManager
			->method('isAdmin')
			->with('group-admin')
			->willReturn(false);

		$this->source
			->expects($this->once())
			->method('loadGroupPolicyConfig')
			->with(FooterPolicy::KEY, 'finance')
			->willReturn((new PolicyLayer())->setCreatedBySystemAdmin(true));

		$this->source
			->expects($this->never())
			->method('clearGroupPolicy');

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$this->expectException(\DomainException::class);
		$this->expectExceptionMessage('Only system administrators can delete group rules created by a system administrator');

		$service->clearGroupPolicy(FooterPolicy::KEY, 'finance');
	}

	public function testClearGroupPolicyAllowsSubAdminToDeleteRuleCreatedByGroupAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('group-admin');

		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->groupManager
			->method('isAdmin')
			->with('group-admin')
			->willReturn(false);

		$this->source
			->expects($this->exactly(2))
			->method('loadGroupPolicyConfig')
			->with(FooterPolicy::KEY, 'finance')
			->willReturnOnConsecutiveCalls(
				(new PolicyLayer())->setCreatedBySystemAdmin(false),
				null,
			);

		$this->source
			->expects($this->once())
			->method('clearGroupPolicy')
			->with(FooterPolicy::KEY, 'finance', true);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		self::assertNull($service->clearGroupPolicy(FooterPolicy::KEY, 'finance'));
	}

	public function testSaveUserPreferenceRejectsValidationSiteOverrideForRegularUser(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('john');

		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->groupManager
			->method('isAdmin')
			->with('john')
			->willReturn(false);

		$this->subAdmin
			->method('isSubAdmin')
			->with($user)
			->willReturn(false);

		$this->groupManager
			->method('getUserGroupIds')
			->with($user)
			->willReturn([]);

		$this->source
			->method('loadSystemPolicy')
			->with(FooterPolicy::KEY)
			->willReturn((new PolicyLayer())
				->setScope('system')
				->setValue('{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":false,"footerTemplate":""}')
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues([]));

		$this->source->method('loadGroupPolicies')->willReturn([]);
		$this->source->method('loadCirclePolicies')->willReturn([]);
		$this->source->method('loadUserPreference')->willReturn(null);
		$this->source->method('loadRequestOverride')->willReturn(null);

		$this->source
			->expects($this->never())
			->method('saveUserPreference');

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Validation URL override is not allowed for this actor');

		$service->saveUserPreference(FooterPolicy::KEY, '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"https://forbidden.example","customizeFooterTemplate":false,"footerTemplate":""}');
	}

	public function testGetAllRuleCountsDelegatesToSource(): void {
		$expected = [
			'signature_flow' => ['groupCount' => 2, 'userCount' => 5],
			'docmdp' => ['groupCount' => 0, 'userCount' => 0],
		];

		$this->source
			->expects($this->once())
			->method('loadAllRuleCounts')
			->willReturn($expected);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$result = $service->getAllRuleCounts();

		$this->assertSame($expected, $result);
	}

	public function testResolveKnownPolicyStatesSerializesResolvedPolicies(): void {
		$resolvedPolicy = (new ResolvedPolicy())
			->setPolicyKey('signature_flow')
			->setEffectiveValue('parallel')
			->setInheritedValue('none')
			->setSourceScope('group')
			->setVisible(true)
			->setEditableByCurrentActor(true)
			->setAllowedValues(['parallel', 'ordered_numeric'])
			->setCanSaveAsUserDefault(true)
			->setCanUseAsRequestOverride(true)
			->setPreferenceWasCleared(false)
			->setBlockedBy(null);

		/** @var PolicyService&MockObject $service */
		$service = $this->getMockBuilder(PolicyService::class)
			->setConstructorArgs([
				$this->contextFactory,
				$this->source,
				$this->registry,
				$this->l10n,
			])
			->onlyMethods(['resolveKnownPolicies'])
			->getMock();

		$service
			->expects($this->once())
			->method('resolveKnownPolicies')
			->with([], null)
			->willReturn(['signature_flow' => $resolvedPolicy]);

		$result = $service->resolveKnownPolicyStates();

		$this->assertSame([
			'signature_flow' => [
				'policyKey' => 'signature_flow',
				'effectiveValue' => 'parallel',
				'inheritedValue' => 'none',
				'sourceScope' => 'group',
				'visible' => true,
				'editableByCurrentActor' => true,
				'allowedValues' => ['parallel', 'ordered_numeric'],
				'canSaveAsUserDefault' => true,
				'canUseAsRequestOverride' => true,
				'preferenceWasCleared' => false,
				'blockedBy' => null,
			],
		], $result);
	}

	/**
	 * @return iterable<string, array{?PolicyLayer, list<PolicyLayer>, ?PolicyLayer, string, string}>
	 */
	public static function provideLegalInformationResolutionCases(): iterable {
		yield 'user policy overrides group and global' => [
			(new PolicyLayer())
				->setScope('global')
				->setValue('# Global legal copy')
				->setAllowChildOverride(true)
				->setVisibleToChild(true),
			[(new PolicyLayer())
				->setScope('group')
				->setValue('## Group legal copy')
				->setAllowChildOverride(true)
				->setVisibleToChild(true)],
			(new PolicyLayer())
				->setScope('user_policy')
				->setValue('### User legal copy')
				->setAllowChildOverride(true)
				->setVisibleToChild(true),
			'### User legal copy',
			'user_policy',
		];

		yield 'group policy overrides global when user policy missing' => [
			(new PolicyLayer())
				->setScope('global')
				->setValue('# Global legal copy')
				->setAllowChildOverride(true)
				->setVisibleToChild(true),
			[(new PolicyLayer())
				->setScope('group')
				->setValue('## Group legal copy')
				->setAllowChildOverride(true)
				->setVisibleToChild(true)],
			null,
			'## Group legal copy',
			'group',
		];

		yield 'global policy is used as fallback' => [
			(new PolicyLayer())
				->setScope('global')
				->setValue('# Global legal copy')
				->setAllowChildOverride(true)
				->setVisibleToChild(true),
			[],
			null,
			'# Global legal copy',
			'global',
		];
	}

	/**
	 * @dataProvider provideLegalInformationResolutionCases
	 * @param list<PolicyLayer> $groupPolicies
	 */
	public function testResolveKnownPolicyStatesForUserIdResolvesRequesterLegalInformation(
		?PolicyLayer $systemPolicy,
		array $groupPolicies,
		?PolicyLayer $userPolicy,
		string $expectedValue,
		string $expectedScope,
	): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('requester');

		$this->userManager
			->expects($this->once())
			->method('get')
			->with('requester')
			->willReturn($user);

		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->willReturn(['admin']);

		$this->source
			->method('loadSystemPolicy')
			->willReturnMap([
				[LegalInformationPolicy::KEY, $systemPolicy],
			]);

		$this->source
			->method('loadAllGroupPolicies')
			->willReturnCallback(static function (array $policyKeys, $context) use ($groupPolicies): array {
				if ($context->getUserId() !== 'requester' || !in_array(LegalInformationPolicy::KEY, $policyKeys, true)) {
					return [];
				}

				return [
					LegalInformationPolicy::KEY => $groupPolicies,
				];
			});

		$this->source
			->method('loadAllUserPolicies')
			->willReturnCallback(static function (array $policyKeys, $context) use ($userPolicy): array {
				if ($context->getUserId() !== 'requester' || !in_array(LegalInformationPolicy::KEY, $policyKeys, true) || $userPolicy === null) {
					return [];
				}

				return [
					LegalInformationPolicy::KEY => $userPolicy,
				];
			});

		$this->source->method('loadAllUserPreferences')->willReturn([]);
		$this->source->method('loadRequestOverride')->willReturn(null);

		$service = new PolicyService(
			$this->contextFactory,
			$this->source,
			$this->registry,
			$this->l10n,
		);

		$result = $service->resolveKnownPolicyStatesForUserId('requester');

		$this->assertSame($expectedValue, $result[LegalInformationPolicy::KEY]['effectiveValue']);
		$this->assertSame($expectedScope, $result[LegalInformationPolicy::KEY]['sourceScope']);
	}

	public function testResolveKnownPolicyStatesPreservesResolvedMetaWhenPresent(): void {
		$resolvedPolicy = (new ResolvedPolicy())
			->setPolicyKey('signature_stamp')
			->setEffectiveValue('parallel')
			->setInheritedValue('none')
			->setSourceScope('group')
			->setVisible(true)
			->setEditableByCurrentActor(true)
			->setAllowedValues([])
			->setCanSaveAsUserDefault(true)
			->setCanUseAsRequestOverride(true)
			->setPreferenceWasCleared(false)
			->setBlockedBy(null)
			->setMeta(['defaultSystemValue' => 'canonical']);

		/** @var PolicyService&MockObject $service */
		$service = $this->getMockBuilder(PolicyService::class)
			->setConstructorArgs([
				$this->contextFactory,
				$this->source,
				$this->registry,
				$this->l10n,
			])
			->onlyMethods(['resolveKnownPolicies'])
			->getMock();

		$service
			->expects($this->once())
			->method('resolveKnownPolicies')
			->with([], null)
			->willReturn(['signature_stamp' => $resolvedPolicy]);

		$result = $service->resolveKnownPolicyStates();

		$this->assertSame(['defaultSystemValue' => 'canonical'], $result['signature_stamp']['meta']);
	}

	public function testResolveKnownPolicyStatesWithRuleCountsEmbedsCounters(): void {
		$resolvedPolicy = (new ResolvedPolicy())
			->setPolicyKey('signature_flow')
			->setEffectiveValue('ordered_numeric')
			->setInheritedValue(null)
			->setSourceScope('system')
			->setVisible(true)
			->setEditableByCurrentActor(true)
			->setAllowedValues(['ordered_numeric'])
			->setCanSaveAsUserDefault(false)
			->setCanUseAsRequestOverride(false)
			->setPreferenceWasCleared(false)
			->setBlockedBy(null);

		/** @var PolicyService&MockObject $service */
		$service = $this->getMockBuilder(PolicyService::class)
			->setConstructorArgs([
				$this->contextFactory,
				$this->source,
				$this->registry,
				$this->l10n,
			])
			->onlyMethods(['resolveKnownPolicies'])
			->getMock();

		$service
			->expects($this->once())
			->method('resolveKnownPolicies')
			->with([], null)
			->willReturn(['signature_flow' => $resolvedPolicy]);

		$result = $service->resolveKnownPolicyStatesWithRuleCounts([
			'signature_flow' => ['groupCount' => 3, 'userCount' => 7, 'everyoneCount' => 1],
		]);

		$this->assertSame([
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
		], $result);
	}

}
