<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use bovigo\vfs\vfsStream;
use OCA\Libresign\Handler\CertificateEngine\OpenSslHandler;
use OCP\Files\AppData\IAppDataFactory;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\ITempManager;

final class OpenSslHandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IConfig $config;
	private IAppConfig $appConfig;
	private IAppDataFactory $appDataFactory;
	private IDateTimeFormatter $dateTimeFormatter;
	private ITempManager $tempManager;
	private OpenSslHandler $openSslHandler;
	public function setUp(): void {
		$this->config = $this->createMock(IConfig::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->appDataFactory = $this->createMock(IAppDataFactory::class);
		$this->dateTimeFormatter = $this->createMock(IDateTimeFormatter::class);
		$this->dateTimeFormatter
			->method('formatDateTime')
			->willReturn('fake date');
		$this->tempManager = $this->createMock(ITempManager::class);
		$this->tempManager
			->method('getTemporaryFile')
			->willReturn(tempnam(sys_get_temp_dir(), 'temp'));
		$this->openSslHandler = new OpenSslHandler(
			$this->config,
			$this->appConfig,
			$this->appDataFactory,
			$this->dateTimeFormatter,
			$this->tempManager,
		);
		vfsStream::setup('certificate');
		$this->openSslHandler->setConfigPath('vfs://certificate/');
	}

	/**
	 * @dataProvider dataReadCertificate
	 */
	public function testReadCertificate(string $commonName, array $hosts, string $password, array $csrNames): void {
		if (isset($csrNames['C'])) {
			$this->openSslHandler->setCountry($csrNames['C']);
		}
		if (isset($csrNames['ST'])) {
			$this->openSslHandler->setState($csrNames['ST']);
		}
		if (isset($csrNames['O'])) {
			$this->openSslHandler->setOrganization($csrNames['O']);
		}
		if (isset($csrNames['OU'])) {
			$this->openSslHandler->setOrganizationalUnit($csrNames['OU']);
		}
		$this->openSslHandler->generateRootCert($commonName, $csrNames);

		$this->openSslHandler->setHosts($hosts);
		$this->openSslHandler->setPassword($password);
		$certificateContent = $this->openSslHandler->generateCertificate();
		$parsed = $this->openSslHandler->readCertificate($certificateContent, $password);
		$this->assertJsonStringEqualsJsonString(
			json_encode($csrNames),
			json_encode($parsed['subject'])
		);
	}

	public static function dataReadCertificate(): array {
		return [
			[
				'common name',
				['user@domain.tld'],
				'password',
				[
					'C' => 'CT',
					'ST' => 'Some-State',
					'O' => 'Organization Name',
				],
			],
			[
				'common name',
				['user@domain.tld'],
				'password',
				[
					'C' => 'CT',
					'ST' => 'Some-State',
					'O' => 'Organization Name',
					'OU' => 'Organization Unit',
				],
			],
		];
	}
}
