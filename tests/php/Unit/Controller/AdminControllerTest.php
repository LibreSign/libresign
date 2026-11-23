<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\AdminController;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Service\ActiveSigningsService;
use OCA\Libresign\Service\Certificate\ValidateService;
use OCA\Libresign\Service\CertificatePolicyService;
use OCA\Libresign\Service\GeoIp\GeoIpConfigService;
use OCA\Libresign\Service\GeoIp\GeoIpDatabaseStatusService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\Service\SetupCheckResultService;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCP\AppFramework\Http;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\IAppConfig;
use OCP\IEventSource;
use OCP\IEventSourceFactory;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdminControllerTest extends TestCase {
	private AdminController $controller;
	private ActiveSigningsService&MockObject $activeSigningsService;
	private SignatureBackgroundService&MockObject $signatureBackgroundService;
	private InstallService&MockObject $installService;
	private ValidateService&MockObject $validateService;
	private IRequest&MockObject $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$appConfig = $this->createMock(IAppConfig::class);
		$this->installService = $this->createMock(InstallService::class);
		$certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$eventSourceFactory = $this->createMock(IEventSourceFactory::class);
		$l10n = $this->createMock(IL10N::class);
		$session = $this->createMock(ISession::class);
		$this->signatureBackgroundService = $this->createMock(SignatureBackgroundService::class);
		$certificatePolicyService = $this->createMock(CertificatePolicyService::class);
		$this->validateService = $this->createMock(ValidateService::class);
		$identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->activeSigningsService = $this->createMock(ActiveSigningsService::class);
		$setupCheckResultService = $this->createMock(SetupCheckResultService::class);
		$geoIpConfigService = $this->createMock(GeoIpConfigService::class);
		$geoIpDatabaseStatusService = $this->createMock(GeoIpDatabaseStatusService::class);
		$eventSource = $this->createMock(IEventSource::class);

		$eventSourceFactory
			->method('create')
			->willReturn($eventSource);

		$this->controller = new AdminController(
			$this->request,
			$appConfig,
			$this->installService,
			$certificateEngineFactory,
			$eventSourceFactory,
			$l10n,
			$session,
			$this->signatureBackgroundService,
			$certificatePolicyService,
			$this->validateService,
			$identifyMethodService,
			$this->activeSigningsService,
			$setupCheckResultService,
			$geoIpConfigService,
			$geoIpDatabaseStatusService,
		);
	}

	public function testGetActiveSigningsReturnsServicePayload(): void {
		$this->activeSigningsService
			->expects($this->once())
			->method('getActiveSignings')
			->willReturn([[
				'id' => 7,
				'uuid' => 'uuid-7',
				'name' => 'Contract.pdf',
				'signerEmail' => 'signer@example.com',
				'signerDisplayName' => 'Active signer',
				'updatedAt' => 1751891696,
			]]);

		$response = $this->controller->getActiveSignings();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([
			'data' => [[
				'id' => 7,
				'uuid' => 'uuid-7',
				'name' => 'Contract.pdf',
				'signerEmail' => 'signer@example.com',
				'signerDisplayName' => 'Active signer',
				'updatedAt' => 1751891696,
			]],
		], $response->getData());
	}

	public function testGetActiveSigningsReturnsErrorResponseWhenServiceFails(): void {
		$this->activeSigningsService
			->expects($this->once())
			->method('getActiveSignings')
			->willThrowException(new \RuntimeException('boom'));

		$response = $this->controller->getActiveSignings();

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertSame(['error' => 'boom'], $response->getData());
	}

	public static function certificateGenerators(): array {
		return [
			'openssl' => ['generateCertificateOpenSsl'],
			'cfssl' => ['generateCertificateCfssl'],
		];
	}

	#[DataProvider('certificateGenerators')]
	public function testCertificateGenerationReportsInvalidInputAsUnprocessable(string $method): void {
		$this->validateService
			->method('validate')
			->willThrowException(new \InvalidArgumentException("Parameter 'CN' is required!"));

		$response = $this->controller->$method(['commonName' => '']);

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
		$this->assertSame(['message' => "Parameter 'CN' is required!"], $response->getData());
	}

	#[DataProvider('certificateGenerators')]
	public function testCertificateGenerationReportsLibresignExceptionAsUnprocessable(string $method): void {
		$this->installService
			->method('generate')
			->willThrowException(new LibresignException('Invalid configuration'));

		$response = $this->controller->$method(['commonName' => 'Common Name']);

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	#[DataProvider('certificateGenerators')]
	public function testCertificateGenerationReportsUnexpectedFailureAsServerError(string $method): void {
		$this->installService
			->method('generate')
			->willThrowException(new \RuntimeException('openssl is unavailable'));

		$response = $this->controller->$method(['commonName' => 'Common Name']);

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertSame(['message' => 'openssl is unavailable'], $response->getData());
	}

	public function testSignatureBackgroundGetSendsTheDocumentedHeaders(): void {
		$this->signatureBackgroundService
			->expects($this->once())
			->method('getImage')
			->willReturn($this->createMock(ISimpleFile::class));

		$response = $this->controller->signatureBackgroundGet();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$headers = $response->getHeaders();
		$this->assertSame('image/png', $headers['Content-Type']);
		$this->assertSame('attachment; filename="background.png"', $headers['Content-Disposition']);
	}
}
