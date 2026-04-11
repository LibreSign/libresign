<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Runtime;

use OCA\Libresign\Db\PermissionSet;
use OCA\Libresign\Db\PermissionSetBinding;
use OCA\Libresign\Db\PermissionSetBindingMapper;
use OCA\Libresign\Db\PermissionSetMapper;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Provider\DocMdp\DocMdpPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCA\Libresign\Service\Policy\Provider\Signature\SignatureFlowPolicy;
use OCA\Libresign\Service\Policy\Runtime\PolicyRegistry;
use OCA\Libresign\Service\Policy\Runtime\PolicySource;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Services\IAppConfig;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class PolicySourceTest extends TestCase {
	private IAppConfig&MockObject $appConfig;
	private PermissionSetMapper&MockObject $permissionSetMapper;
	private PermissionSetBindingMapper&MockObject $bindingMapper;
	private IDBConnection&MockObject $db;
	private PolicyRegistry $registry;

	protected function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->permissionSetMapper = $this->createMock(PermissionSetMapper::class);
		$this->bindingMapper = $this->createMock(PermissionSetBindingMapper::class);
		$this->db = $this->createMock(IDBConnection::class);
		$container = $this->createMock(ContainerInterface::class);
		$container
			->method('get')
			->willReturnCallback(static function (string $class): object {
				return match ($class) {
					FooterPolicy::class => new FooterPolicy(),
					SignatureFlowPolicy::class => new SignatureFlowPolicy(),
					DocMdpPolicy::class => new DocMdpPolicy(),
					default => throw new \RuntimeException('Unexpected provider class: ' . $class),
				};
			});
		$this->registry = new PolicyRegistry($container);
	}

	public function testLoadSystemPolicyReturnsForcedLayerWhenAppConfigIsSet(): void {
		$calls = 0;
		$this->appConfig
			->expects($this->once())
			->method('hasAppKey')
			->with('policy.signature_flow.system')
			->willReturn(true);

		$this->appConfig
			->expects($this->exactly(2))
			->method('getAppValueString')
			->willReturnCallback(static function (string $key, string $default) use (&$calls): string {
				$calls += 1;
				if ($key === 'policy.signature_flow.system' && $default === 'none') {
					return 'ordered_numeric';
				}

				if ($key === 'policy.signature_flow.system.allow_child_override' && $default === '0') {
					return '0';
				}

				throw new \RuntimeException('Unexpected app config key request: ' . $key);
			});

		$source = $this->getSource();
		$layer = $source->loadSystemPolicy('signature_flow');

		$this->assertNotNull($layer);
		$this->assertSame('global', $layer->getScope());
		$this->assertSame('ordered_numeric', $layer->getValue());
		$this->assertFalse($layer->isAllowChildOverride());
		$this->assertSame(['ordered_numeric'], $layer->getAllowedValues());
		$this->assertSame(2, $calls);
	}

	public function testLoadSystemPolicyReturnsInheritableLayerWhenAppConfigMatchesDefault(): void {
		$this->appConfig
			->expects($this->once())
			->method('hasAppKey')
			->with('policy.signature_flow.system')
			->willReturn(false);

		$this->appConfig
			->expects($this->never())
			->method('getAppValueString');

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

	public function testLoadUserPreferenceReturnsLayerFromUserConfig(): void {
		$this->appConfig
			->expects($this->once())
			->method('getUserValue')
			->with('john', 'policy.signature_flow', '')
			->willReturn('parallel');

		$source = $this->getSource();
		$layer = $source->loadUserPreference('signature_flow', PolicyContext::fromUserId('john'));

		$this->assertNotNull($layer);
		$this->assertSame('user', $layer->getScope());
		$this->assertSame('parallel', $layer->getValue());
	}

	public function testSaveUserPreferenceNormalizesAndPersistsUserConfigValue(): void {
		$this->appConfig
			->expects($this->once())
			->method('setUserValue')
			->with('john', 'policy.signature_flow', 'ordered_numeric');

		$source = $this->getSource();
		$source->saveUserPreference('signature_flow', PolicyContext::fromUserId('john'), 'ordered_numeric');
	}

	public function testClearUserPreferenceDeletesUserConfig(): void {
		$this->appConfig
			->expects($this->once())
			->method('deleteUserValue')
			->with('john', 'policy.signature_flow');

		$source = $this->getSource();
		$source->clearUserPreference('signature_flow', PolicyContext::fromUserId('john'));
	}

	public function testSaveSystemPolicyDeletesAppConfigWhenValueMatchesDefault(): void {
		$deletedKeys = [];
		$this->appConfig
			->expects($this->exactly(2))
			->method('deleteAppValue')
			->willReturnCallback(static function (string $key) use (&$deletedKeys): bool {
				$deletedKeys[] = $key;
				return true;
			});

		$source = $this->getSource();
		$source->saveSystemPolicy('signature_flow', 'none');

		$this->assertSame(['policy.signature_flow.system', 'policy.signature_flow.system.allow_child_override'], $deletedKeys);
	}

	public function testSaveSystemPolicyPersistsExplicitDefaultWhenAllowChildOverrideIsTrue(): void {
		$savedValues = [];
		$this->appConfig
			->expects($this->exactly(2))
			->method('setAppValueString')
			->willReturnCallback(static function (string $key, string $value) use (&$savedValues): bool {
				$savedValues[$key] = $value;
				return true;
			});

		$this->appConfig
			->expects($this->never())
			->method('deleteAppValue');

		$source = $this->getSource();
		$source->saveSystemPolicy('signature_flow', 'none', true);

		$this->assertSame([
			'policy.signature_flow.system' => 'none',
			'policy.signature_flow.system.allow_child_override' => '1',
		], $savedValues);
	}

	public function testSaveSystemPolicyNormalizesAndPersistsAppConfigValue(): void {
		$savedValues = [];
		$this->appConfig
			->expects($this->exactly(2))
			->method('setAppValueString')
			->willReturnCallback(static function (string $key, string $value) use (&$savedValues): bool {
				$savedValues[$key] = $value;
				return true;
			});

		$source = $this->getSource();
		$source->saveSystemPolicy('signature_flow', 'ordered_numeric', true);

		$this->assertSame([
			'policy.signature_flow.system' => 'ordered_numeric',
			'policy.signature_flow.system.allow_child_override' => '1',
		], $savedValues);
	}

	public function testLoadSystemPolicyRespectsPersistedAllowChildOverride(): void {
		$calls = 0;
		$this->appConfig
			->expects($this->once())
			->method('hasAppKey')
			->with('policy.signature_flow.system')
			->willReturn(true);

		$this->appConfig
			->expects($this->exactly(2))
			->method('getAppValueString')
			->willReturnCallback(static function (string $key, string $default) use (&$calls): string {
				$calls += 1;
				if ($key === 'policy.signature_flow.system' && $default === 'none') {
					return 'ordered_numeric';
				}

				if ($key === 'policy.signature_flow.system.allow_child_override' && $default === '0') {
					return '1';
				}

				throw new \RuntimeException('Unexpected app config key request: ' . $key);
			});

		$source = $this->getSource();
		$layer = $source->loadSystemPolicy('signature_flow');

		$this->assertNotNull($layer);
		$this->assertSame('ordered_numeric', $layer->getValue());
		$this->assertSame('global', $layer->getScope());
		$this->assertTrue($layer->isAllowChildOverride());
		$this->assertSame([], $layer->getAllowedValues());
		$this->assertSame(2, $calls);
	}

	public function testLoadSystemPolicyTreatsPersistedDefaultAsExplicitWhenAllowChildOverrideIsSet(): void {
		$this->appConfig
			->expects($this->once())
			->method('hasAppKey')
			->with('policy.signature_flow.system')
			->willReturn(true);

		$this->appConfig
			->expects($this->exactly(2))
			->method('getAppValueString')
			->willReturnCallback(static function (string $key, string $default): string {
				if ($key === 'policy.signature_flow.system' && $default === 'none') {
					return 'none';
				}

				if ($key === 'policy.signature_flow.system.allow_child_override' && $default === '0') {
					return '1';
				}

				throw new \RuntimeException('Unexpected app config key request: ' . $key);
			});

		$source = $this->getSource();
		$layer = $source->loadSystemPolicy('signature_flow');

		$this->assertNotNull($layer);
		$this->assertSame('none', $layer->getValue());
		$this->assertSame('global', $layer->getScope());
		$this->assertTrue($layer->isAllowChildOverride());
		$this->assertSame([], $layer->getAllowedValues());
	}

	public function testLoadSystemPolicyReturnsDocMdpLayerFromTypedIntConfig(): void {
		$this->appConfig
			->expects($this->once())
			->method('hasAppKey')
			->with('docmdp_level')
			->willReturn(true);

		$this->appConfig
			->expects($this->once())
			->method('getAppValueInt')
			->with('docmdp_level', 0)
			->willReturn(2);

		$this->appConfig
			->expects($this->once())
			->method('getAppValueString')
			->willReturnCallback(static function (string $key, string $default): string {
				if ($key === 'docmdp_level.allow_child_override' && $default === '0') {
					return '0';
				}

				throw new \RuntimeException('Unexpected app config key request: ' . $key);
			});

		$source = $this->getSource();
		$layer = $source->loadSystemPolicy(DocMdpPolicy::KEY);

		$this->assertNotNull($layer);
		$this->assertSame('global', $layer->getScope());
		$this->assertSame(2, $layer->getValue());
		$this->assertFalse($layer->isAllowChildOverride());
		$this->assertSame([2], $layer->getAllowedValues());
	}

	public function testSaveSystemPolicyPersistsDocMdpLevelAsTypedInt(): void {
		$this->appConfig
			->expects($this->once())
			->method('setAppValueInt')
			->with('docmdp_level', 2)
			->willReturn(true);

		$this->appConfig
			->expects($this->once())
			->method('setAppValueString')
			->with('docmdp_level.allow_child_override', '0')
			->willReturn(true);

		$source = $this->getSource();
		$source->saveSystemPolicy(DocMdpPolicy::KEY, 2, false);
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
			['configkey' => 'policy.signature_flow', 'configvalue' => 'parallel'],
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

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('selectAlias')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('andWhere')->willReturnSelf();
		$qb->method('groupBy')->willReturnSelf();
		$qb->method('expr')->willReturn($expr);
		$qb->method('func')->willReturn($this->createMock(\OCP\DB\QueryBuilder\IFunctionBuilder::class));
		$qb->method('createNamedParameter')->willReturn(':p');

		$dbResult = $this->createMock(IResult::class);
		$dbResult->method('fetchAssociative')->willReturn(false);
		$dbResult->expects($this->once())->method('closeCursor');
		$qb->method('executeQuery')->willReturn($dbResult);

		$this->db->expects($this->once())->method('getQueryBuilder')->willReturn($qb);

		$result = $this->getSource()->loadAllRuleCounts();

		$this->assertArrayHasKey('signature_flow', $result);
		$this->assertSame(0, $result['signature_flow']['groupCount']);
		$this->assertSame(0, $result['signature_flow']['userCount']);
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

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('selectAlias')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('andWhere')->willReturnSelf();
		$qb->method('groupBy')->willReturnSelf();
		$qb->method('expr')->willReturn($expr);
		$qb->method('func')->willReturn($this->createMock(\OCP\DB\QueryBuilder\IFunctionBuilder::class));
		$qb->method('createNamedParameter')->willReturn(':p');

		$dbResult = $this->createMock(IResult::class);
		$dbResult->method('fetchAssociative')->willReturnOnConsecutiveCalls(
			['configkey' => 'policy.signature_flow', 'user_count' => '3'],
			false,
		);
		$dbResult->expects($this->once())->method('closeCursor');
		$qb->method('executeQuery')->willReturn($dbResult);

		$this->db->expects($this->once())->method('getQueryBuilder')->willReturn($qb);

		$result = $this->getSource()->loadAllRuleCounts();

		$this->assertSame(1, $result['signature_flow']['groupCount']);
		$this->assertSame(3, $result['signature_flow']['userCount']);
		$this->assertSame(0, $result['docmdp']['groupCount']);
		$this->assertSame(0, $result['docmdp']['userCount']);
	}

	private function getSource(): PolicySource {
		return new PolicySource(
			$this->appConfig,
			$this->permissionSetMapper,
			$this->bindingMapper,
			$this->registry,
			$this->db,
		);
	}
}
