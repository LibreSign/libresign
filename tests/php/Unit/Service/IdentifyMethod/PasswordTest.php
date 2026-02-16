<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\IdentifyMethod;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\DocMdpHandler;
use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Service\CaIdentifierService;
use OCA\Libresign\Service\Crl\CrlService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdentifyMethod\IdentifyService;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\Password;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\IUserSession;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class PasswordTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IdentifyService&MockObject $identifyService;
	private Pkcs12Handler&MockObject $pkcs12Handler;
	private IUserSession&MockObject $userSession;
	private IAppConfig $appConfig;
	private FolderService&MockObject $folderService;
	private CertificateEngineFactory&MockObject $certificateEngineFactory;
	private IL10N $l10n;
	private FooterHandler&MockObject $footerHandler;
	private ITempManager $tempManager;
	private LoggerInterface&MockObject $logger;
	private CaIdentifierService&MockObject $caIdentifierService;
	private DocMdpHandler&MockObject $docMdpHandler;
	private CrlService&MockObject $crlService;

	public function setUp(): void {
		$this->identifyService = $this->createMock(IdentifyService::class);
		$this->appConfig = $this->getMockAppConfigWithReset();
		$this->folderService = $this->createMock(FolderService::class);
		$this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$this->l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);
		$this->footerHandler = $this->createMock(FooterHandler::class);
		$this->tempManager = \OCP\Server::get(ITempManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->caIdentifierService = $this->createMock(CaIdentifierService::class);
		$this->docMdpHandler = $this->createMock(DocMdpHandler::class);
		$this->crlService = $this->createMock(CrlService::class);
		$this->pkcs12Handler = $this->getPkcs12Instance();
	}

	private function getClass(): Password {
		return new Password(
			$this->identifyService,
			$this->pkcs12Handler,
			$this->userSession,
		);
	}

	/**
	 * @return Pkcs12Handler&MockObject
	 */
	private function getPkcs12Instance(array $methods = []) {
		return $this->getMockBuilder(Pkcs12Handler::class)
			->setConstructorArgs([
				$this->folderService,
				$this->appConfig,
				$this->certificateEngineFactory,
				$this->l10n,
				$this->footerHandler,
				$this->tempManager,
				$this->logger,
				$this->caIdentifierService,
				$this->docMdpHandler,
				$this->crlService,
			])
			->onlyMethods($methods)
			->getMock();
	}

	#[DataProvider('providerValidateToIdentify')]
	public function testValidateToIdentify(string $pfx, bool $shouldThrow): void {
		$this->pkcs12Handler = $this->getPkcs12Instance(['getPfxOfCurrentSigner']);
		$this->pkcs12Handler->method('getPfxOfCurrentSigner')->willReturn($pfx);

		$password = $this->getClass();
		$password->setCodeSentByUser('senha');

		if ($shouldThrow) {
			$this->expectException(LibresignException::class);
			$password->validateToIdentify();
		} else {
			$password->validateToIdentify();
			$this->expectNotToPerformAssertions();
		}
	}

	public static function providerValidateToIdentify(): array {
		return [
			'valid pfx' => ['mock-pfx', false],
			'empty pfx' => ['', true],
		];
	}

	#[DataProvider('providerValidateToSignWithError')]
	public function testValidateToSignWithError(bool $throwsException, string $pfx): void {
		$this->pkcs12Handler = $this->getPkcs12Instance(['getPfxOfCurrentSigner']);
		$this->pkcs12Handler->method('getPfxOfCurrentSigner')->willReturn($pfx);
		if ($throwsException) {
			$this->expectException(LibresignException::class);
		} else {
			$this->expectNotToPerformAssertions();
		}

		$password = $this->getClass();
		$password->setCodeSentByUser('senha');
		$password->validateToSign();
	}

	public static function providerValidateToSignWithError(): array {
		return [
			'Invalid certificate' => [true, ''],
			'throws InvalidPasswordException' => [true, 'mock-pfx'],
		];
	}

	#[DataProvider('providerValidateToSignWithCertificateData')]
	public function testValidateToSignWithCertificateData(array $certificateData, bool $shouldThrow, string $expectedMessage = ''): void {
		$this->pkcs12Handler = $this->getPkcs12Instance(['getPfxOfCurrentSigner', 'setCertificate', 'setPassword', 'readCertificate']);
		$this->pkcs12Handler->method('getPfxOfCurrentSigner')->willReturn('mock-pfx');
		$this->pkcs12Handler->method('setCertificate')->willReturnSelf();
		$this->pkcs12Handler->method('setPassword')->willReturnSelf();
		$this->pkcs12Handler->method('readCertificate')->willReturn($certificateData);

		$this->identifyService->method('getL10n')->willReturn($this->l10n);

		$password = $this->getClass();
		$password->setCodeSentByUser('senha');

		if ($shouldThrow) {
			$this->expectException(LibresignException::class);
			if ($expectedMessage) {
				$this->expectExceptionMessage($expectedMessage);
			}
		}

		$password->validateToSign();

		if (!$shouldThrow) {
			$this->expectNotToPerformAssertions();
		}
	}

	public static function providerValidateToSignWithCertificateData(): array {
		$futureTimestamp = (new \DateTime('+50 years'))->getTimestamp();
		$pastTimestamp = (new \DateTime('-50 years'))->getTimestamp();

		return [
			'valid certificate - no expiration data' => [
				'certificateData' => [],
				'shouldThrow' => false,
			],
			'valid certificate - future expiration' => [
				'certificateData' => [
					'validTo_time_t' => $futureTimestamp,
				],
				'shouldThrow' => false,
			],
			'expired certificate' => [
				'certificateData' => [
					'validTo_time_t' => $pastTimestamp,
				],
				'shouldThrow' => true,
				'expectedMessage' => 'Certificate has expired',
			],
			'invalid certificate - validTo_time_t is string' => [
				'certificateData' => [
					'validTo_time_t' => '1234567890',
				],
				'shouldThrow' => true,
				'expectedMessage' => 'Invalid certificate',
			],
			'invalid certificate - validTo_time_t is null' => [
				'certificateData' => [
					'validTo_time_t' => null,
				],
				'shouldThrow' => true,
				'expectedMessage' => 'Invalid certificate',
			],
			'invalid certificate - validTo_time_t is float' => [
				'certificateData' => [
					'validTo_time_t' => 1234567890.5,
				],
				'shouldThrow' => true,
				'expectedMessage' => 'Invalid certificate',
			],
			'invalid certificate - validTo_time_t is boolean true' => [
				'certificateData' => [
					'validTo_time_t' => true,
				],
				'shouldThrow' => true,
				'expectedMessage' => 'Invalid certificate',
			],
			'invalid certificate - validTo_time_t is boolean false' => [
				'certificateData' => [
					'validTo_time_t' => false,
				],
				'shouldThrow' => true,
				'expectedMessage' => 'Invalid certificate',
			],
			'invalid certificate - validTo_time_t is array' => [
				'certificateData' => [
					'validTo_time_t' => ['timestamp' => 1234567890],
				],
				'shouldThrow' => true,
				'expectedMessage' => 'Invalid certificate',
			],
			'revoked certificate' => [
				'certificateData' => [
					'validTo_time_t' => $futureTimestamp,
					'crl_validation' => 'revoked',
				],
				'shouldThrow' => true,
				'expectedMessage' => 'Certificate has been revoked',
			],
			'valid certificate with crl validation' => [
				'certificateData' => [
					'validTo_time_t' => $futureTimestamp,
					'crl_validation' => 'valid',
				],
				'shouldThrow' => false,
			],
			'invalid certificate - crl validation failed' => [
				'certificateData' => [
					'validTo_time_t' => $futureTimestamp,
					'crl_validation' => 'failed',
				],
				'shouldThrow' => true,
				'expectedMessage' => 'Certificate has been revoked',
			],
			'invalid certificate - crl validation empty string' => [
				'certificateData' => [
					'validTo_time_t' => $futureTimestamp,
					'crl_validation' => '',
				],
				'shouldThrow' => true,
				'expectedMessage' => 'Certificate has been revoked',
			],
			'invalid certificate - crl validation null' => [
				'certificateData' => [
					'validTo_time_t' => $futureTimestamp,
					'crl_validation' => null,
				],
				'shouldThrow' => true,
				'expectedMessage' => 'Certificate has been revoked',
			],
			'revoked and expired certificate' => [
				'certificateData' => [
					'validTo_time_t' => $pastTimestamp,
					'crl_validation' => 'revoked',
				],
				'shouldThrow' => true,
				'expectedMessage' => 'Certificate has been revoked', // revocation is checked first
			],
			'valid certificate - old date but valid (1970s timestamp)' => [
				'certificateData' => [
					'validTo_time_t' => 31536000, // 1971-01-01
				],
				'shouldThrow' => true,
				'expectedMessage' => 'Certificate has expired',
			],
		];
	}
}
