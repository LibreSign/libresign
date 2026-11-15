<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Service\CaIdentifierService;
use OCP\IAppConfig;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class CaIdentifierServiceTest extends TestCase {
	private CaIdentifierService $service;
	private IAppConfig&MockObject $appConfig;
	private ISecureRandom&MockObject $secureRandom;

	protected function setUp(): void {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->secureRandom
			->method('generate')
			->willReturn('abc1234567');
		$this->service = new CaIdentifierService($this->appConfig, $this->secureRandom);
	}

	public function testGenerateCaIdWithOpenSSL(): void {
		$this->appConfig
			->expects($this->once())
			->method('getValueInt')
			->with('libresign', 'ca_generation_counter', 0)
			->willReturn(0);

		$this->appConfig
			->expects($this->once())
			->method('setValueInt')
			->with('libresign', 'ca_generation_counter', 1);

		$result = $this->service->generateCaId('openssl');

		$this->assertMatchesRegularExpression('/^libresign-ca-id:[a-z0-9]{10}_g:\d+_e:o$/', $result);
	}

	public function testGenerateCaIdWithCFSSL(): void {
		$this->appConfig
			->expects($this->once())
			->method('getValueInt')
			->with('libresign', 'ca_generation_counter', 0)
			->willReturn(2);

		$this->appConfig
			->expects($this->once())
			->method('setValueInt')
			->with('libresign', 'ca_generation_counter', 3);

		$result = $this->service->generateCaId('cfssl');

		$this->assertMatchesRegularExpression('/^libresign-ca-id:[a-z0-9]{10}_g:\d+_e:c$/', $result);
	}

	#[DataProvider('providerIsValidCaId')]
	public function testIsValidCaId(string $caId, string $instanceId, bool $expected): void {
		$this->assertEquals($expected, $this->service->isValidCaId($caId, $instanceId));
	}

	public static function providerIsValidCaId(): array {
		return [
			['libresign-ca-id:abc1234567_g:1_e:o', 'abc1234567', true],
			['libresign-ca-id:abc1234567_g:42_e:c', 'abc1234567', true],
			['libresign-ca-id:abc1234567_g:425468_e:c', 'abc1234567', true],
			['libresign-ca-id:abc1234567_g:1_e:x', 'abc1234567', false],
			['invalid-ca-id-format', 'abc1234567', false],
			['libresign-ca-id:wronginstance_g:1_e:o', 'abc1234567', false],
		];
	}

	public function testGeneratePkiDirectoryName(): void {
		$caId = 'libresign-ca-id:abc1234567_g:1_e:o';
		$result = $this->service->generatePkiDirectoryName($caId);

		$this->assertEquals('pki/abc1234567_1_openssl', $result);
	}

	#[DataProvider('providerGetCaIdParsedWithValidLegacyId')]
	public function testGetCaIdParsedWithValidLegacyId(string $caId, array $expected): void {
		$this->mockStoredCaId($caId);

		$this->assertSame($expected, $this->service->getCaIdParsed());
	}

	public static function providerGetCaIdParsedWithValidLegacyId(): array {
		return [
			'openssl first generation' => [
				'libresign-ca-id:abc1234567_g:1_e:o',
				[
					'engineName' => 'openssl',
					'instanceId' => 'abc1234567',
					'generation' => 1,
					'engineType' => 'o',
				],
			],
			'cfssl second generation' => [
				'libresign-ca-id:abc1234567_g:2_e:c',
				[
					'engineName' => 'cfssl',
					'instanceId' => 'abc1234567',
					'generation' => 2,
					'engineType' => 'c',
				],
			],
			'openssl multi digit generation' => [
				'libresign-ca-id:0123456789_g:42_e:o',
				[
					'engineName' => 'openssl',
					'instanceId' => '0123456789',
					'generation' => 42,
					'engineType' => 'o',
				],
			],
			'cfssl large generation' => [
				'libresign-ca-id:zz99yy88xx_g:425468_e:c',
				[
					'engineName' => 'cfssl',
					'instanceId' => 'zz99yy88xx',
					'generation' => 425468,
					'engineType' => 'c',
				],
			],
		];
	}

	#[DataProvider('providerGetCaIdParsedWithInvalidId')]
	public function testGetCaIdParsedWithInvalidId(string $caId): void {
		$this->mockStoredCaId($caId);

		$this->expectException(\InvalidArgumentException::class);
		$this->service->getCaIdParsed();
	}

	public static function providerGetCaIdParsedWithInvalidId(): array {
		return [
			'empty id' => [''],
			'unknown format' => ['invalid-ca-id-format'],
			'wrong prefix' => ['other-ca-id:abc1234567_g:1_e:o'],
			'unsupported engine type' => ['libresign-ca-id:abc1234567_g:1_e:x'],
			'missing engine type' => ['libresign-ca-id:abc1234567_g:1_e:'],
			'missing generation marker' => ['libresign-ca-id:abc1234567_1_e:o'],
			'non numeric generation' => ['libresign-ca-id:abc1234567_g:one_e:o'],
			'empty generation' => ['libresign-ca-id:abc1234567_g:_e:o'],
			'negative generation' => ['libresign-ca-id:abc1234567_g:-1_e:o'],
			'uppercase instance id' => ['libresign-ca-id:ABC1234567_g:1_e:o'],
			'invalid instance id character' => ['libresign-ca-id:abc-234567_g:1_e:o'],
			'empty instance id' => ['libresign-ca-id:_g:1_e:o'],
			'trailing content' => ['libresign-ca-id:abc1234567_g:1_e:o_extra'],
			'leading content' => [' libresign-ca-id:abc1234567_g:1_e:o'],
		];
	}

	#[DataProvider('providerGeneratePkiDirectoryName')]
	public function testGeneratePkiDirectoryNameForLegacyIds(string $caId, string $expected): void {
		$this->assertSame($expected, $this->service->generatePkiDirectoryName($caId));
	}

	public static function providerGeneratePkiDirectoryName(): array {
		return [
			'openssl first generation' => ['libresign-ca-id:abc1234567_g:1_e:o', 'pki/abc1234567_1_openssl'],
			'openssl later generation' => ['libresign-ca-id:abc1234567_g:42_e:o', 'pki/abc1234567_42_openssl'],
			'cfssl first generation' => ['libresign-ca-id:abc1234567_g:1_e:c', 'pki/abc1234567_1_cfssl'],
			'cfssl later generation' => ['libresign-ca-id:abc1234567_g:425468_e:c', 'pki/abc1234567_425468_cfssl'],
		];
	}

	#[DataProvider('providerGeneratePkiDirectoryNameFromParams')]
	public function testGeneratePkiDirectoryNameFromParams(string $instanceId, int $generation, string $engineName, string $expected): void {
		$this->assertSame($expected, $this->service->generatePkiDirectoryNameFromParams($instanceId, $generation, $engineName));
	}

	public static function providerGeneratePkiDirectoryNameFromParams(): array {
		return [
			'openssl' => ['abc1234567', 1, 'openssl', 'pki/abc1234567_1_openssl'],
			'cfssl' => ['abc1234567', 7, 'cfssl', 'pki/abc1234567_7_cfssl'],
		];
	}

	#[DataProvider('providerGeneratePkiDirectoryNameWithInvalidId')]
	public function testGeneratePkiDirectoryNameWithInvalidId(string $caId): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->generatePkiDirectoryName($caId);
	}

	public static function providerGeneratePkiDirectoryNameWithInvalidId(): array {
		return [
			'empty id' => [''],
			'unsupported engine type' => ['libresign-ca-id:abc1234567_g:1_e:x'],
			'unknown format' => ['invalid-ca-id-format'],
		];
	}

	#[DataProvider('providerGenerateCaIdPreservesShape')]
	public function testGenerateCaIdPreservesLegacyShape(string $engineName, int $currentGeneration, string $expected): void {
		$this->appConfig
			->method('getValueInt')
			->with('libresign', 'ca_generation_counter', 0)
			->willReturn($currentGeneration);

		$stored = [];
		$this->appConfig
			->method('setValueString')
			->willReturnCallback(function (string $app, string $key, string $value) use (&$stored): bool {
				$stored[$app . '.' . $key] = $value;
				return true;
			});

		$this->assertSame($expected, $this->service->generateCaId($engineName));
		$this->assertSame('abc1234567', $stored['libresign.instance_id']);
		$this->assertSame($expected, $stored['libresign.ca_id']);
	}

	public static function providerGenerateCaIdPreservesShape(): array {
		return [
			'openssl' => ['openssl', 0, 'libresign-ca-id:abc1234567_g:1_e:o'],
			'cfssl' => ['cfssl', 2, 'libresign-ca-id:abc1234567_g:3_e:c'],
		];
	}

	public function testGenerateCaIdWithUnsupportedEngine(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->generateCaId('unsupported');
	}

	private function mockStoredCaId(string $caId): void {
		$this->appConfig
			->method('getValueString')
			->with('libresign', 'ca_id')
			->willReturn($caId);
	}
}
