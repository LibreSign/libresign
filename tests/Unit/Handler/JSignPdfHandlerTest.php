<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use Jeidison\JSignPDF\JSignPDF;
use Jeidison\JSignPDF\Sign\JSignParam;
use OCA\Libresign\Handler\JSignPdfHandler;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignatureTextService;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use OCP\ITempManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class JSignPdfHandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IAppConfig $appConfig;
	private LoggerInterface&MockObject $loggerInterface;
	private ITempManager&MockObject $tempManager;
	private SignatureTextService&MockObject $signatureTextService;
	private SignatureBackgroundService&MockObject $signatureBackgroundService;
	public function setUp(): void {
		$this->appConfig = $this->getMockAppConfig();
		$this->loggerInterface = $this->createMock(LoggerInterface::class);
		$this->tempManager = $this->createMock(ITempManager::class);
		$this->signatureTextService = $this->createMock(SignatureTextService::class);
		$this->signatureBackgroundService = $this->createMock(SignatureBackgroundService::class);
	}

	private function getClass(): JSignPdfHandler {
		return new JSignPdfHandler(
			$this->appConfig,
			$this->loggerInterface,
			$this->signatureTextService,
			$this->tempManager,
			$this->signatureBackgroundService,
		);
	}

	public function testSignExistingFileSuccess():void {
		$inputFile = $this->createMock(\OC\Files\Node\File::class);
		$mock = $this->createMock(JSignPDF::class);
		$mock->method('sign')->willReturn('content');

		$this->appConfig->setValueString('libresign', 'java_path', __FILE__);
		$this->appConfig->setValueString('libresign', 'jsignpdf_temp_path', sys_get_temp_dir());
		$this->appConfig->setValueString('libresign', 'jsignpdf_jar_path', __FILE__);

		$jSignPdfHandler = $this->getClass();
		$jSignPdfHandler->setJSignPdf($mock);
		$jSignPdfHandler->setInputFile($inputFile);
		$jSignPdfHandler->setCertificate('');
		$jSignPdfHandler->setPassword('password');
		$actual = $jSignPdfHandler->getSignedContent();
		$this->assertEquals('content', $actual);
	}


	#[DataProvider('providerGetJSignParam')]
	public function testGetJSignParam(string $temp_path, string $java_path, string $jar_path, bool $throwException): void {
		$expected = new JSignParam();

		$this->appConfig->setValueString('libresign', 'java_path', $java_path);
		$expected->setJavaPath($java_path);

		$this->appConfig->setValueString('libresign', 'jsignpdf_temp_path', $temp_path);
		$expected->setTempPath($temp_path);

		$this->appConfig->setValueString('libresign', 'jsignpdf_jar_path', $jar_path);
		$expected->setjSignPdfJarPath($jar_path);

		$jSignPdfHandler = $this->getClass();
		if ($throwException) {
			$this->expectException(\Exception::class);
			$jSignParam = $jSignPdfHandler->getJSignParam();
		} else {
			$jSignParam = $jSignPdfHandler->getJSignParam();
			$this->assertEquals($expected->getPdf(), $jSignParam->getPdf());
			$this->assertEquals($expected->getJavaPath(), $jSignParam->getJavaPath());
			$this->assertEquals($expected->getTempPath(), $jSignParam->getTempPath());
			$this->assertEquals($expected->getjSignPdfJarPath(), $jSignParam->getjSignPdfJarPath());
			$this->assertEquals('-a -kst PKCS12', $jSignParam->getJSignParameters());
		}
	}

	public static function providerGetJSignParam(): array {
		return [
			['',                 '',       __FILE__, true],
			['invalid',          '',       __FILE__, true],
			[sys_get_temp_dir(), '',       __FILE__, false],
			[sys_get_temp_dir(), 'b',      __FILE__, true],
			[sys_get_temp_dir(), __FILE__, __FILE__, false],
			[sys_get_temp_dir(), 'b',      __FILE__, true],
			[sys_get_temp_dir(), __FILE__, __FILE__, false],
			[sys_get_temp_dir(), __FILE__, '',       true],
		];
	}
}
