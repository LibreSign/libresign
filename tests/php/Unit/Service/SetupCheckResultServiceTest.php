<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\IEngineHandler;
use OCA\Libresign\Service\SetupCheckResultService;
use OCA\Libresign\SetupCheck\ImagickSetupCheck;
use OCA\Libresign\SetupCheck\JavaSetupCheck;
use OCA\Libresign\SetupCheck\JSignPdfSetupCheck;
use OCA\Libresign\SetupCheck\PDFtkSetupCheck;
use OCA\Libresign\SetupCheck\PopplerSetupCheck;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SetupCheckResultServiceTest extends TestCase {
	private CertificateEngineFactory&MockObject $certificateEngineFactory;
	private JavaSetupCheck&MockObject $javaSetupCheck;
	private JSignPdfSetupCheck&MockObject $jSignPdfSetupCheck;
	private PDFtkSetupCheck&MockObject $pdftkSetupCheck;
	private PopplerSetupCheck&MockObject $popplerSetupCheck;
	private ImagickSetupCheck&MockObject $imagickSetupCheck;
	private SetupCheckResultService $service;

	public function setUp(): void {
		$this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$engine = $this->createMock(IEngineHandler::class);
		$engine->method('configureCheck')->willReturn([]);
		$this->certificateEngineFactory->method('getEngine')->willReturn($engine);

		$this->javaSetupCheck = $this->createMock(JavaSetupCheck::class);
		$this->jSignPdfSetupCheck = $this->createMock(JSignPdfSetupCheck::class);
		$this->pdftkSetupCheck = $this->createMock(PDFtkSetupCheck::class);
		$this->popplerSetupCheck = $this->createMock(PopplerSetupCheck::class);
		$this->imagickSetupCheck = $this->createMock(ImagickSetupCheck::class);

		$this->configureCheck($this->javaSetupCheck, 'system', 'success', 'Java OK', 'https://example.com');
		$this->configureCheck($this->jSignPdfSetupCheck, 'system', 'success', 'JSignPdf OK', 'https://example.com/jsignpdf');
		$this->configureCheck($this->pdftkSetupCheck, 'system', 'success', 'PDFtk OK', 'https://example.com/pdftk');
		$this->configureCheck($this->popplerSetupCheck, 'system', 'success', 'Poppler OK', 'https://example.com/poppler');
		$this->configureCheck($this->imagickSetupCheck, 'system', 'success', 'Imagick OK', 'https://example.com/imagick');

		$this->buildService();
	}

	private function buildService(): void {
		$this->service = new SetupCheckResultService(
			$this->certificateEngineFactory,
			$this->javaSetupCheck,
			$this->jSignPdfSetupCheck,
			$this->pdftkSetupCheck,
			$this->popplerSetupCheck,
			$this->imagickSetupCheck,
		);
	}

	/**
	 * @param ISetupCheck&MockObject $check
	 */
	private function configureCheck($check, string $category, string $severity, string $description, ?string $link): void {
		$result = $this->createMock(SetupResult::class);
		$result->method('getSeverity')->willReturn($severity);
		$result->method('getDescription')->willReturn($description);
		$result->method('getLinkToDoc')->willReturn($link);
		$check->method('getCategory')->willReturn($category);
		$check->method('run')->willReturn($result);
	}

	public function testGetFormattedChecksReturnsOnlyLibresignChecks(): void {
		$result = $this->service->getFormattedChecks();

		$this->assertCount(5, $result);
		$resources = array_map(static fn ($check) => $check->getResource(), $result);
		$this->assertSame(['java', 'jsignpdf', 'pdftk', 'poppler', 'imagick'], $resources);
		foreach ($result as $check) {
			$this->assertNotSame('', $check->getCategory());
		}
	}

	public function testJsonSerializeOmitsCategory(): void {
		$this->javaSetupCheck = $this->createMock(JavaSetupCheck::class);
		$this->configureCheck($this->javaSetupCheck, 'system', 'warning', 'Java Warning', null);
		$this->buildService();

		$result = $this->service->getFormattedChecks();

		$this->assertSame('system', $result[0]->getCategory());

		$serialized = $result[0]->jsonSerialize();
		$this->assertSame(['status', 'resource', 'message', 'tip'], array_keys($serialized));
		$this->assertSame('info', $serialized['status']);
	}

	#[DataProvider('providerSeverityMapping')]
	public function testSeverityMapping(string $severity, string $expectedStatus): void {
		$this->javaSetupCheck = $this->createMock(JavaSetupCheck::class);
		$this->configureCheck($this->javaSetupCheck, 'system', $severity, 'Message', null);
		$this->buildService();

		$result = $this->service->getFormattedChecks();

		$this->assertEquals($expectedStatus, $result[0]->getStatus());
	}

	public static function providerSeverityMapping(): array {
		return [
			'error' => ['error', 'error'],
			'warning' => ['warning', 'info'],
			'success' => ['success', 'success'],
			'unknown' => ['unknown', 'info'],
		];
	}
}
