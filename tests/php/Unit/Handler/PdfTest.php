<?php

declare(strict_types=1);

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\PdfTk\Pdf;
use OCA\Libresign\Helper\JavaHelper;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

final class PdfTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private JavaHelper&MockObject $javaHelper;
	private IAppConfig $appConfig;
	private IL10N $l10n;

	public function setUp(): void {
		parent::setUp();
		$this->javaHelper = $this->createMock(JavaHelper::class);
		$this->appConfig = $this->getMockAppConfig();
		$this->l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);
	}

	private function getInstance(array $methods = []): Pdf|MockObject {
		if ($methods) {
			return $this->getMockBuilder(Pdf::class)
				->setConstructorArgs([
					$this->javaHelper,
					$this->appConfig,
					$this->l10n,
				])
				->onlyMethods($methods)
				->getMock();
		}
		return new Pdf(
			$this->javaHelper,
			$this->appConfig,
			$this->l10n,
		);
	}

	public function testApplyStampReturnsBufferWhenSuccess(): void {
		$pdf = $this->getInstance(['configureCommand', 'multiStamp']);

		$mock = $this->createMock(\OCA\Libresign\Vendor\mikehaertl\pdftk\Pdf::class);
		$mock->method('toString')->willReturn('%PDF-1.4 fake');

		$pdf->method('multiStamp')->willReturn($mock);

		$result = $pdf->applyStamp('/tmp/test.pdf', '/tmp/stamp.pdf');

		$this->assertSame('%PDF-1.4 fake', $result);
	}

	public function testApplyStampThrowsWhenHaventJavaPath(): void {
		$this->javaHelper->method('getJavaPath')->willReturn('');
		$this->appConfig->setValueString(Application::APP_ID, 'pdftk_path', '/fake/path');
		$pdf = $this->getInstance();

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('/Java/');

		$pdf->applyStamp('/tmp/input.pdf', '/tmp/stamp.pdf');
	}

	public function testApplyStampThrowsWhenHaventPdftk(): void {
		$this->javaHelper->method('getJavaPath')->willReturn('/fake/path');
		$this->appConfig->setValueString(Application::APP_ID, 'pdftk_path', '');
		$pdf = $this->getInstance();

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('/PDFtk/');

		$pdf->applyStamp('/tmp/input.pdf', '/tmp/stamp.pdf');
	}

	public function testInvalidDependenciesPath(): void {
		$this->javaHelper->method('getJavaPath')->willReturn('/fake/path');
		$this->appConfig->setValueString(Application::APP_ID, 'pdftk_path', '/fake/path');
		$pdf = $this->getInstance();

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessageMatches('/set up/');

		$pdf->applyStamp('/tmp/input.pdf', '/tmp/stamp.pdf');
	}
}
