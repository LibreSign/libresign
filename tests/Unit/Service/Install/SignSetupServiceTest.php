<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use bovigo\vfs\vfsStream;
use OC\IntegrityCheck\Helpers\EnvironmentHelper;
use OC\IntegrityCheck\Helpers\FileAccessHelper;
use OCA\Libresign\Service\Install\SignSetupService;
use OCP\App\IAppManager;
use OCP\Files\AppData\IAppDataFactory;
use OCP\IConfig;
use phpseclib\Crypt\RSA;
use phpseclib\File\X509;
use PHPUnit\Framework\MockObject\MockObject;

final class SignSetupServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private EnvironmentHelper&MockObject $environmentHelper;
	private FileAccessHelper $fileAccessHelper;
	private IConfig&MockObject $config;
	private IAppDataFactory&MockObject $appDataFactory;
	private IAppManager&MockObject $appManager;

	public function setUp(): void {
		$this->environmentHelper = $this->createMock(EnvironmentHelper::class);
		$this->fileAccessHelper = new FileAccessHelper();
		$this->config = $this->createMock(IConfig::class);
		$this->appDataFactory = $this->createMock(IAppDataFactory::class);
		$this->appManager = $this->createMock(IAppManager::class);
	}

	/**
	 * @return SignSetupService|MockObject
	 */
	private function getInstance(array $methods = []) {
		return $this->getMockBuilder(SignSetupService::class)
			->setConstructorArgs([
				$this->environmentHelper,
				$this->fileAccessHelper,
				$this->config,
				$this->appDataFactory,
				$this->appManager,
			])
			->onlyMethods($methods)
			->getMock();
	}

	/**
	 * @dataProvider dataGetArchitectures
	 */
	public function testGetArchitectures(array $appInfo, bool $throwException, $expected):void {
		$this->appManager->method('getAppInfo')
			->willReturn($appInfo);
		if ($throwException) {
			$this->expectExceptionMessage('dependencies>architecture not found at info.xml');
		}
		$actual = $this->getInstance()->getArchitectures();
		if ($throwException) {
			return;
		}
		$this->assertEquals($expected, $actual);
	}

	public static function dataGetArchitectures(): array {
		return [
			[[], true, []],
			[['dependencies' => ['architecture' => []]], true, []],
			[['dependencies' => ['architecture' => ['x86_64']]], false, ['x86_64']],
			[['dependencies' => ['architecture' => ['x86_64', 'aarch64']]], false, ['x86_64', 'aarch64']],
		];
	}

	private function writeAppSignature(string $architecture, $resource): SignSetupService {
		$this->config->method('getSystemValue')
			->willReturn(vfsStream::url('home/data'));

		$this->environmentHelper->method('getServerRoot')
			->willReturn('vfs://home');

		$signSetupService = $this->getInstance([
			'getInternalPathOfFolder',
			'getAppInfoDirectory',
		]);
		$signSetupService->expects($this->any())
			->method('getInternalPathOfFolder')
			->willReturn('libresign');
		$signSetupService->expects($this->any())
			->method('getAppInfoDirectory')
			->willReturn('vfs://home/appinfo');

		$this->appManager->method('getAppInfo')
			->willReturn(['dependencies' => ['architecture' => [$architecture]]]);

		$certificate = $signSetupService->getDevelopCert();
		$rsa = new RSA();
		$rsa->loadKey($certificate['privateKeyInstance']);
		$rsa->loadKey($certificate['privateKeyCert']);
		$x509 = new X509();
		$x509->loadX509($certificate['rootCertificate']);
		$x509->setPrivateKey($rsa);

		$structure = [
			'data' => [
				'libresign' => [
					'java' => [
						'fakeFile01' => 'content',
						'fakeFile02' => 'content',
					],
				],
			],
			'resources' => [
				'codesigning' => [
					'root.crt' => $certificate['rootCertificate'],
				],
			],
			'appinfo' => [],
		];
		$root = vfsStream::setup('home', null, $structure);

		$signSetupService->writeAppSignature($architecture, $resource);
		$this->assertFileExists('vfs://home/appinfo/install-' . $architecture . '-' . $resource . '.json');
		$json = file_get_contents('vfs://home/appinfo/install-' . $architecture . '-' . $resource . '.json');
		$signatureContent = json_decode($json, true);
		$this->assertArrayHasKey('hashes', $signatureContent);
		$this->assertCount(2, $signatureContent['hashes']);
		$expected = hash('sha512', $structure['data']['libresign'][$resource]['fakeFile01']);
		$actual = $signatureContent['hashes']['java/fakeFile01'];
		$this->assertEquals($expected, $actual);
		return $signSetupService;
	}

	/**
	 * @dataProvider dataWriteAppSignature
	 */
	public function testWriteAppSignature(string $architecture, $resource): void {
		$signSetupService = $this->writeAppSignature($architecture, $resource);
		$actual = $signSetupService->verify($architecture, $resource);
		$this->assertCount(0, $actual);
	}

	public static function dataWriteAppSignature(): array {
		return [
			['x86_64', 'java'],
			['aarch64', 'java'],
		];
	}

	public function testVerify(): void {
		$architecture = 'x86_64';
		$signSetupService = $this->writeAppSignature($architecture, 'java');
		unlink('vfs://home/data/libresign/java/fakeFile01');
		file_put_contents('vfs://home/data/libresign/java/fakeFile02', 'invalidContent');
		file_put_contents('vfs://home/data/libresign/java/fakeFile03', 'invalidContent');
		$expected = json_encode([
			'FILE_MISSING' => [
				'java/fakeFile01' => [
					'expected' => 'b2d1d285b5199c85f988d03649c37e44fd3dde01e5d69c50fef90651962f48110e9340b60d49a479c4c0b53f5f07d690686dd87d2481937a512e8b85ee7c617f',
					'current' => '',
				],
			],
			'INVALID_HASH' => [
				'java/fakeFile02' => [
					'expected' => 'b2d1d285b5199c85f988d03649c37e44fd3dde01e5d69c50fef90651962f48110e9340b60d49a479c4c0b53f5f07d690686dd87d2481937a512e8b85ee7c617f',
					'current' => '827a4e298c978e1eeffebdf09f0fa5a1e1d8b608c8071144f3fffb31f9ed21f6d27f88a63f7409583df7438105f713ff58d55e68e61e01a285125d763045c726',
				],
			],
			'EXTRA_FILE' => [
				'java/fakeFile03' => [
					'expected' => '',
					'current' => '827a4e298c978e1eeffebdf09f0fa5a1e1d8b608c8071144f3fffb31f9ed21f6d27f88a63f7409583df7438105f713ff58d55e68e61e01a285125d763045c726',
				],
			],
		]);
		$actual = $signSetupService->verify($architecture, 'java');
		$actual = json_encode($actual);
		$this->assertJsonStringEqualsJsonString($expected, $actual);
	}
}
