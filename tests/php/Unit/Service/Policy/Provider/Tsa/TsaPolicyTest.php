<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Tsa;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Policy\Model\ActorRole;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Provider\Tsa\TsaPolicy;
use OCA\Libresign\Service\Policy\Provider\Tsa\TsaPolicyManagedValue;
use OCA\Libresign\Service\Policy\Provider\Tsa\TsaPolicyValue;
use OCA\Libresign\Settings\Admin;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TsaPolicyTest extends TestCase {
	public function testProviderBuildsTsaDefinition(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$provider = new TsaPolicy(new TsaPolicyManagedValue($appConfig));
		$this->assertSame([TsaPolicy::KEY], $provider->keys());

		$definition = $provider->get(TsaPolicy::KEY);
		$this->assertSame(TsaPolicy::KEY, $definition->key());
		$this->assertSame(
			TsaPolicyValue::encode(TsaPolicyValue::defaults()),
			$definition->defaultSystemValue(),
		);
	}

	public function testProviderSupportsDelegatedGroupAdminOverlays(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$provider = new TsaPolicy(new TsaPolicyManagedValue($appConfig));
		$definition = $provider->get(TsaPolicy::KEY);

		$this->assertTrue($definition->supportsGroupAdminDelegation());
		$this->assertFalse($definition->supportsUserPreference());
	}

	public function testGroupAdminCanManageDelegatedTsaGroupPolicy(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$provider = new TsaPolicy(new TsaPolicyManagedValue($appConfig));
		$definition = $provider->get(TsaPolicy::KEY);
		$context = (new PolicyContext())->setActorRole(ActorRole::groupAdmin(1));

		$canManage = $definition->canCurrentActorManageGroupPolicy(
			$context,
			null,
			[
				self::buildPolicyLayer(
					scope: 'group',
					allowChildOverride: false,
					visibleToChild: true,
					value: TsaPolicyValue::encode(TsaPolicyValue::defaults()),
					delegatedFromSystemCreatedSeed: true,
				),
			],
		);

		$this->assertTrue($canManage);
	}

	#[DataProvider('nonBasicAuthPayloadProvider')]
	public function testProviderNormalizesNonBasicAuthPayload(array $payload, string $expectedNormalized): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$provider = new TsaPolicy(new TsaPolicyManagedValue($appConfig));
		$definition = $provider->get(TsaPolicy::KEY);

		$appConfig
			->expects($this->once())
			->method('hasKey')
			->with(Application::APP_ID, TsaPolicy::PASSWORD_APP_CONFIG_KEY)
			->willReturn(true);
		$appConfig
			->expects($this->once())
			->method('deleteKey')
			->with(Application::APP_ID, TsaPolicy::PASSWORD_APP_CONFIG_KEY);
		$appConfig
			->expects($this->never())
			->method('getValueString');
		$appConfig
			->expects($this->never())
			->method('setValueString');

		$normalized = $definition->normalizeValue($payload);

		$this->assertSame($expectedNormalized, $normalized);
	}

	#[DataProvider('basicAuthFreshPasswordProvider')]
	public function testProviderNormalizesBasicAuthWithFreshPassword(array $payload, string $expectedNormalized, string $expectedStoredPassword): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$provider = new TsaPolicy(new TsaPolicyManagedValue($appConfig));
		$definition = $provider->get(TsaPolicy::KEY);

		$appConfig
			->expects($this->never())
			->method('deleteKey');
		$appConfig
			->expects($this->once())
			->method('getValueString')
			->with(Application::APP_ID, TsaPolicy::PASSWORD_APP_CONFIG_KEY, '')
			->willReturn('');
		$appConfig
			->expects($this->once())
			->method('setValueString')
			->with(Application::APP_ID, TsaPolicy::PASSWORD_APP_CONFIG_KEY, $expectedStoredPassword, false, true);

		$normalized = $definition->normalizeValue($payload);

		$this->assertSame($expectedNormalized, $normalized);
	}

	#[DataProvider('basicAuthPersistedPasswordProvider')]
	public function testProviderNormalizesBasicAuthWithPersistedPassword(array $payload, string $expectedNormalized, string $persistedPassword): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$provider = new TsaPolicy(new TsaPolicyManagedValue($appConfig));
		$definition = $provider->get(TsaPolicy::KEY);

		$appConfig
			->expects($this->never())
			->method('deleteKey');
		$appConfig
			->expects($this->once())
			->method('getValueString')
			->with(Application::APP_ID, TsaPolicy::PASSWORD_APP_CONFIG_KEY, '')
			->willReturn($persistedPassword);
		$appConfig
			->expects($this->never())
			->method('setValueString');

		$normalized = $definition->normalizeValue($payload);

		$this->assertSame($expectedNormalized, $normalized);
	}

	/** @return array<string, array{0: array<string, string>, 1: string}> */
	public static function nonBasicAuthPayloadProvider(): array {
		return [
			'non-basic authentication clears username' => [
				[
					'url' => ' https://freetsa.org/tsr ',
					'policy_oid' => '1.2.3.4',
					'auth_type' => 'none',
					'username' => ' admin ',
				],
				'{"url":"https://freetsa.org/tsr","policy_oid":"1.2.3.4","auth_type":"none","username":""}',
			],
		];
	}

	/** @return array<string, array{0: array<string, string>, 1: string, 2: string}> */
	public static function basicAuthFreshPasswordProvider(): array {
		return [
			'basic authentication stores fresh password' => [
				[
					'url' => ' https://tsa.example.test/tsr ',
					'policy_oid' => '1.2.3.4.1',
					'auth_type' => 'basic',
					'username' => ' tsa-user ',
					'password' => ' topsecret ',
				],
				'{"url":"https://tsa.example.test/tsr","policy_oid":"1.2.3.4.1","auth_type":"basic","username":"tsa-user"}',
				'topsecret',
			],
		];
	}

	/** @return array<string, array{0: array<string, string>, 1: string, 2: string}> */
	public static function basicAuthPersistedPasswordProvider(): array {
		return [
			'basic authentication keeps persisted password when placeholder is provided' => [
				[
					'url' => 'https://tsa.example.test/tsr',
					'auth_type' => 'basic',
					'username' => 'tsa-user',
					'password' => Admin::PASSWORD_PLACEHOLDER,
				],
				'{"url":"https://tsa.example.test/tsr","policy_oid":"","auth_type":"basic","username":"tsa-user"}',
				'already-stored-secret',
			],
		];
	}

	private static function buildPolicyLayer(
		string $scope,
		bool $allowChildOverride,
		bool $visibleToChild,
		mixed $value,
		bool $createdBySystemAdmin = false,
		bool $delegatedFromSystemCreatedSeed = false,
	): PolicyLayer {
		return (new PolicyLayer())
			->setScope($scope)
			->setAllowChildOverride($allowChildOverride)
			->setVisibleToChild($visibleToChild)
			->setValue($value)
			->setCreatedBySystemAdmin($createdBySystemAdmin)
			->setDelegatedFromSystemCreatedSeed($delegatedFromSystemCreatedSeed);
	}
}
