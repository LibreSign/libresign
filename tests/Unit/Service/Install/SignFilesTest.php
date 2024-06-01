<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use bovigo\vfs\vfsStream;
use OC\IntegrityCheck\Helpers\FileAccessHelper;
use OCA\Libresign\Service\Install\SignFiles;
use OCP\App\IAppManager;
use OCP\Files\AppData\IAppDataFactory;
use OCP\IConfig;
use phpseclib\Crypt\RSA;
use phpseclib\File\X509;
use PHPUnit\Framework\MockObject\MockObject;

final class SignFilesTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private FileAccessHelper&MockObject $fileAccessHelper;
	private IConfig&MockObject $config;
	private IAppDataFactory&MockObject $appDataFactory;
	private IAppManager&MockObject $appManager;

	public function setUp(): void {
		$this->fileAccessHelper = $this->createMock(FileAccessHelper::class);
		$this->config = $this->createMock(IConfig::class);
		$this->appDataFactory = $this->createMock(IAppDataFactory::class);
		$this->appManager = $this->createMock(IAppManager::class);
	}

	/**
	 * @return SignFiles|MockObject
	 */
	private function getInstance(array $methods = []) {
		return $this->getMockBuilder(SignFiles::class)
			->setConstructorArgs([
				$this->fileAccessHelper,
				$this->config,
				$this->appDataFactory,
				$this->appManager,
			])
			->onlyMethods($methods)
			->getMock();
	}

	private function getNewCert(): array {
		$privateKey = openssl_pkey_new([
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		]);

		$csrNames = ['commonName' => 'Jhon Doe'];

		$csr = openssl_csr_new($csrNames, $privateKey, ['digest_alg' => 'sha256']);
		$x509 = openssl_csr_sign($csr, null, $privateKey, $days = 365, ['digest_alg' => 'sha256']);

		openssl_x509_export($x509, $rootCertificate);
		openssl_pkey_export($privateKey, $rootPrivateKey);

		$privateKey = openssl_pkey_new([
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		]);
		return [
			'privateKey' => $privateKey,
			'certificate' => $rootCertificate,
		];
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

	/**
	 * @dataProvider dataWriteAppSignature
	 */
	public function testWriteAppSignature(string $architecture): void {
		$this->appManager->method('getAppInfo')
			->willReturn(['dependencies' => ['architecture' => [$architecture]]]);

		$certificate = $this->getNewCert('123456');
		$rsa = new RSA();
		$rsa->loadKey($certificate['privateKey']);
		$x509 = new X509();
		$x509->loadX509($certificate['certificate']);
		$x509->setPrivateKey($rsa);


		vfsStream::setup('home');

		$this->config->method('getSystemValue')
			->willReturn(vfsStream::url('home/data'));

		$signFiles = $this->getInstance(['getInternalPathOfFolder']);
		$signFiles->expects($this->any())
			->method('getInternalPathOfFolder')
			->willReturn('libresign');
		mkdir('vfs://home/data/libresign', 0755, true);
		$signFiles->writeAppSignature($x509, $rsa, $architecture);
	}

	public static function dataWriteAppSignature(): array {
		return [
			['x86_64'],
		];
	}
}
