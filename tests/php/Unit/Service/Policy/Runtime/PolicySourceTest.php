<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Runtime;

use OC\AppFramework\Services\AppConfig as ScopedAppConfig;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\PermissionSet;
use OCA\Libresign\Db\PermissionSetBinding;
use OCA\Libresign\Db\PermissionSetBindingMapper;
use OCA\Libresign\Db\PermissionSetMapper;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Provider\ApprovalGroups\ApprovalGroupsPolicy;
use OCA\Libresign\Service\Policy\Provider\DocMdp\DocMdpPolicy;
use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\IdentifyMethodsPolicy;
use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\IdentifyMethodsPolicyValue;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicy;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicyValue;
use OCA\Libresign\Service\Policy\Provider\Signature\SignatureFlowPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureText\SignatureTextPolicy;
use OCA\Libresign\Service\Policy\Provider\Tsa\TsaPolicy;
use OCA\Libresign\Service\Policy\Provider\Tsa\TsaPolicyManagedValue;
use OCA\Libresign\Service\Policy\Runtime\PolicyRegistry;
use OCA\Libresign\Service\Policy\Runtime\PolicySource;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Services\IAppConfig as ScopedIAppConfig;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig as CoreIAppConfig;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;

final class PolicySourceTest extends TestCase {
	private ScopedIAppConfig $appConfig;
	private CoreIAppConfig $coreAppConfig;
	private IConfig&MockObject $config;
	private PermissionSetMapper&MockObject $permissionSetMapper;
	private PermissionSetBindingMapper&MockObject $bindingMapper;
	private IDBConnection&MockObject $db;
	private IL10N&MockObject $l10n;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private PolicyRegistry $registry;

