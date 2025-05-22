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
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Install\SignSetupService;
use OCP\App\IAppManager;
use OCP\Files\AppData\IAppDataFactory;
use OCP\IAppConfig;
use OCP\IConfig;
use phpseclib\Crypt\RSA;
use phpseclib\File\X509;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class SignSetupServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private EnvironmentHelper&MockObject $environmentHelper;
	private FileAccessHelper $fileAccessHelper;
	private IConfig&MockObject $config;
	private IAppConfig $appConfig;
	private IAppManager&MockObject $appManager;
	private IAppDataFactory $appDataFactory;

	public function setUp(): void {
		$this->environmentHelper = $this->createMock(EnvironmentHelper::class);
		$this->fileAccessHelper = new FileAccessHelper();
		$this->appManager = $this->createMock(IAppManager::class);
		$this->config = $this->createMock(IConfig::class);
		$this->appConfig = $this->getMockAppConfig();
		$this->appDataFactory = \OCP\Server::get(IAppDataFactory::class);
	}

	/**
	 * @return SignSetupService|MockObject
	 */
	private function getInstance(array $methods = []) {
		$this->config
			->method('getSystemValue')
			->willReturnCallback(fn ($key, $default): string => match ($key) {
				'instanceid' => '1',
			});
		return $this->getMockBuilder(SignSetupService::class)
			->setConstructorArgs([
				$this->environmentHelper,
				$this->fileAccessHelper,
				$this->config,
				$this->appConfig,
				$this->appManager,
				$this->appDataFactory,
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
		$this->environmentHelper->method('getServerRoot')
			->willReturn('vfs://home');

		$this->appConfig->setValueString(Application::APP_ID, 'java_path', 'vfs://home/data/appdata_1/libresign/' . $architecture . '/linux/java/jdk-21.0.2+13-jre/bin/java');
		$signSetupService = $this->getInstance([
			'getAppInfoDirectory',
		]);
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
				'appdata_1' => [
					'libresign' => [
						$architecture => [
							'linux' => [
								'java' => [
									'jdk-21.0.2+13-jre' => [
										'fakeFile01' => 'content',
										'fakeFile02' => 'content',
									],
								],
							],
						],
					],
				]
			],
			'resources' => [
				'codesigning' => [
					'root.crt' => $certificate['rootCertificate'],
				],
			],
			'appinfo' => [],
		];
		vfsStream::setup('home', null, $structure);

		$signSetupService
			->setArchitecture($architecture)
			->setResource($resource)
			->setDistro('linux')
			->writeAppSignature();
		$this->assertFileExists('vfs://home/appinfo/install-' . $architecture . '-linux-' . $resource . '.json');
		$json = file_get_contents('vfs://home/appinfo/install-' . $architecture . '-linux-' . $resource . '.json');
		$signatureContent = json_decode($json, true);
		$this->assertArrayHasKey('hashes', $signatureContent);
		$this->assertCount(2, $signatureContent['hashes']);
		$expected = hash('sha512', $structure['data']['appdata_1']['libresign'][$architecture]['linux'][$resource]['jdk-21.0.2+13-jre']['fakeFile01']);
		$this->assertArrayHasKey('fakeFile01', $signatureContent['hashes']);
		$actual = $signatureContent['hashes']['fakeFile01'];
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
		unlink('vfs://home/data/appdata_1/libresign/' . $architecture . '/linux/java/jdk-21.0.2+13-jre/fakeFile01');
		file_put_contents('vfs://home/data/appdata_1/libresign/' . $architecture . '/linux/java/jdk-21.0.2+13-jre/fakeFile02', 'invalidContent');
		file_put_contents('vfs://home/data/appdata_1/libresign/' . $architecture . '/linux/java/jdk-21.0.2+13-jre/fakeFile03', 'invalidContent');
		$expected = json_encode([
			'FILE_MISSING' => [
				'fakeFile01' => [
					'expected' => 'b2d1d285b5199c85f988d03649c37e44fd3dde01e5d69c50fef90651962f48110e9340b60d49a479c4c0b53f5f07d690686dd87d2481937a512e8b85ee7c617f',
					'current' => '',
				],
			],
			'INVALID_HASH' => [
				'fakeFile02' => [
					'expected' => 'b2d1d285b5199c85f988d03649c37e44fd3dde01e5d69c50fef90651962f48110e9340b60d49a479c4c0b53f5f07d690686dd87d2481937a512e8b85ee7c617f',
					'current' => '827a4e298c978e1eeffebdf09f0fa5a1e1d8b608c8071144f3fffb31f9ed21f6d27f88a63f7409583df7438105f713ff58d55e68e61e01a285125d763045c726',
				],
			],
			'EXTRA_FILE' => [
				'fakeFile03' => [
					'expected' => '',
					'current' => '827a4e298c978e1eeffebdf09f0fa5a1e1d8b608c8071144f3fffb31f9ed21f6d27f88a63f7409583df7438105f713ff58d55e68e61e01a285125d763045c726',
				],
			],
		]);
		$actual = $signSetupService->verify($architecture, 'java');
		$actual = json_encode($actual);
		$this->assertJsonStringEqualsJsonString($expected, $actual);
	}

	#[DataProvider('dataGetInstallPath')]
	public function testGetInstallPath(string $architecture, string $resource, string $distro, string $expected): void {
		$this->appConfig->setValueString(Application::APP_ID, 'java_path', 'vfs://home/data/appdata_1/libresign/x86_64/linux/java/jdk-21.0.2+13-jre/bin/java');
		$this->appConfig->setValueString(Application::APP_ID, 'jsignpdf_jar_path', 'vfs://home/data/appdata_1/libresign/x86_64/jsignpdf/jsignpdf-2.2.2/JSignPdf.jar');
		$this->appConfig->setValueString(Application::APP_ID, 'pdftk_path', 'vfs://home/data/appdata_1/libresign/x86_64/pdftk/pdftk.jar');
		$this->appConfig->setValueString(Application::APP_ID, 'cfssl_bin', 'vfs://home/data/appdata_1/libresign/x86_64/cfssl/cfssl');
		$actual = $this->getInstance()
			->setArchitecture($architecture)
			->setDistro($distro)
			->setResource($resource)
			->getInstallPath();
		$this->assertEquals(
			$expected,
			$actual
		);
	}

	public static function dataGetInstallPath(): array {
		return [
			['x86_64', 'java', 'linux', 'vfs://home/data/appdata_1/libresign/x86_64/linux/java/jdk-21.0.2+13-jre'],
			['x86_64', 'java', 'alpine-linux', 'vfs://home/data/appdata_1/libresign/x86_64/alpine-linux/java/jdk-21.0.2+13-jre'],
			['x86_64', 'pdftk', 'linux', 'vfs://home/data/appdata_1/libresign/x86_64/pdftk'],
			['x86_64', 'pdftk', 'alpine-linux', 'vfs://home/data/appdata_1/libresign/x86_64/pdftk'],
			['x86_64', 'jsignpdf', 'linux', 'vfs://home/data/appdata_1/libresign/x86_64/jsignpdf'],
			['x86_64', 'jsignpdf', 'alpine-linux', 'vfs://home/data/appdata_1/libresign/x86_64/jsignpdf'],
			['x86_64', 'cfssl', 'linux', 'vfs://home/data/appdata_1/libresign/x86_64/cfssl'],
			['x86_64', 'cfssl', 'alpine-linux', 'vfs://home/data/appdata_1/libresign/x86_64/cfssl'],
			['aarch64', 'java', 'linux', 'vfs://home/data/appdata_1/libresign/aarch64/linux/java/jdk-21.0.2+13-jre'],
			['aarch64', 'java', 'alpine-linux', 'vfs://home/data/appdata_1/libresign/aarch64/alpine-linux/java/jdk-21.0.2+13-jre'],
			['aarch64', 'pdftk', 'linux', 'vfs://home/data/appdata_1/libresign/aarch64/pdftk'],
			['aarch64', 'pdftk', 'alpine-linux', 'vfs://home/data/appdata_1/libresign/aarch64/pdftk'],
			['aarch64', 'jsignpdf', 'linux', 'vfs://home/data/appdata_1/libresign/aarch64/jsignpdf'],
			['aarch64', 'jsignpdf', 'alpine-linux', 'vfs://home/data/appdata_1/libresign/aarch64/jsignpdf'],
			['aarch64', 'cfssl', 'linux', 'vfs://home/data/appdata_1/libresign/aarch64/cfssl'],
			['aarch64', 'cfssl', 'alpine-linux', 'vfs://home/data/appdata_1/libresign/aarch64/cfssl'],
		];
	}
}
