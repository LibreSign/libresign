<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Service\CaIdentifierService;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class CaIdentifierServiceTest extends TestCase {
	private CaIdentifierService $service;
	private MockObject $appConfig;

	protected function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->createMock(IAppConfig::class);
		/** @var IAppConfig $appConfig */
		$appConfig = $this->appConfig;
		$this->service = new CaIdentifierService($appConfig);
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
}
