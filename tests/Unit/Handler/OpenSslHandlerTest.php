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
		$this->config = \OCP\Server::get(IConfig::class);
		$this->appConfig = \OCP\Server::get(IAppConfig::class);
		$this->appDataFactory = \OCP\Server::get(IAppDataFactory::class);
		$this->dateTimeFormatter = \OCP\Server::get(IDateTimeFormatter::class);
		$this->tempManager = \OCP\Server::get(ITempManager::class);
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
	public function testReadCertificate(string $commonName, string $signerName, array $hosts, string $password, array $csrNames): void {
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
		$this->openSslHandler->setFriendlyName($signerName);
		$certificateContent = $this->openSslHandler->generateCertificate();
		$parsed = $this->openSslHandler->readCertificate($certificateContent, $password);

		$name = $this->csrArrayToString($csrNames);
		$this->assertEquals($parsed['name'], $name);

		$this->assertJsonStringEqualsJsonString(
			json_encode($csrNames),
			json_encode($parsed['subject'])
		);
	}

	private function csrArrayToString(array $csr): string {
		$return = '';
		foreach ($csr as $key => $value) {
			$return .= "/$key=$value";
		}
		return $return;
	}

	public static function dataReadCertificate(): array {
		return [
			[
				'common name',
				'Signer Name',
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
				'Signer Name',
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
