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
use OCA\Libresign\Service\Policy\Provider\Signature\SignatureFlowPolicy;
use OCA\Libresign\Service\Policy\Runtime\PolicyRegistry;
use OCA\Libresign\Service\Policy\Runtime\PolicySource;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Services\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class PolicySourceTest extends TestCase {
	private IAppConfig&MockObject $appConfig;
	private PermissionSetMapper&MockObject $permissionSetMapper;
	private PermissionSetBindingMapper&MockObject $bindingMapper;
	private PolicyRegistry $registry;

	protected function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->permissionSetMapper = $this->createMock(PermissionSetMapper::class);
		$this->bindingMapper = $this->createMock(PermissionSetBindingMapper::class);
		$container = $this->createMock(ContainerInterface::class);
		$container
			->method('get')
			->with(SignatureFlowPolicy::class)
			->willReturn(new SignatureFlowPolicy());
		$this->registry = new PolicyRegistry($container);
	}

	public function testLoadSystemPolicyReturnsForcedLayerWhenAppConfigIsSet(): void {
		$calls = 0;
		$this->appConfig
			->expects($this->exactly(2))
			->method('getAppValueString')
			->willReturnCallback(static function (string $key, string $default) use (&$calls): string {
				$calls += 1;
				if ($key === 'signature_flow' && $default === '') {
					return 'ordered_numeric';
				}

				if ($key === 'signature_flow.allow_child_override' && $default === '0') {
					return '0';
				}

				throw new \RuntimeException('Unexpected app config key request: ' . $key);
			});

		$source = $this->getSource();
		$layer = $source->loadSystemPolicy('signature_flow');

		$this->assertNotNull($layer);
		$this->assertSame('system', $layer->getScope());
		$this->assertSame('ordered_numeric', $layer->getValue());
		$this->assertFalse($layer->isAllowChildOverride());
		$this->assertSame(['ordered_numeric'], $layer->getAllowedValues());
		$this->assertSame(2, $calls);
	}

	public function testLoadSystemPolicyReturnsInheritableLayerWhenAppConfigMatchesDefault(): void {
		$this->appConfig
			->expects($this->once())
			->method('getAppValueString')
			->with('signature_flow', '')
			->willReturn('');

		$source = $this->getSource();
		$layer = $source->loadSystemPolicy('signature_flow');

		$this->assertNotNull($layer);
		$this->assertSame('none', $layer->getValue());
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
		$source->saveUserPreference('signature_flow', PolicyContext::fromUserId('john'), 2);
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

		$this->assertSame(['signature_flow', 'signature_flow.allow_child_override'], $deletedKeys);
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
		$source->saveSystemPolicy('signature_flow', 2, true);

		$this->assertSame([
			'signature_flow' => 'ordered_numeric',
			'signature_flow.allow_child_override' => '1',
		], $savedValues);
	}

	public function testLoadSystemPolicyRespectsPersistedAllowChildOverride(): void {
		$calls = 0;
		$this->appConfig
			->expects($this->exactly(2))
			->method('getAppValueString')
			->willReturnCallback(static function (string $key, string $default) use (&$calls): string {
				$calls += 1;
				if ($key === 'signature_flow' && $default === '') {
					return 'ordered_numeric';
				}

				if ($key === 'signature_flow.allow_child_override' && $default === '0') {
					return '1';
				}

				throw new \RuntimeException('Unexpected app config key request: ' . $key);
			});

		$source = $this->getSource();
		$layer = $source->loadSystemPolicy('signature_flow');

		$this->assertNotNull($layer);
		$this->assertSame('ordered_numeric', $layer->getValue());
		$this->assertTrue($layer->isAllowChildOverride());
		$this->assertSame([], $layer->getAllowedValues());
		$this->assertSame(2, $calls);
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
		$source->saveGroupPolicy('signature_flow', 'finance', 2, false);
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

	private function getSource(): PolicySource {
		return new PolicySource(
			$this->appConfig,
			$this->permissionSetMapper,
			$this->bindingMapper,
			$this->registry,
		);
	}
}
