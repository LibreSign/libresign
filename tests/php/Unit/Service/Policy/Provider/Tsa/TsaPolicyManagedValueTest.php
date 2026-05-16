<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Tsa;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Policy\Provider\Tsa\TsaPolicy;
use OCA\Libresign\Service\Policy\Provider\Tsa\TsaPolicyManagedValue;
use OCA\Libresign\Service\Policy\Provider\Tsa\TsaPolicyValue;
use OCA\Libresign\Settings\Admin;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class TsaPolicyManagedValueTest extends TestCase {
	private IAppConfig&MockObject $appConfig;
	private TsaPolicyManagedValue $managedValue;

	protected function setUp(): void {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->managedValue = new TsaPolicyManagedValue($this->appConfig);
	}

	public function testNormalizesBasicAuthPayloadAndStoresFreshPassword(): void {
		$this->appConfig
			->expects($this->once())
			->method('getValueString')
			->with(Application::APP_ID, TsaPolicy::PASSWORD_APP_CONFIG_KEY, '')
			->willReturn('');

		$this->appConfig
			->expects($this->once())
			->method('setValueString')
			->with(Application::APP_ID, TsaPolicy::PASSWORD_APP_CONFIG_KEY, 'topsecret', false, true);

		$normalized = $this->managedValue->normalizeForPersistence([
			'url' => ' https://tsa.example.test/tsr ',
			'policy_oid' => '1.2.3.4.1',
			'auth_type' => 'basic',
			'username' => ' tsa-user ',
			'password' => ' topsecret ',
		]);

		$this->assertSame(
			'{"url":"https://tsa.example.test/tsr","policy_oid":"1.2.3.4.1","auth_type":"basic","username":"tsa-user"}',
			$normalized,
		);
	}

	public function testKeepsStoredPasswordWhenPlaceholderIsProvided(): void {
		$this->appConfig
			->expects($this->once())
			->method('getValueString')
			->with(Application::APP_ID, TsaPolicy::PASSWORD_APP_CONFIG_KEY, '')
			->willReturn('already-stored-secret');

		$this->appConfig
			->expects($this->never())
			->method('setValueString');

		$normalized = $this->managedValue->normalizeForPersistence([
			'url' => 'https://tsa.example.test/tsr',
			'auth_type' => 'basic',
			'username' => 'tsa-user',
			'password' => Admin::PASSWORD_PLACEHOLDER,
		]);

		$this->assertSame(
			'{"url":"https://tsa.example.test/tsr","policy_oid":"","auth_type":"basic","username":"tsa-user"}',
			$normalized,
		);
	}

	#[DataProvider('clearPasswordProvider')]
	public function testClearsPasswordWhenTsaIsDisabledOrAuthIsNotBasic(array $payload, string $expected): void {
		$this->appConfig
			->expects($this->once())
			->method('hasKey')
			->with(Application::APP_ID, TsaPolicy::PASSWORD_APP_CONFIG_KEY)
			->willReturn(true);
		$this->appConfig
			->expects($this->once())
			->method('deleteKey')
			->with(Application::APP_ID, TsaPolicy::PASSWORD_APP_CONFIG_KEY);

		$normalized = $this->managedValue->normalizeForPersistence($payload);

		$this->assertSame($expected, $normalized);
	}

	#[DataProvider('invalidUrlProvider')]
	public function testRejectsInvalidUrl(string $url): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid URL format');

		$this->managedValue->normalizeForPersistence([
			'url' => $url,
			'auth_type' => 'none',
		]);
	}

	#[DataProvider('invalidOidProvider')]
	public function testRejectsInvalidOid(string $policyOid): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid OID format');

		$this->managedValue->normalizeForPersistence([
			'url' => 'https://tsa.example.test/tsr',
			'policy_oid' => $policyOid,
			'auth_type' => 'none',
		]);
	}

	#[DataProvider('validOidProvider')]
	public function testAcceptsValidOid(string $policyOid): void {
		$this->appConfig
			->expects($this->once())
			->method('hasKey')
			->with(Application::APP_ID, TsaPolicy::PASSWORD_APP_CONFIG_KEY)
			->willReturn(true);
		$this->appConfig
			->expects($this->once())
			->method('deleteKey')
			->with(Application::APP_ID, TsaPolicy::PASSWORD_APP_CONFIG_KEY);

		$normalized = $this->managedValue->normalizeForPersistence([
			'url' => 'https://tsa.example.test/tsr',
			'policy_oid' => $policyOid,
			'auth_type' => 'none',
		]);

		$this->assertSame(
			sprintf('{"url":"https://tsa.example.test/tsr","policy_oid":"%s","auth_type":"none","username":""}', $policyOid),
			$normalized,
		);
	}

	#[DataProvider('missingPasswordProvider')]
	public function testRequiresPasswordForBasicAuthenticationWhenNotPersisted(?string $password): void {
		$this->appConfig
			->expects($this->once())
			->method('getValueString')
			->with(Application::APP_ID, TsaPolicy::PASSWORD_APP_CONFIG_KEY, '')
			->willReturn('');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Password is required');

		$payload = [
			'url' => 'https://tsa.example.test/tsr',
			'auth_type' => 'basic',
			'username' => 'tsa-user',
		];

		if ($password !== null) {
			$payload['password'] = $password;
		}

		$this->managedValue->normalizeForPersistence($payload);
	}

	/** @return array<string, array{0: array<string, string>, 1: string}> */
	public static function clearPasswordProvider(): array {
		return [
			'url empty resets to defaults' => [
				[
					'url' => '',
					'auth_type' => 'none',
				],
				TsaPolicyValue::encode(TsaPolicyValue::defaults()),
			],
			'non basic auth clears username' => [
				[
					'url' => 'https://tsa.example.test/tsr',
					'auth_type' => 'none',
					'username' => 'tsa-user',
					'password' => 'topsecret',
				],
				'{"url":"https://tsa.example.test/tsr","policy_oid":"","auth_type":"none","username":""}',
			],
		];
	}

	/** @return array<string, array{0: string}> */
	public static function invalidUrlProvider(): array {
		return [
			'without scheme' => ['invalid-url'],
			'unsupported scheme ftp' => ['ftp://tsa.example.test/tsr'],
			'unsupported scheme file' => ['file:///tmp/tsa'],
			'malformed http url' => ['http://'],
		];
	}

	/** @return array<string, array{0: ?string}> */
	public static function missingPasswordProvider(): array {
		return [
			'missing password field' => [null],
			'empty password' => [''],
			'blank password' => ['   '],
			'placeholder without persisted password' => [Admin::PASSWORD_PLACEHOLDER],
		];
	}

	/** @return array<string, array{0: string}> */
	public static function invalidOidProvider(): array {
		return [
			'contains letters' => ['1.2.abc'],
			'contains plus sign' => ['1.2.+3'],
			'contains spaces' => ['1.2. 3'],
			'double dot' => ['1..2'],
			'leading dot' => ['.1.2.3'],
			'trailing dot' => ['1.2.3.'],
		];
	}

	/** @return array<string, array{0: string}> */
	public static function validOidProvider(): array {
		return [
			'single arc' => ['1'],
			'two arcs' => ['1.2'],
			'multiple arcs' => ['1.2.840.113549'],
			'long arc values' => ['2.16.840.1.101.3.4.2.1'],
		];
	}
}
