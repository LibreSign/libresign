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
		$this->appConfig
			->expects($this->once())
			->method('getAppValueString')
			->with('signature_flow', 'none')
			->willReturn('ordered_numeric');

		$source = $this->getSource();
		$layer = $source->loadSystemPolicy('signature_flow');

		$this->assertNotNull($layer);
		$this->assertSame('system', $layer->getScope());
		$this->assertSame('ordered_numeric', $layer->getValue());
		$this->assertFalse($layer->isAllowChildOverride());
		$this->assertSame(['ordered_numeric'], $layer->getAllowedValues());
	}

	public function testLoadSystemPolicyReturnsInheritableLayerWhenAppConfigMatchesDefault(): void {
		$this->appConfig
			->expects($this->once())
			->method('getAppValueString')
			->with('signature_flow', 'none')
			->willReturn('none');

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
		$this->appConfig
			->expects($this->once())
			->method('deleteAppValue')
			->with('signature_flow');

		$source = $this->getSource();
		$source->saveSystemPolicy('signature_flow', 'none');
	}

	public function testSaveSystemPolicyNormalizesAndPersistsAppConfigValue(): void {
		$this->appConfig
			->expects($this->once())
			->method('setAppValueString')
			->with('signature_flow', 'ordered_numeric');

		$source = $this->getSource();
		$source->saveSystemPolicy('signature_flow', 2);
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