	public function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->coreAppConfig = self::getMockAppConfigWithReset();
		$this->coreAppConfig->deleteApp(Application::APP_ID);
		$this->appConfig = new ScopedAppConfig(
			$this->config,
			$this->coreAppConfig,
			Application::APP_ID,
		);
		$this->permissionSetMapper = $this->createMock(PermissionSetMapper::class);
		$this->bindingMapper = $this->createMock(PermissionSetBindingMapper::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
		$container = $this->createMock(ContainerInterface::class);
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
				'requirement' => 'required',
				'signatureMethods' => [
					'emailToken' => ['enabled' => true],
				],
				'signatureMethodEnabled' => 'emailToken',
			],
		]);
		$coreAppConfig = $this->coreAppConfig;
		$l10n = $this->l10n;
		$identifyMethodService = $this->identifyMethodService;
		$container
			->method('get')
			->willReturnCallback(static function (string $class) use ($identifyMethodService, $coreAppConfig, $l10n): object {
				if ($class === IdentifyMethodsPolicy::class) {
					return new IdentifyMethodsPolicy($identifyMethodService);
				}
				if ($class === TsaPolicy::class) {
					return new TsaPolicy(new TsaPolicyManagedValue($coreAppConfig));
				}
				if ($class === SignatureTextPolicy::class) {
					return new SignatureTextPolicy($l10n);
				}
				if (!\class_exists($class)) {
					throw new \RuntimeException('Unexpected provider class: ' . $class);
				}

				return new $class();
			});
		$this->registry = new PolicyRegistry($container);
	}

	public function testLoadSystemPolicyReturnsForcedLayerWhenAppConfigIsSet(): void {
		$this->setStoredAppConfigString('policy.signature_flow.system', 'ordered_numeric');
		$this->setStoredAppConfigString('policy.signature_flow.system.allow_child_override', '0');

		$source = $this->getSource();
		$layer = $source->loadSystemPolicy('signature_flow');

		$this->assertNotNull($layer);
		$this->assertSame('global', $layer->getScope());
		$this->assertSame('ordered_numeric', $layer->getValue());
		$this->assertFalse($layer->isAllowChildOverride());
		$this->assertSame(['ordered_numeric'], $layer->getAllowedValues());
	}

	public function testLoadSystemPolicyReturnsInheritableLayerWhenAppConfigMatchesDefault(): void {
		$source = $this->getSource();
		$layer = $source->loadSystemPolicy('signature_flow');

		$this->assertNotNull($layer);
		$this->assertSame('none', $layer->getValue());
		$this->assertSame('system', $layer->getScope());
		$this->assertTrue($layer->isAllowChildOverride());
		$this->assertSame([], $layer->getAllowedValues());
	}

	public function testLoadGroupPoliciesReturnsBoundPermissionSetForActiveGroup(): void {
		$binding = new PermissionSetBinding();
		$binding->setPermissionSetId(77);
		$binding->setTargetType('group');
		$binding->setTargetId('finance');

		$permissionSet = new PermissionSet();
		$permissionSet->setId(77);
		$permissionSet->setPolicyJson([
			'signature_flow' => [
				'defaultValue' => 'ordered_numeric',
				'allowChildOverride' => false,
				'visibleToChild' => true,
				'allowedValues' => ['ordered_numeric'],
			],
		]);

		$this->bindingMapper
			->expects($this->once())
			->method('findByTargets')
			->with('group', ['finance'])
			->willReturn([$binding]);

		$this->permissionSetMapper
			->expects($this->once())
			->method('findByIds')
			->with([77])
			->willReturn([$permissionSet]);

		$context = PolicyContext::fromUserId('john')
			->setGroups(['finance'])
			->setActiveContext(['type' => 'group', 'id' => 'finance']);

		$source = $this->getSource();
		$layers = $source->loadGroupPolicies('signature_flow', $context);

		$this->assertCount(1, $layers);
		$this->assertSame('group', $layers[0]->getScope());
		$this->assertSame('ordered_numeric', $layers[0]->getValue());
		$this->assertFalse($layers[0]->isAllowChildOverride());
		$this->assertSame(['ordered_numeric'], $layers[0]->getAllowedValues());
	}

	public function testLoadGroupPoliciesHydratesSystemCreatedNotes(): void {
		$binding = new PermissionSetBinding();
		$binding->setPermissionSetId(77);
		$binding->setTargetType('group');
		$binding->setTargetId('finance');

		$permissionSet = new PermissionSet();
		$permissionSet->setId(77);
		$permissionSet->setPolicyJson([
			'signature_flow' => [
				'defaultValue' => 'ordered_numeric',
				'allowChildOverride' => true,
				'visibleToChild' => true,
				'allowedValues' => [],
				'createdBySystemAdmin' => true,
				'createdByActorScope' => 'system',
			],
		]);

		$this->bindingMapper
			->expects($this->once())
			->method('findByTargets')
			->with('group', ['finance'])
			->willReturn([$binding]);

		$this->permissionSetMapper
			->expects($this->once())
			->method('findByIds')
			->with([77])
			->willReturn([$permissionSet]);

		$context = PolicyContext::fromUserId('john')
			->setGroups(['finance'])
			->setActiveContext(['type' => 'group', 'id' => 'finance']);

		$layers = $this->getSource()->loadGroupPolicies('signature_flow', $context);

		$this->assertCount(1, $layers);
		$this->assertSame('system', $layers[0]->getNotes()['createdByActorScope'] ?? null);
		$this->assertTrue($layers[0]->getNotes()['createdBySystemAdmin'] ?? false);
	}

	public function testLoadGroupPoliciesMarksDelegatedRequestSignOverrideAsBackedBySystemSeed(): void {
		$binding = new PermissionSetBinding();
		$binding->setPermissionSetId(78);
		$binding->setTargetType('group');
		$binding->setTargetId('board');

		$permissionSet = new PermissionSet();
		$permissionSet->setId(78);
		$permissionSet->setPolicyJson([
			RequestSignGroupsPolicy::KEY => [
				'defaultValue' => RequestSignGroupsPolicyValue::encode([
					'allowGroups' => ['board'],
					'denyGroups' => [],
				]),
				'allowChildOverride' => true,
				'visibleToChild' => true,
				'allowedValues' => [],
				'createdBySystemAdmin' => true,
				'createdByActorScope' => 'system',
			],
			RequestSignGroupsPolicy::KEY . '__delegated_override' => [
				'defaultValue' => RequestSignGroupsPolicyValue::encode([
					'allowGroups' => ['board'],
					'denyGroups' => ['board'],
				]),
				'allowChildOverride' => true,
				'visibleToChild' => true,
				'allowedValues' => [],
				'createdBySystemAdmin' => false,
				'createdByActorScope' => 'group',
			],
		]);

		$this->bindingMapper
			->expects($this->once())
			->method('findByTargets')
			->with('group', ['board'])
			->willReturn([$binding]);

		$this->permissionSetMapper
			->expects($this->once())
			->method('findByIds')
			->with([78])
			->willReturn([$permissionSet]);

		$context = PolicyContext::fromUserId('ceo')
			->setGroups(['board'])
			->setActiveContext(['type' => 'group', 'id' => 'board']);

		$layers = $this->getSource()->loadGroupPolicies(RequestSignGroupsPolicy::KEY, $context);

		$this->assertCount(1, $layers);
		$this->assertFalse($layers[0]->getNotes()['createdBySystemAdmin'] ?? true);
		$this->assertSame('group', $layers[0]->getNotes()['createdByActorScope'] ?? null);
		$this->assertTrue($layers[0]->getNotes()['delegatedFromSystemCreatedSeed'] ?? false);
	}

	public function testLoadUserPreferenceReturnsLayerFromUserConfig(): void {
		$this->config
			->expects($this->once())
			->method('getUserValue')
			->with('john', Application::APP_ID, 'policy.signature_flow', '')
			->willReturn('"parallel"');

		$source = $this->getSource();
		$layer = $source->loadUserPreference('signature_flow', PolicyContext::fromUserId('john'));

		$this->assertNotNull($layer);
		$this->assertSame('user', $layer->getScope());
		$this->assertSame('parallel', $layer->getValue());
	}

	public function testSaveUserPreferenceNormalizesAndPersistsUserConfigValue(): void {
		$this->config
			->expects($this->once())
			->method('setUserValue')
			->with('john', Application::APP_ID, 'policy.signature_flow', '"ordered_numeric"', null);

		$source = $this->getSource();
		$source->saveUserPreference('signature_flow', PolicyContext::fromUserId('john'), 'ordered_numeric');
	}

	public function testLoadUserPolicyReturnsExplicitAssignedLayerFromUserConfig(): void {
		$this->config
			->expects($this->once())
			->method('getUserValue')
			->with('john', Application::APP_ID, 'policy.signature_flow.assigned', '')
			->willReturn('{"value":"ordered_numeric","allowChildOverride":false}');

		$source = $this->getSource();
		$layer = $source->loadUserPolicy('signature_flow', PolicyContext::fromUserId('john'));

		$this->assertNotNull($layer);
		$this->assertSame('user_policy', $layer->getScope());
		$this->assertSame('ordered_numeric', $layer->getValue());
		$this->assertFalse($layer->isAllowChildOverride());
		$this->assertSame(['ordered_numeric'], $layer->getAllowedValues());
	}

	#[DataProvider('providerStoredUserPolicyPayloadCases')]
	public function testLoadUserPolicyConfigInterpretsStoredPayloads(
		string $storedPayload,
		?string $expectedValue,
		bool $expectedAllowChildOverride,
		array $expectedAllowedValues,
	): void {
		$this->config
			->expects($this->once())
			->method('getUserValue')
			->with('john', Application::APP_ID, 'policy.signature_flow.assigned', '')
			->willReturn($storedPayload);

		$layer = $this->getSource()->loadUserPolicyConfig('signature_flow', 'john');

		if ($expectedValue === null) {
			$this->assertNull($layer);
			return;
		}

		$this->assertNotNull($layer);
		$this->assertSame('user_policy', $layer->getScope());
		$this->assertSame($expectedValue, $layer->getValue());
		$this->assertSame($expectedAllowChildOverride, $layer->isAllowChildOverride());
		$this->assertSame($expectedAllowedValues, $layer->getAllowedValues());
	}

	/** @return array<string, array{0: string, 1: ?string, 2: bool, 3: array<int, string>}> */
	public static function providerStoredUserPolicyPayloadCases(): array {
		return [
			'allow_child_override_disabled' => [
				'{"value":"ordered_numeric","allowChildOverride":false}',
				'ordered_numeric',
				false,
				['ordered_numeric'],
			],
			'allow_child_override_enabled' => [
				'{"value":"ordered_numeric","allowChildOverride":true}',
				'ordered_numeric',
				true,
				[],
			],
			'missing_value' => [
				'{"allowChildOverride":true}',
				null,
				false,
				[],
			],
			'null_value' => [
				'{"value":null,"allowChildOverride":true}',
				null,
				false,
				[],
			],
			'invalid_json' => [
				'not-json',
				null,
				false,
				[],
			],
		];
	}

	public function testSaveUserPolicyPersistsAssignedUserPayload(): void {
		$this->config
			->expects($this->once())
			->method('setUserValue')
			->with('john', Application::APP_ID, 'policy.signature_flow.assigned', '{"value":"ordered_numeric","allowChildOverride":true}', null);

		$source = $this->getSource();
		$source->saveUserPolicy('signature_flow', PolicyContext::fromUserId('john'), 'ordered_numeric', true);
	}

	public function testLoadUserPolicyConfigReturnsNullWhenUserIdIsEmpty(): void {
		$this->config->expects($this->never())->method('getUserValue');

		$this->assertNull($this->getSource()->loadUserPolicyConfig('signature_flow', ''));
	}

	public function testListUserPoliciesByKeyReturnsMappedTargetsFromDatabase(): void {
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('eq')->willReturn('1=1');
		$expr->method('in')->willReturn('1=1');
		$expr->method('neq')->willReturn('1=1');

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('andWhere')->willReturnSelf();
		$qb->method('expr')->willReturn($expr);
		$qb->method('createNamedParameter')->willReturn(':p');

		$dbResult = $this->createMock(IResult::class);
		$dbResult->method('fetchAssociative')->willReturnOnConsecutiveCalls(
			['userid' => 'john', 'configvalue' => '{"value":"ordered_numeric","allowChildOverride":true}'],
			['userid' => '', 'configvalue' => '{"value":"parallel"}'],
			['userid' => 'mary', 'configvalue' => 'not-json'],
			false,
		);
		$dbResult->expects($this->once())->method('closeCursor');
		$qb->method('executeQuery')->willReturn($dbResult);

		$this->db->expects($this->once())->method('getQueryBuilder')->willReturn($qb);

		$result = $this->getSource()->listUserPoliciesByKey('signature_flow');

		$this->assertCount(1, $result);
		$this->assertSame('john', $result[0]['targetId']);
		$this->assertSame('user_policy', $result[0]['policy']->getScope());
		$this->assertSame('ordered_numeric', $result[0]['policy']->getValue());
		$this->assertTrue($result[0]['policy']->isAllowChildOverride());
		$this->assertSame([], $result[0]['policy']->getAllowedValues());
	}

	public function testClearUserPolicyDeletesAssignedUserConfig(): void {
		$this->config
			->expects($this->once())
			->method('deleteUserValue')
			->with('john', Application::APP_ID, 'policy.signature_flow.assigned');

		$source = $this->getSource();
		$source->clearUserPolicy('signature_flow', PolicyContext::fromUserId('john'));
	}

	public function testClearUserPreferenceDeletesUserConfig(): void {
		$this->config
			->expects($this->once())
			->method('deleteUserValue')
			->with('john', Application::APP_ID, 'policy.signature_flow');

		$source = $this->getSource();
		$source->clearUserPreference('signature_flow', PolicyContext::fromUserId('john'));
	}

	public function testListGroupPoliciesByKeyReturnsOnlyValidTargets(): void {
		$bindingOne = new PermissionSetBinding();
		$bindingOne->setPermissionSetId(11);
		$bindingOne->setTargetType('group');
		$bindingOne->setTargetId('finance');

		$bindingTwo = new PermissionSetBinding();
		$bindingTwo->setPermissionSetId(12);
		$bindingTwo->setTargetType('group');
		$bindingTwo->setTargetId('legal');

		$permissionSetOne = new PermissionSet();
		$permissionSetOne->setId(11);
		$permissionSetOne->setPolicyJson([
			'signature_flow' => [
				'defaultValue' => 'ordered_numeric',
				'allowChildOverride' => false,
				'visibleToChild' => true,
				'allowedValues' => ['ordered_numeric'],
			],
		]);

		$permissionSetTwo = new PermissionSet();
		$permissionSetTwo->setId(12);
		$permissionSetTwo->setPolicyJson([
			'signature_flow' => [
				'allowChildOverride' => true,
				'visibleToChild' => false,
				'allowedValues' => ['parallel'],
			],
		]);

		$this->bindingMapper
			->expects($this->once())
			->method('findByTargetType')
			->with('group')
			->willReturn([$bindingOne, $bindingTwo]);

		$this->permissionSetMapper
			->expects($this->once())
			->method('findByIds')
			->with([11, 12])
			->willReturn([$permissionSetOne, $permissionSetTwo]);

		$result = $this->getSource()->listGroupPoliciesByKey('signature_flow');

		$this->assertCount(1, $result);
		$this->assertSame('finance', $result[0]['targetId']);
		$this->assertSame('group', $result[0]['policy']->getScope());
		$this->assertSame('ordered_numeric', $result[0]['policy']->getValue());
		$this->assertFalse($result[0]['policy']->isAllowChildOverride());
		$this->assertSame(['ordered_numeric'], $result[0]['policy']->getAllowedValues());
	}

	public function testListGroupPoliciesByKeyForTargetsScopesBindingsToProvidedGroups(): void {
		$binding = new PermissionSetBinding();
		$binding->setPermissionSetId(11);
		$binding->setTargetType('group');
		$binding->setTargetId('finance');

		$permissionSet = new PermissionSet();
		$permissionSet->setId(11);
		$permissionSet->setPolicyJson([
			'signature_flow' => [
				'defaultValue' => 'ordered_numeric',
				'allowChildOverride' => false,
				'visibleToChild' => true,
				'allowedValues' => ['ordered_numeric'],
			],
		]);

		$this->bindingMapper
			->expects($this->once())
			->method('findByTargets')
			->with('group', ['finance'])
			->willReturn([$binding]);

		$this->bindingMapper
			->expects($this->never())
			->method('findByTargetType');

		$this->permissionSetMapper
			->expects($this->once())
			->method('findByIds')
			->with([11])
			->willReturn([$permissionSet]);

		$result = $this->getSource()->listGroupPoliciesByKeyForTargets('signature_flow', ['finance', 'finance', '']);

		$this->assertCount(1, $result);
		$this->assertSame('finance', $result[0]['targetId']);
		$this->assertSame('ordered_numeric', $result[0]['policy']->getValue());
	}

	public function testSaveSystemPolicyDeletesAppConfigWhenValueMatchesDefault(): void {
		$this->setStoredAppConfigString('policy.signature_flow.system', 'ordered_numeric');
		$this->setStoredAppConfigString('policy.signature_flow.system.allow_child_override', '1');

		$source = $this->getSource();
		$source->saveSystemPolicy('signature_flow', 'none');

		$this->assertAppConfigMissing('policy.signature_flow.system');
		$this->assertAppConfigMissing('policy.signature_flow.system.allow_child_override');
	}

	public function testClearSystemPolicyDeletesStoredSystemConfig(): void {
		$this->setStoredAppConfigString('policy.signature_flow.system', 'ordered_numeric');
		$this->setStoredAppConfigString('policy.signature_flow.system.allow_child_override', '1');

		$source = $this->getSource();
		$source->clearSystemPolicy('signature_flow');

		$this->assertAppConfigMissing('policy.signature_flow.system');
		$this->assertAppConfigMissing('policy.signature_flow.system.allow_child_override');
	}

	public function testSaveSystemPolicyPersistsExplicitDefaultWhenAllowChildOverrideIsTrue(): void {
		$source = $this->getSource();
		$source->saveSystemPolicy('signature_flow', 'none', true);

		$this->assertStoredAppConfigString('policy.signature_flow.system', 'none');
		$this->assertStoredAppConfigString('policy.signature_flow.system.allow_child_override', '1');
	}

	public function testSaveSystemPolicyNormalizesAndPersistsAppConfigValue(): void {
		$source = $this->getSource();
		$source->saveSystemPolicy('signature_flow', 'ordered_numeric', true);

		$this->assertStoredAppConfigString('policy.signature_flow.system', 'ordered_numeric');
		$this->assertStoredAppConfigString('policy.signature_flow.system.allow_child_override', '1');
	}

	public function testSaveSystemPolicyDeletesIdentifyMethodsWhenValueMatchesServiceDefault(): void {
		$this->setStoredAppConfigArray(IdentifyMethodsPolicy::SYSTEM_APP_CONFIG_KEY, [
			[
				'name' => 'account',
				'enabled' => true,
				'requirement' => 'required',
				'signatureMethods' => [
					'clickToSign' => ['enabled' => true],
				],
			],
			[
				'name' => 'email',
				'enabled' => false,
				'requirement' => 'optional',
				'signatureMethods' => [
					'emailToken' => ['enabled' => true],
				],
			],
		]);

		$source = $this->getSource();
		$source->saveSystemPolicy(
			IdentifyMethodsPolicy::KEY,
			IdentifyMethodsPolicyValue::normalize([], $this->identifyMethodService),
			false,
		);

		$this->assertAppConfigMissing(IdentifyMethodsPolicy::SYSTEM_APP_CONFIG_KEY);
		$this->assertAppConfigMissing(IdentifyMethodsPolicy::SYSTEM_APP_CONFIG_KEY . '.allow_child_override');
	}

	#[DataProvider('providerSaveSystemPolicyBusinessRules')]
	public function testSaveSystemPolicyBusinessRulesWithDataProvider(
		string $policyKey,
		string $inputValue,
		bool $allowChildOverride,
		bool $expectDelete,
		string $expectedValue,
		string $expectedAppConfigKey,
		string $expectedAllowOverrideValue,
	): void {
		$source = $this->getSource();

		if ($expectDelete) {
			$source->saveSystemPolicy($policyKey, $inputValue, $allowChildOverride);

			$this->assertAppConfigMissing($expectedAppConfigKey);
			$this->assertAppConfigMissing($expectedAppConfigKey . '.allow_child_override');
			return;
		}

		$source->saveSystemPolicy($policyKey, $inputValue, $allowChildOverride);

		$this->assertStoredAppConfigString($expectedAppConfigKey, $expectedValue);
		$this->assertStoredAppConfigString($expectedAppConfigKey . '.allow_child_override', $expectedAllowOverrideValue);
	}

	/** @return array<string, array{0: string, 1: string, 2: bool, 3: bool, 4: string, 5: string, 6: string}> */
	public static function providerSaveSystemPolicyBusinessRules(): array {
		return [
			'deletes_when_value_matches_default_and_override_disabled' => [
				SignatureFlowPolicy::KEY,
				'none',
				false,
				true,
				'none',
				'policy.signature_flow.system',
				'0',
			],
			'persists_explicit_default_when_override_enabled' => [
				SignatureFlowPolicy::KEY,
				'none',
				true,
				false,
				'none',
				'policy.signature_flow.system',
				'1',
			],
			'deletes_when_json_is_semantically_equal_to_default' => [
				ApprovalGroupsPolicy::KEY,
				'[ "admin" ]',
				false,
				true,
				'["admin"]',
				ApprovalGroupsPolicy::SYSTEM_APP_CONFIG_KEY,
				'0',
			],
			'persists_when_value_differs_from_default' => [
				SignatureFlowPolicy::KEY,
				'ordered_numeric',
				false,
				false,
				'ordered_numeric',
				'policy.signature_flow.system',
				'0',
			],
		];
	}

	public function testLoadSystemPolicyRespectsPersistedAllowChildOverride(): void {
		$this->setStoredAppConfigString('policy.signature_flow.system', 'ordered_numeric');
		$this->setStoredAppConfigString('policy.signature_flow.system.allow_child_override', '1');

		$source = $this->getSource();
		$layer = $source->loadSystemPolicy('signature_flow');

		$this->assertNotNull($layer);
		$this->assertSame('ordered_numeric', $layer->getValue());
		$this->assertSame('global', $layer->getScope());
		$this->assertTrue($layer->isAllowChildOverride());
		$this->assertSame([], $layer->getAllowedValues());
	}

	public function testLoadSystemPolicyTreatsPersistedDefaultAsExplicitWhenAllowChildOverrideIsSet(): void {
		$this->setStoredAppConfigString('policy.signature_flow.system', 'none');
		$this->setStoredAppConfigString('policy.signature_flow.system.allow_child_override', '1');

		$source = $this->getSource();
		$layer = $source->loadSystemPolicy('signature_flow');

		$this->assertNotNull($layer);
		$this->assertSame('none', $layer->getValue());
		$this->assertSame('global', $layer->getScope());
		$this->assertTrue($layer->isAllowChildOverride());
		$this->assertSame([], $layer->getAllowedValues());
	}

	public function testLoadSystemPolicyReturnsDocMdpLayerFromTypedIntConfig(): void {
		$this->setStoredAppConfigInt('docmdp_level', 2);
		$this->setStoredAppConfigString('docmdp_level.allow_child_override', '0');

		$source = $this->getSource();
		$layer = $source->loadSystemPolicy(DocMdpPolicy::KEY);

		$this->assertNotNull($layer);
		$this->assertSame('global', $layer->getScope());
		$this->assertSame(2, $layer->getValue());
		$this->assertFalse($layer->isAllowChildOverride());
		$this->assertSame([2], $layer->getAllowedValues());
	}

	public function testSaveSystemPolicyPersistsDocMdpLevelAsTypedInt(): void {
		$source = $this->getSource();
		$source->saveSystemPolicy(DocMdpPolicy::KEY, 2, false);

		$this->assertStoredAppConfigInt('docmdp_level', 2);
		$this->assertStoredAppConfigString('docmdp_level.allow_child_override', '0');
	}

	public function testLoadSystemPolicyNormalizesLegacyTypedArrayForGroupsRequestSign(): void {
		$this->setStoredAppConfigArray('groups_request_sign', ['finance', 'admin']);
		$this->setStoredAppConfigString('groups_request_sign.allow_child_override', '0');

		$source = $this->getSource();
		$layer = $source->loadSystemPolicy('groups_request_sign');

		$this->assertNotNull($layer);
		$this->assertSame('global', $layer->getScope());
		$this->assertSame('{"allowGroups":["admin","finance"],"denyGroups":[]}', $layer->getValue());
		$this->assertFalse($layer->isAllowChildOverride());
		$this->assertSame(['{"allowGroups":["admin","finance"],"denyGroups":[]}'], $layer->getAllowedValues());
	}

	public function testLoadGroupPolicyConfigReturnsBoundPolicyLayer(): void {
		$binding = new PermissionSetBinding();
		$binding->setPermissionSetId(77);
		$binding->setTargetType('group');
		$binding->setTargetId('finance');

		$permissionSet = new PermissionSet();
		$permissionSet->setId(77);
		$permissionSet->setPolicyJson([
			'signature_flow' => [
				'defaultValue' => 'parallel',
				'allowChildOverride' => true,
				'visibleToChild' => true,
				'allowedValues' => [],
			],
		]);

		$this->bindingMapper
			->expects($this->once())
			->method('getByTarget')
			->with('group', 'finance')
			->willReturn($binding);

		$this->permissionSetMapper
			->expects($this->once())
			->method('getById')
			->with(77)
			->willReturn($permissionSet);

		$source = $this->getSource();
		$layer = $source->loadGroupPolicyConfig('signature_flow', 'finance');

		$this->assertNotNull($layer);
		$this->assertSame('group', $layer->getScope());
		$this->assertSame('parallel', $layer->getValue());
		$this->assertTrue($layer->isAllowChildOverride());
		$this->assertSame([], $layer->getNotes());
	}

	public function testLoadGroupPolicyConfigMapsLegacyCreatorScopeToBooleanOriginNote(): void {
		$binding = new PermissionSetBinding();
		$binding->setPermissionSetId(77);
		$binding->setTargetType('group');
		$binding->setTargetId('finance');

		$permissionSet = new PermissionSet();
		$permissionSet->setId(77);
		$permissionSet->setPolicyJson([
			'signature_flow' => [
				'defaultValue' => 'parallel',
				'allowChildOverride' => true,
				'visibleToChild' => true,
				'allowedValues' => [],
				'createdByActorScope' => 'system',
			],
		]);

		$this->bindingMapper
			->expects($this->once())
			->method('getByTarget')
			->with('group', 'finance')
			->willReturn($binding);

		$this->permissionSetMapper
			->expects($this->once())
			->method('getById')
			->with(77)
			->willReturn($permissionSet);

		$source = $this->getSource();
		$layer = $source->loadGroupPolicyConfig('signature_flow', 'finance');

		$this->assertNotNull($layer);
		$this->assertSame('system', $layer->getNotes()['createdByActorScope'] ?? null);
		$this->assertTrue($layer->getNotes()['createdBySystemAdmin'] ?? false);
	}

	public function testLoadGroupPolicyConfigUsesDelegatedRequestSignOverrideWhenSystemSeedExists(): void {
		$binding = new PermissionSetBinding();
		$binding->setPermissionSetId(77);
		$binding->setTargetType('group');
		$binding->setTargetId('board');

		$permissionSet = new PermissionSet();
		$permissionSet->setId(77);
		$permissionSet->setPolicyJson([
			RequestSignGroupsPolicy::KEY => [
				'defaultValue' => RequestSignGroupsPolicyValue::encode([
					'allowGroups' => ['board'],
					'denyGroups' => [],
				]),
				'allowChildOverride' => true,
				'visibleToChild' => true,
				'allowedValues' => [],
				'createdBySystemAdmin' => true,
				'createdByActorScope' => 'system',
			],
			RequestSignGroupsPolicy::KEY . '__delegated_override' => [
				'defaultValue' => RequestSignGroupsPolicyValue::encode([
					'allowGroups' => ['board'],
					'denyGroups' => ['board'],
				]),
				'allowChildOverride' => false,
				'visibleToChild' => true,
				'allowedValues' => [RequestSignGroupsPolicyValue::encode([
					'allowGroups' => ['board'],
					'denyGroups' => ['board'],
				])],
				'createdBySystemAdmin' => false,
				'createdByActorScope' => 'group',
			],
		]);

		$this->bindingMapper
			->expects($this->once())
			->method('getByTarget')
			->with('group', 'board')
			->willReturn($binding);

		$this->permissionSetMapper
			->expects($this->once())
			->method('getById')
			->with(77)
			->willReturn($permissionSet);

		$layer = $this->getSource()->loadGroupPolicyConfig(RequestSignGroupsPolicy::KEY, 'board');

		$this->assertNotNull($layer);
		$this->assertSame(
			RequestSignGroupsPolicyValue::encode([
				'allowGroups' => ['board'],
				'denyGroups' => ['board'],
			]),
			$layer->getValue(),
		);
		$this->assertFalse($layer->getNotes()['createdBySystemAdmin'] ?? true);
		$this->assertSame('group', $layer->getNotes()['createdByActorScope'] ?? null);
	}

	public function testListGroupPoliciesByKeyUsesDelegatedRequestSignOverrideOnlyOncePerTarget(): void {
		$binding = new PermissionSetBinding();
		$binding->setPermissionSetId(88);
		$binding->setTargetType('group');
		$binding->setTargetId('board');

		$permissionSet = new PermissionSet();
		$permissionSet->setId(88);
		$permissionSet->setPolicyJson([
			RequestSignGroupsPolicy::KEY => [
				'defaultValue' => RequestSignGroupsPolicyValue::encode([
					'allowGroups' => ['board'],
					'denyGroups' => [],
				]),
				'allowChildOverride' => true,
				'visibleToChild' => true,
				'allowedValues' => [],
				'createdBySystemAdmin' => true,
				'createdByActorScope' => 'system',
			],
			RequestSignGroupsPolicy::KEY . '__delegated_override' => [
				'defaultValue' => RequestSignGroupsPolicyValue::encode([
					'allowGroups' => ['board'],
					'denyGroups' => ['board'],
				]),
				'allowChildOverride' => true,
				'visibleToChild' => true,
				'allowedValues' => [],
				'createdBySystemAdmin' => false,
				'createdByActorScope' => 'group',
			],
		]);

		$this->bindingMapper
			->expects($this->once())
			->method('findByTargetType')
			->with('group')
			->willReturn([$binding]);

		$this->permissionSetMapper
			->expects($this->once())
			->method('findByIds')
			->with([88])
			->willReturn([$permissionSet]);

		$result = $this->getSource()->listGroupPoliciesByKey(RequestSignGroupsPolicy::KEY);

		$this->assertCount(1, $result);
		$this->assertSame('board', $result[0]['targetId']);
		$this->assertSame(
			RequestSignGroupsPolicyValue::encode([
				'allowGroups' => ['board'],
				'denyGroups' => ['board'],
			]),
			$result[0]['policy']->getValue(),
		);
	}

	public function testSaveGroupPolicyStoresDelegatedRequestSignOverrideWithoutOverwritingSystemSeed(): void {
		$binding = new PermissionSetBinding();
		$binding->setPermissionSetId(99);
		$binding->setTargetType('group');
		$binding->setTargetId('board');

		$permissionSet = new PermissionSet();
		$permissionSet->setId(99);
		$permissionSet->setPolicyJson([
			RequestSignGroupsPolicy::KEY => [
				'defaultValue' => RequestSignGroupsPolicyValue::encode([
					'allowGroups' => ['board'],
					'denyGroups' => [],
				]),
				'allowChildOverride' => true,
				'visibleToChild' => true,
				'allowedValues' => [],
				'createdBySystemAdmin' => true,
				'createdByActorScope' => 'system',
			],
		]);

		$this->bindingMapper
			->expects($this->once())
			->method('getByTarget')
			->with('group', 'board')
			->willReturn($binding);

		$this->permissionSetMapper
			->expects($this->once())
			->method('getById')
			->with(99)
			->willReturn($permissionSet);

		$this->permissionSetMapper
			->expects($this->once())
			->method('update')
			->with($this->callback(function (PermissionSet $updatedPermissionSet): bool {
				$policyJson = $updatedPermissionSet->getDecodedPolicyJson();
				$this->assertArrayHasKey(RequestSignGroupsPolicy::KEY, $policyJson);
				$this->assertArrayHasKey(RequestSignGroupsPolicy::KEY . '__delegated_override', $policyJson);
				$this->assertSame(
					RequestSignGroupsPolicyValue::encode([
						'allowGroups' => ['board'],
						'denyGroups' => [],
					]),
					$policyJson[RequestSignGroupsPolicy::KEY]['defaultValue'] ?? null,
				);
				$this->assertSame(
					RequestSignGroupsPolicyValue::encode([
						'allowGroups' => ['board'],
						'denyGroups' => ['board'],
					]),
					$policyJson[RequestSignGroupsPolicy::KEY . '__delegated_override']['defaultValue'] ?? null,
				);
				$this->assertFalse($policyJson[RequestSignGroupsPolicy::KEY . '__delegated_override']['createdBySystemAdmin'] ?? true);
				return true;
			}));

		$source = $this->getSource();
		$source->saveGroupPolicy(
			RequestSignGroupsPolicy::KEY,
			'board',
			RequestSignGroupsPolicyValue::encode([
				'allowGroups' => ['board'],
				'denyGroups' => ['board'],
			]),
			false,
			false,
		);
	}

	public function testClearGroupPolicyPreservesSystemSeedWhenRemovingDelegatedRequestSignOverride(): void {
		$binding = new PermissionSetBinding();
		$binding->setPermissionSetId(109);
		$binding->setTargetType('group');
		$binding->setTargetId('board');

		$permissionSet = new PermissionSet();
		$permissionSet->setId(109);
		$permissionSet->setPolicyJson([
			RequestSignGroupsPolicy::KEY => [
				'defaultValue' => RequestSignGroupsPolicyValue::encode([
					'allowGroups' => ['board'],
					'denyGroups' => [],
				]),
				'allowChildOverride' => true,
				'visibleToChild' => true,
				'allowedValues' => [],
				'createdBySystemAdmin' => true,
				'createdByActorScope' => 'system',
			],
			RequestSignGroupsPolicy::KEY . '__delegated_override' => [
				'defaultValue' => RequestSignGroupsPolicyValue::encode([
					'allowGroups' => ['board'],
					'denyGroups' => ['board'],
				]),
				'allowChildOverride' => false,
				'visibleToChild' => true,
				'allowedValues' => [RequestSignGroupsPolicyValue::encode([
					'allowGroups' => ['board'],
					'denyGroups' => ['board'],
				])],
				'createdBySystemAdmin' => false,
				'createdByActorScope' => 'group',
			],
		]);

		$this->bindingMapper
			->expects($this->once())
			->method('getByTarget')
			->with('group', 'board')
			->willReturn($binding);

		$this->permissionSetMapper
			->expects($this->once())
			->method('getById')
			->with(109)
			->willReturn($permissionSet);

		$this->permissionSetMapper
			->expects($this->once())
			->method('update')
			->with($this->callback(function (PermissionSet $updatedPermissionSet): bool {
				$policyJson = $updatedPermissionSet->getDecodedPolicyJson();
				$this->assertArrayHasKey(RequestSignGroupsPolicy::KEY, $policyJson);
				$this->assertArrayNotHasKey(RequestSignGroupsPolicy::KEY . '__delegated_override', $policyJson);
				$this->assertSame(
					RequestSignGroupsPolicyValue::encode([
						'allowGroups' => ['board'],
						'denyGroups' => [],
					]),
					$policyJson[RequestSignGroupsPolicy::KEY]['defaultValue'] ?? null,
				);
				return true;
			}));

		$this->bindingMapper->expects($this->never())->method('delete');
		$this->permissionSetMapper->expects($this->never())->method('delete');

		$source = $this->getSource();
		$source->clearGroupPolicy(RequestSignGroupsPolicy::KEY, 'board', true);
	}

	public function testSaveGroupPolicyCreatesPermissionSetAndBinding(): void {
		$this->bindingMapper
			->expects($this->once())
			->method('getByTarget')
			->with('group', 'finance')
			->willThrowException(new DoesNotExistException('missing'));

		$this->permissionSetMapper
			->expects($this->once())
			->method('insert')
			->with($this->callback(function (PermissionSet $permissionSet): bool {
				$this->assertSame('group', $permissionSet->getScopeType());
				$this->assertSame('group:finance', $permissionSet->getName());
				$this->assertSame([
					'signature_flow' => [
						'defaultValue' => 'ordered_numeric',
						'allowChildOverride' => false,
						'visibleToChild' => true,
						'allowedValues' => ['ordered_numeric'],
						'createdBySystemAdmin' => false,
						'createdByActorScope' => 'group',
					],
				], $permissionSet->getDecodedPolicyJson());
				return true;
			}))
			->willReturnCallback(static function (PermissionSet $permissionSet): PermissionSet {
				$permissionSet->setId(77);
				return $permissionSet;
			});

		$this->bindingMapper
			->expects($this->once())
			->method('insert')
			->with($this->callback(function (PermissionSetBinding $binding): bool {
				$this->assertSame(77, $binding->getPermissionSetId());
				$this->assertSame('group', $binding->getTargetType());
				$this->assertSame('finance', $binding->getTargetId());
				return true;
			}));

		$source = $this->getSource();
		$source->saveGroupPolicy('signature_flow', 'finance', 'ordered_numeric', false);
	}

	public function testSaveGroupPolicyCreatesDocMdpPermissionSetWithNormalizedLevel(): void {
		$this->bindingMapper
			->expects($this->once())
			->method('getByTarget')
			->with('group', 'finance')
			->willThrowException(new DoesNotExistException('missing'));

		$this->permissionSetMapper
			->expects($this->once())
			->method('insert')
			->with($this->callback(function (PermissionSet $permissionSet): bool {
				$this->assertSame('group', $permissionSet->getScopeType());
				$this->assertSame('group:finance', $permissionSet->getName());
				$this->assertSame([
					'docmdp' => [
						'defaultValue' => 3,
						'allowChildOverride' => false,
						'visibleToChild' => true,
						'allowedValues' => [3],
						'createdBySystemAdmin' => false,
						'createdByActorScope' => 'group',
					],
				], $permissionSet->getDecodedPolicyJson());
				return true;
			}))
			->willReturnCallback(static function (PermissionSet $permissionSet): PermissionSet {
				$permissionSet->setId(91);
				return $permissionSet;
			});

		$this->bindingMapper
			->expects($this->once())
			->method('insert')
			->with($this->callback(function (PermissionSetBinding $binding): bool {
				$this->assertSame(91, $binding->getPermissionSetId());
				$this->assertSame('group', $binding->getTargetType());
				$this->assertSame('finance', $binding->getTargetId());
				return true;
			}));

		$source = $this->getSource();
		$source->saveGroupPolicy(DocMdpPolicy::KEY, 'finance', 3, false);
	}

	public function testSaveGroupPolicyMarksSystemAdminAsCreator(): void {
		$this->bindingMapper
			->expects($this->once())
			->method('getByTarget')
			->with('group', 'finance')
			->willThrowException(new DoesNotExistException('missing'));

		$this->permissionSetMapper
			->expects($this->once())
			->method('insert')
			->with($this->callback(function (PermissionSet $permissionSet): bool {
				$this->assertSame(true, $permissionSet->getDecodedPolicyJson()['signature_flow']['createdBySystemAdmin'] ?? null);
				$this->assertSame('system', $permissionSet->getDecodedPolicyJson()['signature_flow']['createdByActorScope'] ?? null);
				return true;
			}))
			->willReturnCallback(static function (PermissionSet $permissionSet): PermissionSet {
				$permissionSet->setId(101);
				return $permissionSet;
			});

		$this->bindingMapper
			->expects($this->once())
			->method('insert')
			->with($this->callback(function (PermissionSetBinding $binding): bool {
				$this->assertSame(101, $binding->getPermissionSetId());
				$this->assertSame('group', $binding->getTargetType());
				$this->assertSame('finance', $binding->getTargetId());
				return true;
			}));

		$source = $this->getSource();
		$source->saveGroupPolicy('signature_flow', 'finance', 'ordered_numeric', false, true);
	}

	public function testClearGroupPolicyDeletesBindingAndPermissionSetWhenItIsTheLastPolicy(): void {
		$binding = new PermissionSetBinding();
		$binding->setPermissionSetId(77);
		$binding->setTargetType('group');
		$binding->setTargetId('finance');

		$permissionSet = new PermissionSet();
		$permissionSet->setId(77);
		$permissionSet->setPolicyJson([
			'signature_flow' => [
				'defaultValue' => 'parallel',
				'allowChildOverride' => true,
				'visibleToChild' => true,
				'allowedValues' => [],
			],
		]);

		$this->bindingMapper
			->expects($this->once())
			->method('getByTarget')
			->with('group', 'finance')
			->willReturn($binding);

		$this->permissionSetMapper
			->expects($this->once())
			->method('getById')
			->with(77)
			->willReturn($permissionSet);

		$this->bindingMapper
			->expects($this->once())
			->method('delete')
			->with($binding);

		$this->permissionSetMapper
			->expects($this->once())
			->method('delete')
			->with($permissionSet);

		$source = $this->getSource();
		$source->clearGroupPolicy('signature_flow', 'finance');
	}

	public function testLoadRequestOverrideReturnsLayerFromContext(): void {
		$source = $this->getSource();
		$context = PolicyContext::fromUserId('john')
			->setRequestOverrides(['signature_flow' => 'ordered_numeric']);

		$layer = $source->loadRequestOverride('signature_flow', $context);

		$this->assertNotNull($layer);
		$this->assertSame('request', $layer->getScope());
		$this->assertSame('ordered_numeric', $layer->getValue());
	}

	public function testLoadCirclePoliciesReturnsEmptyArray(): void {
		$this->assertSame([], $this->getSource()->loadCirclePolicies('signature_flow', PolicyContext::fromUserId('john')));
	}

	public function testLoadAllGroupPoliciesBuildsLayersForAllPoliciesWithSingleQueryPair(): void {
		$binding = new PermissionSetBinding();
		$binding->setPermissionSetId(77);
		$binding->setTargetType('group');
		$binding->setTargetId('finance');

		$permissionSet = new PermissionSet();
		$permissionSet->setId(77);
		$permissionSet->setPolicyJson([
			'signature_flow' => [
				'defaultValue' => 'ordered_numeric',
				'allowChildOverride' => false,
				'visibleToChild' => true,
				'allowedValues' => ['ordered_numeric'],
			],
		]);

		$this->bindingMapper
			->expects($this->once())
			->method('findByTargets')
			->with('group', ['finance'])
			->willReturn([$binding]);

		$this->permissionSetMapper
			->expects($this->once())
			->method('findByIds')
			->with([77])
			->willReturn([$permissionSet]);

		$context = PolicyContext::fromUserId('john')
			->setGroups(['finance'])
			->setActiveContext(['type' => 'group', 'id' => 'finance']);

		$source = $this->getSource();
		$result = $source->loadAllGroupPolicies(['signature_flow', 'docmdp', 'footer_template'], $context);

		$this->assertArrayHasKey('signature_flow', $result);
		$this->assertArrayHasKey('docmdp', $result);
		$this->assertArrayHasKey('footer_template', $result);

		$this->assertCount(1, $result['signature_flow']);
		$this->assertSame('ordered_numeric', $result['signature_flow'][0]->getValue());
		$this->assertSame('group', $result['signature_flow'][0]->getScope());

		$this->assertSame([], $result['docmdp']);
		$this->assertSame([], $result['footer_template']);
	}

	public function testLoadAllGroupPoliciesHydratesSystemCreatedNotes(): void {
		$binding = new PermissionSetBinding();
		$binding->setPermissionSetId(77);
		$binding->setTargetType('group');
		$binding->setTargetId('finance');

		$permissionSet = new PermissionSet();
		$permissionSet->setId(77);
		$permissionSet->setPolicyJson([
			'signature_flow' => [
				'defaultValue' => 'ordered_numeric',
				'allowChildOverride' => true,
				'visibleToChild' => true,
				'allowedValues' => [],
				'createdBySystemAdmin' => true,
				'createdByActorScope' => 'system',
			],
		]);

		$this->bindingMapper
			->expects($this->once())
			->method('findByTargets')
			->with('group', ['finance'])
			->willReturn([$binding]);

		$this->permissionSetMapper
			->expects($this->once())
			->method('findByIds')
			->with([77])
			->willReturn([$permissionSet]);

		$context = PolicyContext::fromUserId('john')
			->setGroups(['finance'])
			->setActiveContext(['type' => 'group', 'id' => 'finance']);

		$result = $this->getSource()->loadAllGroupPolicies(['signature_flow'], $context);

		$this->assertCount(1, $result['signature_flow']);
		$this->assertSame('system', $result['signature_flow'][0]->getNotes()['createdByActorScope'] ?? null);
		$this->assertTrue($result['signature_flow'][0]->getNotes()['createdBySystemAdmin'] ?? false);
	}

	public function testLoadAllGroupPoliciesReturnsEmptyArraysWhenContextHasNoGroups(): void {
		$this->bindingMapper->expects($this->never())->method('findByTargets');
		$this->permissionSetMapper->expects($this->never())->method('findByIds');

		$policyKeys = ['signature_flow', 'docmdp'];
		$result = $this->getSource()->loadAllGroupPolicies($policyKeys, PolicyContext::fromUserId('john'));

		$this->assertSame(['signature_flow' => [], 'docmdp' => []], $result);
	}

	public function testLoadAllUserPreferencesReturnsEmptyArrayWhenContextHasNoUser(): void {
		$this->db->expects($this->never())->method('getQueryBuilder');

		$result = $this->getSource()->loadAllUserPreferences(['signature_flow'], new PolicyContext());

		$this->assertSame([], $result);
	}

	public function testLoadAllUserPreferencesReturnsMappedLayersFromDatabase(): void {
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('eq')->willReturn('1=1');
		$expr->method('in')->willReturn('1=1');
		$expr->method('neq')->willReturn('1=1');

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('andWhere')->willReturnSelf();
		$qb->method('expr')->willReturn($expr);
		$qb->method('createNamedParameter')->willReturn(':p');

		$dbResult = $this->createMock(IResult::class);
		$dbResult->method('fetchAssociative')->willReturnOnConsecutiveCalls(
			['configkey' => 'policy.signature_flow', 'configvalue' => '"parallel"'],
			false,
		);
		$dbResult->expects($this->once())->method('closeCursor');
		$qb->method('executeQuery')->willReturn($dbResult);

		$this->db->expects($this->once())->method('getQueryBuilder')->willReturn($qb);

		$source = $this->getSource();
		$result = $source->loadAllUserPreferences(
			['signature_flow', 'docmdp'],
			PolicyContext::fromUserId('john'),
		);

		$this->assertArrayHasKey('signature_flow', $result);
		$this->assertArrayNotHasKey('docmdp', $result);
		$this->assertSame('user', $result['signature_flow']->getScope());
		$this->assertSame('parallel', $result['signature_flow']->getValue());
	}

	public function testLoadAllUserPoliciesReturnsEmptyArrayWhenContextHasNoUser(): void {
		$this->db->expects($this->never())->method('getQueryBuilder');

		$this->assertSame([], $this->getSource()->loadAllUserPolicies(['signature_flow'], new PolicyContext()));
	}

	#[DataProvider('providerStoredUserPolicyPayloadCases')]
	public function testLoadAllUserPoliciesInterpretsStoredPayloads(
		string $storedPayload,
		?string $expectedValue,
		bool $expectedAllowChildOverride,
		array $expectedAllowedValues,
	): void {
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('eq')->willReturn('1=1');
		$expr->method('in')->willReturn('1=1');
		$expr->method('neq')->willReturn('1=1');

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('andWhere')->willReturnSelf();
		$qb->method('groupBy')->willReturnSelf();
		$qb->method('expr')->willReturn($expr);
		$qb->method('createNamedParameter')->willReturn(':p');

		$dbResult = $this->createMock(IResult::class);
		$dbResult->method('fetchAssociative')->willReturnOnConsecutiveCalls(
			['configkey' => 'policy.signature_flow.assigned', 'configvalue' => $storedPayload],
			false,
		);
		$dbResult->expects($this->once())->method('closeCursor');
		$qb->method('executeQuery')->willReturn($dbResult);

		$this->db->expects($this->once())->method('getQueryBuilder')->willReturn($qb);

		$result = $this->getSource()->loadAllUserPolicies(
			['signature_flow'],
			PolicyContext::fromUserId('john'),
		);

		if ($expectedValue === null) {
			$this->assertSame([], $result);
			return;
		}

		$this->assertArrayHasKey('signature_flow', $result);
		$this->assertSame('user_policy', $result['signature_flow']->getScope());
		$this->assertSame($expectedValue, $result['signature_flow']->getValue());
		$this->assertSame($expectedAllowChildOverride, $result['signature_flow']->isAllowChildOverride());
		$this->assertSame($expectedAllowedValues, $result['signature_flow']->getAllowedValues());
	}

	public function testLoadAllRuleCountsReturnsZeroCountsWhenNoBindingsExist(): void {
		$this->bindingMapper
			->expects($this->once())
			->method('findByTargetType')
			->with('group')
			->willReturn([]);

		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('eq')->willReturn('1=1');
		$expr->method('in')->willReturn('1=1');
		$expr->method('neq')->willReturn('1=1');

		$userCountsQuery = $this->createMock(IQueryBuilder::class);
		$userCountsQuery->method('select')->willReturnSelf();
		$userCountsQuery->method('selectAlias')->willReturnSelf();
		$userCountsQuery->method('from')->willReturnSelf();
		$userCountsQuery->method('where')->willReturnSelf();
		$userCountsQuery->method('andWhere')->willReturnSelf();
		$userCountsQuery->method('groupBy')->willReturnSelf();
		$userCountsQuery->method('expr')->willReturn($expr);
		$userCountsQuery->method('func')->willReturn($this->createMock(\OCP\DB\QueryBuilder\IFunctionBuilder::class));
		$userCountsQuery->method('createNamedParameter')->willReturn(':p');

		$userCountsResult = $this->createMock(IResult::class);
		$userCountsResult->method('fetchAssociative')->willReturn(false);
		$userCountsResult->expects($this->once())->method('closeCursor');
		$userCountsQuery->method('executeQuery')->willReturn($userCountsResult);

		$systemConfigQuery = $this->createMock(IQueryBuilder::class);
		$systemConfigQuery->method('select')->willReturnSelf();
		$systemConfigQuery->method('from')->willReturnSelf();
		$systemConfigQuery->method('where')->willReturnSelf();
		$systemConfigQuery->method('expr')->willReturn($expr);
		$systemConfigQuery->method('createNamedParameter')->willReturn(':p');

		$systemConfigResult = $this->createMock(IResult::class);
		$systemConfigResult->method('fetchAssociative')->willReturn(false);
		$systemConfigResult->expects($this->once())->method('closeCursor');
		$systemConfigQuery->method('executeQuery')->willReturn($systemConfigResult);

		$this->db->expects($this->exactly(2))
			->method('getQueryBuilder')
			->willReturnOnConsecutiveCalls($userCountsQuery, $systemConfigQuery);

		$result = $this->getSource()->loadAllRuleCounts();

		$this->assertArrayHasKey('signature_flow', $result);
		$this->assertSame(0, $result['signature_flow']['groupCount']);
		$this->assertSame(0, $result['signature_flow']['userCount']);
	}

	public function testLoadRuleCountsCountsFilteredGroupAndUserRules(): void {
		$binding = new PermissionSetBinding();
		$binding->setPermissionSetId(42);
		$binding->setTargetType('group');
		$binding->setTargetId('finance');

		$permissionSet = new PermissionSet();
		$permissionSet->setId(42);
		$permissionSet->setPolicyJson([
			'signature_flow' => [
				'defaultValue' => 'ordered_numeric',
				'allowChildOverride' => false,
				'visibleToChild' => true,
				'allowedValues' => ['ordered_numeric'],
			],
			'docmdp' => [
				'defaultValue' => null,
				'allowChildOverride' => false,
				'visibleToChild' => true,
				'allowedValues' => [],
			],
		]);

		$this->bindingMapper
			->expects($this->once())
			->method('findByTargets')
			->with('group', ['finance'])
			->willReturn([$binding]);

		$this->permissionSetMapper
			->expects($this->once())
			->method('findByIds')
			->with([42])
			->willReturn([$permissionSet]);

		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('eq')->willReturn('1=1');
		$expr->method('in')->willReturn('1=1');
		$expr->method('neq')->willReturn('1=1');

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('selectAlias')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('andWhere')->willReturnSelf();
		$qb->method('groupBy')->willReturnSelf();
		$qb->method('expr')->willReturn($expr);
		$qb->method('createNamedParameter')->willReturn(':p');
		$qb->method('createFunction')->willReturn('COUNT(DISTINCT userid)');

		$dbResult = $this->createMock(IResult::class);
		$dbResult->method('fetchAssociative')->willReturnOnConsecutiveCalls(
			['configkey' => 'policy.signature_flow.assigned', 'user_count' => '2'],
			false,
		);
		$dbResult->expects($this->once())->method('closeCursor');
		$qb->method('executeQuery')->willReturn($dbResult);

		$systemConfigQuery = $this->createMock(IQueryBuilder::class);
		$systemConfigQuery->method('select')->willReturnSelf();
		$systemConfigQuery->method('from')->willReturnSelf();
		$systemConfigQuery->method('where')->willReturnSelf();
		$systemConfigQuery->method('andWhere')->willReturnSelf();
		$systemConfigQuery->method('expr')->willReturn($expr);
		$systemConfigQuery->method('createNamedParameter')->willReturn(':p');

		$systemConfigResult = $this->createMock(IResult::class);
		$systemConfigResult->method('fetchAssociative')->willReturn(false);
		$systemConfigResult->expects($this->once())->method('closeCursor');
		$systemConfigQuery->method('executeQuery')->willReturn($systemConfigResult);

		$this->db->expects($this->exactly(2))
			->method('getQueryBuilder')
			->willReturnOnConsecutiveCalls($qb, $systemConfigQuery);

		$result = $this->getSource()->loadRuleCounts(['', 'finance', 'finance'], ['', 'john', 'john']);

		$this->assertArrayHasKey('signature_flow', $result);
		$this->assertSame(1, $result['signature_flow']['groupCount']);
		$this->assertSame(2, $result['signature_flow']['userCount']);

		$this->assertArrayHasKey('docmdp', $result);
		$this->assertSame(0, $result['docmdp']['groupCount']);
		$this->assertSame(0, $result['docmdp']['userCount']);
	}

	public function testLoadRuleCountsCountsExplicitSystemRuleWhenUserListIsEmpty(): void {
		$this->bindingMapper
			->expects($this->once())
			->method('findByTargets')
			->with('group', ['finance'])
			->willReturn([]);

		$this->permissionSetMapper
			->expects($this->once())
			->method('findByIds')
			->with([])
			->willReturn([]);

		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('eq')->willReturn('1=1');
		$expr->method('in')->willReturn('1=1');

		$systemConfigQuery = $this->createMock(IQueryBuilder::class);
		$systemConfigQuery->method('select')->willReturnSelf();
		$systemConfigQuery->method('from')->willReturnSelf();
		$systemConfigQuery->method('where')->willReturnSelf();
		$systemConfigQuery->method('andWhere')->willReturnSelf();
		$systemConfigQuery->method('expr')->willReturn($expr);
		$systemConfigQuery->method('createNamedParameter')->willReturn(':p');

		$systemConfigResult = $this->createMock(IResult::class);
		$systemConfigResult->method('fetchAssociative')->willReturnOnConsecutiveCalls(
			['configkey' => 'policy.signature_flow.system'],
			false,
		);
		$systemConfigResult->expects($this->once())->method('closeCursor');
		$systemConfigQuery->method('executeQuery')->willReturn($systemConfigResult);

		$this->db->expects($this->once())
			->method('getQueryBuilder')
			->willReturn($systemConfigQuery);

		$result = $this->getSource()->loadRuleCounts(['finance'], []);

		$this->assertSame(0, $result['signature_flow']['groupCount']);
		$this->assertSame(0, $result['signature_flow']['userCount']);
		$this->assertSame(1, $result['signature_flow']['everyoneCount']);
	}

	public function testLoadAllRuleCountsAggregatesGroupAndUserCounts(): void {
		$binding = new PermissionSetBinding();
		$binding->setPermissionSetId(10);
		$binding->setTargetType('group');
		$binding->setTargetId('finance');

		$permissionSet = new PermissionSet();
		$permissionSet->setId(10);
		$permissionSet->setPolicyJson([
			'signature_flow' => ['defaultValue' => 'ordered_numeric', 'allowChildOverride' => false, 'visibleToChild' => true, 'allowedValues' => ['ordered_numeric']],
		]);

		$this->bindingMapper
			->expects($this->once())
			->method('findByTargetType')
			->with('group')
			->willReturn([$binding]);

		$this->permissionSetMapper
			->expects($this->once())
			->method('findByIds')
			->with([10])
			->willReturn([$permissionSet]);

		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('eq')->willReturn('1=1');
		$expr->method('in')->willReturn('1=1');
		$expr->method('neq')->willReturn('1=1');

		$userCountsQuery = $this->createMock(IQueryBuilder::class);
		$userCountsQuery->method('select')->willReturnSelf();
		$userCountsQuery->method('selectAlias')->willReturnSelf();
		$userCountsQuery->method('from')->willReturnSelf();
		$userCountsQuery->method('where')->willReturnSelf();
		$userCountsQuery->method('andWhere')->willReturnSelf();
		$userCountsQuery->method('groupBy')->willReturnSelf();
		$userCountsQuery->method('expr')->willReturn($expr);
		$userCountsQuery->method('func')->willReturn($this->createMock(\OCP\DB\QueryBuilder\IFunctionBuilder::class));
		$userCountsQuery->method('createNamedParameter')->willReturn(':p');

		$userCountsResult = $this->createMock(IResult::class);
		$userCountsResult->method('fetchAssociative')->willReturnOnConsecutiveCalls(
			['configkey' => 'policy.signature_flow.assigned', 'user_count' => '3'],
			false,
		);
		$userCountsResult->expects($this->once())->method('closeCursor');
		$userCountsQuery->method('executeQuery')->willReturn($userCountsResult);

		$systemConfigQuery = $this->createMock(IQueryBuilder::class);
		$systemConfigQuery->method('select')->willReturnSelf();
		$systemConfigQuery->method('from')->willReturnSelf();
		$systemConfigQuery->method('where')->willReturnSelf();
		$systemConfigQuery->method('expr')->willReturn($expr);
		$systemConfigQuery->method('createNamedParameter')->willReturn(':p');

		$systemConfigResult = $this->createMock(IResult::class);
		$systemConfigResult->method('fetchAssociative')->willReturn(false);
		$systemConfigResult->expects($this->once())->method('closeCursor');
		$systemConfigQuery->method('executeQuery')->willReturn($systemConfigResult);

		$this->db->expects($this->exactly(2))
			->method('getQueryBuilder')
			->willReturnOnConsecutiveCalls($userCountsQuery, $systemConfigQuery);

		$result = $this->getSource()->loadAllRuleCounts();

		$this->assertSame(1, $result['signature_flow']['groupCount']);
		$this->assertSame(3, $result['signature_flow']['userCount']);
		$this->assertSame(0, $result['docmdp']['groupCount']);
		$this->assertSame(0, $result['docmdp']['userCount']);
	}

	public function testLoadAllRuleCountsCountsExplicitSystemRuleEvenWhenValueMatchesDefault(): void {
		$this->bindingMapper
			->expects($this->once())
			->method('findByTargetType')
			->with('group')
			->willReturn([]);

		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('eq')->willReturn('1=1');
		$expr->method('in')->willReturn('1=1');
		$expr->method('neq')->willReturn('1=1');

		$userCountsQuery = $this->createMock(IQueryBuilder::class);
		$userCountsQuery->method('select')->willReturnSelf();
		$userCountsQuery->method('selectAlias')->willReturnSelf();
		$userCountsQuery->method('from')->willReturnSelf();
		$userCountsQuery->method('where')->willReturnSelf();
		$userCountsQuery->method('andWhere')->willReturnSelf();
		$userCountsQuery->method('groupBy')->willReturnSelf();
		$userCountsQuery->method('expr')->willReturn($expr);
		$userCountsQuery->method('func')->willReturn($this->createMock(\OCP\DB\QueryBuilder\IFunctionBuilder::class));
		$userCountsQuery->method('createNamedParameter')->willReturn(':p');

		$userCountsResult = $this->createMock(IResult::class);
		$userCountsResult->method('fetchAssociative')->willReturn(false);
		$userCountsResult->expects($this->once())->method('closeCursor');
		$userCountsQuery->method('executeQuery')->willReturn($userCountsResult);

		$systemConfigQuery = $this->createMock(IQueryBuilder::class);
		$systemConfigQuery->method('select')->willReturnSelf();
		$systemConfigQuery->method('from')->willReturnSelf();
		$systemConfigQuery->method('where')->willReturnSelf();
		$systemConfigQuery->method('andWhere')->willReturnSelf();
		$systemConfigQuery->method('expr')->willReturn($expr);
		$systemConfigQuery->method('createNamedParameter')->willReturn(':p');

		$systemConfigResult = $this->createMock(IResult::class);
		$systemConfigResult->method('fetchAssociative')->willReturnOnConsecutiveCalls(
			['configkey' => 'policy.signature_flow.system'],
			false,
		);
		$systemConfigResult->expects($this->once())->method('closeCursor');
		$systemConfigQuery->method('executeQuery')->willReturn($systemConfigResult);

		$this->db->expects($this->exactly(2))
			->method('getQueryBuilder')
			->willReturnOnConsecutiveCalls($userCountsQuery, $systemConfigQuery);

		$result = $this->getSource()->loadAllRuleCounts();

		$this->assertSame(1, $result['signature_flow']['everyoneCount']);
	}

	private function setStoredAppConfigString(string $key, string $value): void {
		$this->coreAppConfig->setValueString(Application::APP_ID, $key, $value);
	}

	private function setStoredAppConfigInt(string $key, int $value): void {
		$this->coreAppConfig->setValueInt(Application::APP_ID, $key, $value);
	}

	private function setStoredAppConfigArray(string $key, array $value): void {
		$this->coreAppConfig->setValueArray(Application::APP_ID, $key, $value);
	}

	private function assertStoredAppConfigString(string $key, string $expectedValue): void {
		$this->assertSame($expectedValue, $this->coreAppConfig->getValueString(Application::APP_ID, $key, ''));
	}

	private function assertStoredAppConfigInt(string $key, int $expectedValue): void {
		$this->assertSame($expectedValue, $this->coreAppConfig->getValueInt(Application::APP_ID, $key, -1));
	}

	private function assertAppConfigMissing(string $key): void {
		$this->assertFalse($this->coreAppConfig->hasKey(Application::APP_ID, $key));
	}

	private function getSource(): PolicySource {
		return new PolicySource(
			$this->appConfig,
			$this->permissionSetMapper,
			$this->bindingMapper,
			$this->registry,
			$this->db,
			$this->l10n,
		);
	}
}
