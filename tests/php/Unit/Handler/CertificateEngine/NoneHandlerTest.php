<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Handler\CertificateEngine;

use OCA\Libresign\Enum\CrlValidationStatus;
use OCA\Libresign\Handler\CertificateEngine\NoneHandler;
use OCA\Libresign\Service\CaIdentifierService;
use OCA\Libresign\Service\CertificatePolicyService;
use OCA\Libresign\Service\Crl\CrlRevocationChecker;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\Files\AppData\IAppDataFactory;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\ITempManager;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class NoneHandlerTest extends TestCase {
	private IConfig $config;
	private IAppConfig $appConfig;
	private IAppDataFactory $appDataFactory;
	private IDateTimeFormatter $dateTimeFormatter;
	private ITempManager $tempManager;
	private CertificatePolicyService $certificatePolicyService;
	private IURLGenerator $urlGenerator;
	private CaIdentifierService $caIdentifierService;
	private LoggerInterface $logger;
	private PolicyService $policyService;
	private CrlRevocationChecker&MockObject $crlRevocationChecker;

	#[\Override]
	public function setUp(): void {
		$this->config = \OCP\Server::get(IConfig::class);
		$this->appConfig = $this->getMockAppConfigWithReset();
		$this->appDataFactory = \OCP\Server::get(IAppDataFactory::class);
		$this->dateTimeFormatter = \OCP\Server::get(IDateTimeFormatter::class);
		$this->tempManager = \OCP\Server::get(ITempManager::class);
		$mockCertificatePolicy = $this->createMock(CertificatePolicyService::class);
		$mockCertificatePolicy->method('getOid')->willReturn('');
		$mockCertificatePolicy->method('getCps')->willReturn('');
		$this->certificatePolicyService = $mockCertificatePolicy;
		$this->urlGenerator = \OCP\Server::get(IURLGenerator::class);
		$this->caIdentifierService = \OCP\Server::get(CaIdentifierService::class);
		$this->logger = \OCP\Server::get(LoggerInterface::class);
		$this->policyService = $this->createMock(PolicyService::class);
		$this->crlRevocationChecker = $this->createMock(CrlRevocationChecker::class);
		$this->crlRevocationChecker->method('validate')->willReturn(['status' => CrlValidationStatus::VALID]);
	}

	private function getInstance(): NoneHandler {
		return new NoneHandler(
			$this->config,
			$this->appConfig,
			$this->appDataFactory,
			$this->dateTimeFormatter,
			$this->tempManager,
			$this->certificatePolicyService,
			$this->urlGenerator,
			$this->caIdentifierService,
			$this->policyService,
			$this->logger,
			$this->crlRevocationChecker,
		);
	}

	public function testConfigureCheckReturnsNoneEngineSuccessWithoutOpenSslChecks(): void {
		$handler = $this->getInstance();

		$checks = $handler->configureCheck();

		$this->assertCount(1, $checks);
		$this->assertSame('success', $checks[0]->getStatus());
		$this->assertSame('none-configure', $checks[0]->getResource());
		$this->assertSame('None handler is active (no certificates required).', $checks[0]->getMessage());
		$this->assertSame('', $checks[0]->getTip());
	}
}
