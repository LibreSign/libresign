<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\AccountCertificateService;
use OCA\Libresign\Service\File\AccountSettingsProvider;
use OCA\Libresign\Service\IdDocsPolicyService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyAuthorizationService;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Validation\IdentityDocumentValidator;
use OCP\Accounts\IAccount;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\IAccountProperty;
use OCP\Config\IUserConfig;
use OCP\Group\ISubAdmin;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class AccountSettingsProviderTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IAccountManager|MockObject $accountManager;
	private IdDocsPolicyService|MockObject $idDocsPolicyService;
	private AccountCertificateService|MockObject $accountCertificateService;

	private IUserConfig&MockObject $userConfig;
	private IGroupManager&MockObject $groupManager;
	private PolicyAuthorizationService $policyAuthorizationService;
	private IdentityDocumentValidator&MockObject $identityDocumentValidator;
	private ISubAdmin&MockObject $subAdmin;
	private PolicyService&MockObject $policyService;

	public function setUp(): void {
		parent::setUp();
		$this->userConfig = $this->createMock(IUserConfig::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->identityDocumentValidator = $this->createMock(IdentityDocumentValidator::class);
		$this->subAdmin = $this->createMock(ISubAdmin::class);
		$this->policyService = $this->createMock(PolicyService::class);
		$this->policyAuthorizationService = new PolicyAuthorizationService($this->groupManager, $this->subAdmin, $this->policyService);
		$this->accountManager = $this->createMock(IAccountManager::class);
		$this->idDocsPolicyService = $this->createMock(IdDocsPolicyService::class);
		$this->accountCertificateService = $this->createMock(AccountCertificateService::class);
	}

	#[DataProvider('provideCertificateAvailability')]
	public function testCertificateAvailabilityDelegatesToCertificateService(bool $hasUser, bool $hasCertificate): void {
		$user = $hasUser ? $this->createMock(IUser::class) : null;
		$this->accountCertificateService->expects($this->once())->method('hasSignatureFile')->with($user)->willReturn($hasCertificate);
		$this->assertSame($hasCertificate, $this->getService()->hasSignatureFile($user));
	}

	public static function provideCertificateAvailability(): array {
		return [
			'anonymous' => [false, false],
			'no certificate' => [true, false],
			'certificate present' => [true, true],
		];
	}

	private function getService(): AccountSettingsProvider {
		return new AccountSettingsProvider(
			$this->accountManager,
			$this->idDocsPolicyService,
			$this->accountCertificateService,
			$this->userConfig,
			$this->groupManager,
			$this->policyAuthorizationService,
			$this->identityDocumentValidator,
		);
	}

	public function testGetPhoneNumber(): void {
		$user = $this->createMock(IUser::class);

		$accountProperty = $this->createMock(IAccountProperty::class);
		$accountProperty->method('getValue')->willReturn('123456789');

		$account = $this->createMock(IAccount::class);
		$account->method('getProperty')->with(IAccountManager::PROPERTY_PHONE)->willReturn($accountProperty);

		$this->accountManager->method('getAccount')->with($user)->willReturn($account);

		$service = $this->getService();
		$result = $service->getPhoneNumber($user);

		$this->assertEquals('123456789', $result);
	}

	public function testGetPhoneNumberWithoutUserDoesNotLoadAccount(): void {
		$this->accountManager->expects($this->never())->method('getAccount');
		$this->assertSame('', $this->getService()->getPhoneNumber(null));
	}

	#[DataProvider('provideFileListPreferences')]
	public function testGetConfigFiltersAndSortingPreserveStoredValues(bool $hasUser, array $stored, array $expectedFilters, array $expectedSorting): void {
		$user = null;
		if ($hasUser) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('preference-owner');
			$this->userConfig->expects($this->exactly(4))->method('getValueString')
				->willReturnCallback(static function (string $uid, string $appId, string $key) use ($stored): string {
					self::assertSame('preference-owner', $uid);
					self::assertSame(Application::APP_ID, $appId);
					self::assertArrayHasKey($key, $stored);
					return $stored[$key];
				});
		} else {
			$this->userConfig->expects($this->never())->method('getValueString');
		}
		$service = $this->getService();
		$this->assertSame($expectedFilters, $service->getConfigFilters($user));
		$this->assertSame($expectedSorting, $service->getConfigSorting($user));
	}

	public static function provideFileListPreferences(): array {
		$emptyFilters = ['files_list_filter_modified' => '', 'files_list_filter_status' => ''];
		$emptySorting = ['files_list_sorting_mode' => '', 'files_list_sorting_direction' => ''];
		$defaultSorting = ['files_list_sorting_mode' => 'name', 'files_list_sorting_direction' => 'asc'];
		$storedFilters = ['files_list_filter_modified' => 'last_week', 'files_list_filter_status' => 'signed'];
		$storedSorting = ['files_list_sorting_mode' => 'size', 'files_list_sorting_direction' => 'desc'];
		$zeroFilters = array_fill_keys(array_keys($emptyFilters), '0');
		$zeroSorting = array_fill_keys(array_keys($emptySorting), '0');
		return [
			'anonymous defaults' => [false, [], $emptyFilters, $defaultSorting],
			'user defaults' => [true, $emptyFilters + $emptySorting, $emptyFilters, $defaultSorting],
			'stored preferences' => [true, $storedFilters + $storedSorting, $storedFilters, $storedSorting],
			'zero filters retained and sorting falls back' => [true, $zeroFilters + $zeroSorting, $zeroFilters, $defaultSorting],
		];
	}

	public static function providerGetSettings(): array {
		return [
			'user in authorized group with signature file' => [
				'hasUser' => true,
				'approvalGroups' => ['admin', 'users'],
				'userGroups' => ['users', 'editors'],
				'hasPfx' => true,
				'expectedCanRequestSign' => true,
				'expectedHasSignatureFile' => true,
				'expectedIsApprover' => true,
			],
			'user not in authorized group with signature file' => [
				'hasUser' => true,
				'approvalGroups' => ['admin'],
				'userGroups' => ['users', 'editors'],
				'hasPfx' => true,
				'expectedCanRequestSign' => false,
				'expectedHasSignatureFile' => true,
				'expectedIsApprover' => false,
			],
			'user in authorized group without signature file' => [
				'hasUser' => true,
				'approvalGroups' => ['users'],
				'userGroups' => ['users'],
				'hasPfx' => false,
				'expectedCanRequestSign' => true,
				'expectedHasSignatureFile' => false,
				'expectedIsApprover' => true,
			],
			'null user returns all false' => [
				'hasUser' => false,
				'approvalGroups' => [],
				'userGroups' => [],
				'hasPfx' => false,
				'expectedCanRequestSign' => false,
				'expectedHasSignatureFile' => false,
				'expectedIsApprover' => false,
			],
			'empty approval groups' => [
				'hasUser' => true,
				'approvalGroups' => [],
				'userGroups' => ['users'],
				'hasPfx' => true,
				'expectedCanRequestSign' => false,
				'expectedHasSignatureFile' => true,
				'expectedIsApprover' => false,
			],
			'user in one of multiple approval groups without signature' => [
				'hasUser' => true,
				'approvalGroups' => ['admin', 'approvers'],
				'userGroups' => ['approvers'],
				'hasPfx' => false,
				'expectedCanRequestSign' => true,
				'expectedHasSignatureFile' => false,
				'expectedIsApprover' => true,
			],
			'user in multiple matching groups' => [
				'hasUser' => true,
				'approvalGroups' => ['admin', 'managers'],
				'userGroups' => ['admin', 'managers', 'users'],
				'hasPfx' => true,
				'expectedCanRequestSign' => true,
				'expectedHasSignatureFile' => true,
				'expectedIsApprover' => true,
			],
		];
	}

	#[DataProvider('providerGetSettings')]
	public function testGetSettings(
		bool $hasUser,
		array $approvalGroups,
		array $userGroups,
		bool $hasPfx,
		bool $expectedCanRequestSign,
		bool $expectedHasSignatureFile,
		bool $expectedIsApprover,
	): void {
		$user = $hasUser ? $this->createMock(IUser::class) : null;
		$this->accountCertificateService->method('hasSignatureFile')->with($user)->willReturn($hasPfx);
		$this->idDocsPolicyService->method('userCanApproveValidationDocuments')
			->with($user, false)->willReturn($expectedIsApprover);
		$this->assertSame([
			'canRequestSign' => $expectedCanRequestSign,
			'hasSignatureFile' => $expectedHasSignatureFile,
			'isApprover' => $expectedIsApprover,
		], $this->getService()->getSettings($user));
	}

	public function testGetConfigSetsCanManageGroupPoliciesForSubAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('subadmin-user');

		$this->groupManager->method('isAdmin')->with('subadmin-user')->willReturn(false);
		$this->subAdmin->method('isSubAdmin')->with($user)->willReturn(true);

		$config = $this->getService()->getConfig($user);

		$this->assertArrayHasKey('can_manage_group_policies', $config);
		$this->assertTrue($config['can_manage_group_policies']);
	}

	public function testGetConfigIncludesPolicyWorkbenchCatalogCompactViewPreference(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('preference-user');

		$this->userConfig
			->expects($this->atLeastOnce())
			->method('getValueString')
			->willReturnCallback(static function (string $uid, string $appId, string $key, string $default = ''): string {
				if ($uid === 'preference-user'
					&& $appId === Application::APP_ID
					&& $key === 'policy_workbench_catalog_compact_view') {
					return '1';
				}

				return $default;
			});

		$config = $this->getService()->getConfig($user);

		$this->assertArrayHasKey('policy_workbench_catalog_compact_view', $config);
		$this->assertTrue($config['policy_workbench_catalog_compact_view']);
	}

	public function testGetConfigIncludesPolicyWorkbenchCollapsedPreferences(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('preference-user');

		$storedCollapsedState = [
			'who-can-sign' => true,
			'how-signing-works' => true,
			'signer-experience' => false,
			'what-gets-recorded' => false,
			'time-and-limits' => true,
			'trust-and-verification' => false,
			'system-behavior' => true,
		];

		$this->userConfig
			->expects($this->atLeastOnce())
			->method('getValueString')
			->willReturnCallback(static function (string $uid, string $appId, string $key, string $default = '') use ($storedCollapsedState): string {
				if ($uid !== 'preference-user' || $appId !== Application::APP_ID) {
					return $default;
				}

				if ($key === 'policy_workbench_catalog_collapsed') {
					return '1';
				}

				if ($key === 'policy_workbench_category_collapsed_state') {
					return json_encode($storedCollapsedState);
				}

				return $default;
			});

		$config = $this->getService()->getConfig($user);

		$this->assertArrayHasKey('policy_workbench_catalog_collapsed', $config);
		$this->assertTrue($config['policy_workbench_catalog_collapsed']);
		$this->assertArrayHasKey('policy_workbench_category_collapsed_state', $config);
		$this->assertSame($storedCollapsedState, $config['policy_workbench_category_collapsed_state']);
	}

	#[DataProvider('provideWarnWithoutVisibleSignatureFieldsCases')]
	public function testGetConfigIncludesWarnWithoutVisibleSignatureFieldsPreference(string $storedValue, bool $expected): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('preference-user');

		$this->userConfig
			->expects($this->atLeastOnce())
			->method('getValueString')
			->willReturnCallback(static function (string $uid, string $appId, string $key, string $default = '') use ($storedValue): string {
				if ($uid === 'preference-user'
					&& $appId === Application::APP_ID
					&& $key === 'warn_without_visible_signature_fields') {
					return $storedValue;
				}

				return $default;
			});

		$config = $this->getService()->getConfig($user);

		$this->assertArrayHasKey('warn_without_visible_signature_fields', $config);
		$this->assertSame($expected, $config['warn_without_visible_signature_fields']);
	}

	public static function provideWarnWithoutVisibleSignatureFieldsCases(): array {
		return [
			'stored 1 shows the warning' => ['1', true],
			'stored 0 hides the warning' => ['0', false],
			'no stored value falls back to the default' => ['', true],
		];
	}

	public function testGetConfigIncludesManageablePolicyGroupIds(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('manageable-user');

		$this->groupManager->method('isAdmin')->with('manageable-user')->willReturn(false);
		$this->subAdmin->method('isSubAdmin')->with($user)->willReturn(true);

		$finance = $this->createMock(IGroup::class);
		$finance->method('getGID')->willReturn('finance');
		$legal = $this->createMock(IGroup::class);
		$legal->method('getGID')->willReturn('legal');
		$this->subAdmin->method('getSubAdminsGroups')->with($user)->willReturn([$finance, $legal]);

		$this->policyService->method('resolveForUser')
			->willReturn((new ResolvedPolicy())
				->setEffectiveValue('{"allowGroups":["finance"],"denyGroups":[]}')
				->setEditableByCurrentActor(true));

		$config = $this->getService()->getConfig($user);

		$this->assertArrayHasKey('manageable_policy_group_ids', $config);
		$this->assertSame(['finance', 'legal'], $config['manageable_policy_group_ids']);
	}

	#[DataProvider('provideStoredAccountPreferences')]
	public function testGetConfigPreservesStoredPreferencesAndRoleBoundaries(string $role, string $json, ?array $decoded): void {
		$user = null;
		if ($role !== 'anonymous') {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('preference-owner');
		}
		$isAdmin = $role === 'admin';
		$isApprover = $role === 'approver';
		$this->groupManager->method('isAdmin')->with('preference-owner')->willReturn($isAdmin);
		$this->identityDocumentValidator->method('userCanApproveValidationDocuments')
			->with($user, false)->willReturn($isApprover);
		$this->accountCertificateService->method('hasSignatureFile')->with($user)->willReturn($user !== null);
		$stored = [
			'id_docs_filters' => $json, 'id_docs_sort' => $json,
			'crl_filters' => $json, 'crl_sort' => $json,
			'policy_workbench_category_collapsed_state' => $json,
			'files_list_sorting_mode' => 'size', 'files_list_sorting_direction' => 'desc',
			'files_list_grid_view' => '1', 'files_list_signer_identify_tab' => 'account',
			'policy_workbench_catalog_compact_view' => '1', 'policy_workbench_catalog_collapsed' => '1',
			'warn_without_visible_signature_fields' => '0',
		];
		if ($user === null) {
			$this->userConfig->expects($this->never())->method('getValueString');
		} else {
			$this->userConfig->method('getValueString')
				->willReturnCallback(static function (string $uid, string $appId, string $key, string $default = '') use ($stored, $isAdmin, $isApprover): string {
					self::assertSame('preference-owner', $uid);
					self::assertSame(Application::APP_ID, $appId);
					if (!$isAdmin) {
						self::assertNotContains($key, ['crl_filters', 'crl_sort']);
					}
					if (!$isApprover) {
						self::assertNotSame('id_docs_sort', $key);
					}
					return $stored[$key] ?? $default;
				});
		}
		$expected = [
			'identificationDocumentsFlow' => false,
			'hasSignatureFile' => $user !== null,
			'isApprover' => $isApprover,
			'id_docs_filters' => $user !== null ? ($decoded ?? []) : [],
			'id_docs_sort' => $isApprover ? ($decoded ?? ['sortBy' => null, 'sortOrder' => null]) : ['sortBy' => null, 'sortOrder' => null],
			'crl_filters' => $isAdmin ? ($decoded ?? []) : [],
			'crl_sort' => $isAdmin ? ($decoded ?? ['sortBy' => 'revoked_at', 'sortOrder' => 'DESC']) : ['sortBy' => 'revoked_at', 'sortOrder' => 'DESC'],
			'files_list_grid_view' => $user !== null,
			'files_list_sorting_mode' => $user !== null ? 'size' : 'name',
			'files_list_sorting_direction' => $user !== null ? 'desc' : 'asc',
			'policy_workbench_catalog_compact_view' => $user !== null,
			'policy_workbench_catalog_collapsed' => $user !== null,
			'warn_without_visible_signature_fields' => $user === null,
			'can_manage_group_policies' => $isAdmin,
			'manageable_policy_group_ids' => [],
		];
		if ($user !== null) {
			$expected['files_list_signer_identify_tab'] = 'account';
			if ($decoded !== null) {
				$expected['policy_workbench_category_collapsed_state'] = $decoded;
			}
		}
		$actual = $this->getService()->getConfig($user);
		ksort($actual);
		ksort($expected);
		$this->assertSame($expected, $actual);
	}

	public static function provideStoredAccountPreferences(): array {
		$cases = [];
		foreach (['anonymous', 'user', 'admin', 'approver'] as $role) {
			foreach ([
				'empty' => ['', null],
				'invalid JSON' => ['{invalid', null],
				'scalar' => ['"text"', null],
				'null' => ['null', null],
				'empty array' => ['[]', []],
				'populated object' => ['{"sortBy":"name","sortOrder":"ASC"}', ['sortBy' => 'name', 'sortOrder' => 'ASC']],
			] as $name => [$json, $decoded]) {
				$cases[$role . ': ' . $name] = [$role, $json, $decoded];
			}
		}
		return $cases;
	}

	public function testGetConfigIncludesCanManageGroupPoliciesForInstanceAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('instance-admin');

		$this->groupManager->method('isAdmin')->with('instance-admin')->willReturn(true);

		$config = $this->getService()->getConfig($user);

		$this->assertArrayHasKey('can_manage_group_policies', $config);
		$this->assertTrue($config['can_manage_group_policies']);
	}
}
