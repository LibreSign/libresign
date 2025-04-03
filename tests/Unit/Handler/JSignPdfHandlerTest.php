<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use Jeidison\JSignPDF\JSignPDF;
use Jeidison\JSignPDF\Sign\JSignParam;
use OCA\Libresign\DataObjects\VisibleElementAssoc;
use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Handler\JSignPdfHandler;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignatureTextService;
use OCP\IAppConfig;
use OCP\ITempManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class JSignPdfHandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IAppConfig $appConfig;
	private LoggerInterface&MockObject $loggerInterface;
	private ITempManager $tempManager;
	private SignatureTextService&MockObject $signatureTextService;
	private SignatureBackgroundService&MockObject $signatureBackgroundService;
	public function setUp(): void {
		$this->appConfig = $this->getMockAppConfig();
		$this->loggerInterface = $this->createMock(LoggerInterface::class);
		$this->tempManager = \OCP\Server::get(ITempManager::class);
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

	#[DataProvider('providerSignAffectedParams')]
	public function testSignAffectedParams(
		array $visibleElements,
		string $template,
		string $signatureBackgroundType,
		string $renderMode,
		string $pdfContent,
		?string $hashAlgorithm,
		string $params,
	):void {
		$inputFile = $this->createMock(\OC\Files\Node\File::class);
		$inputFile->method('getContent')
			->willReturn($pdfContent);
		$mock = $this->createMock(JSignPDF::class);
		$mock->method('sign')->willReturn('content');

		$this->signatureBackgroundService->method('getSignatureBackgroundType')->willReturn(
			$signatureBackgroundType
		);

		$this->signatureBackgroundService->method('getImagePath')->willReturn(
			realpath(__DIR__ . '/../../../img/app-dark.png')
		);

		$this->signatureTextService->method('parse')
			->willReturn([
				'parsed' => trim($template, '"'),
				'fontSize' => 10,
			]);

		$this->signatureTextService->method('getRenderMode')->willReturn($renderMode);

		$this->appConfig->setValueString('libresign', 'signature_hash_algorithm', $hashAlgorithm);

		$this->appConfig->setValueString('libresign', 'java_path', __FILE__);
		$this->appConfig->setValueString('libresign', 'jsignpdf_temp_path', sys_get_temp_dir());
		$this->appConfig->setValueString('libresign', 'jsignpdf_jar_path', __FILE__);

		$jSignPdfHandler = $this->getClass();
		$jSignPdfHandler->setVisibleElements($visibleElements);
		$jSignPdfHandler->setJSignPdf($mock);
		$jSignPdfHandler->setInputFile($inputFile);
		$jSignPdfHandler->setCertificate('');
		$jSignPdfHandler->setPassword('password');
		$actual = $jSignPdfHandler->getSignedContent();
		$this->assertEquals('content', $actual);
		$jSignParam = $jSignPdfHandler->getJSignParam();
		$this->assertEquals('password', $jSignParam->getPassword());
		$paramsAsOptions = $jSignParam->getJSignParameters();
		$paramsAsOptions = preg_replace('/bg-path \/\S+_merged.png/', 'bg-path merged.png', $paramsAsOptions);
		$paramsAsOptions = preg_replace('/bg-path \/\S+app-dark.png/', 'bg-path background.png', $paramsAsOptions);
		$paramsAsOptions = preg_replace('/img-path \/\S+app-dark.png/', 'img-path signature.png', $paramsAsOptions);
		$this->assertEquals($params, $paramsAsOptions);
	}

	public static function providerSignAffectedParams(): array {
		return [
			[[], '', '', '', '%PDF-1',   '',       '-a -kst PKCS12 --hash-algorithm SHA1'],
			[[], '', '', '', '%PDF-1.5', 'SHA1',   '-a -kst PKCS12 --hash-algorithm SHA1'],
			[[], '', '', '', '%PDF-1.5', 'SHA256', '-a -kst PKCS12 --hash-algorithm SHA1'],
			[[], '', '', '', '%PDF-1.6', 'SHA1',   '-a -kst PKCS12 --hash-algorithm SHA256'],
			[[], '', '', '', '%PDF-1.6', 'SHA256', '-a -kst PKCS12 --hash-algorithm SHA256'],
			[[], '', '', '', '%PDF-1.6', 'SHA512', '-a -kst PKCS12 --hash-algorithm SHA256'],
			[[], '', '', '', '%PDF-1.7', 'SHA512', '-a -kst PKCS12 --hash-algorithm SHA512'],
			[
				[self::getElement([
					'page' => 1,
					'llx' => 0,
					'lly' => 0,
					'urx' => 0,
					'ury' => 0,
				])],
				'',
				'default',
				'DESCRIPTION_ONLY',
				'%PDF-1.6',
				'',
				'-a -kst PKCS12 --l2-text "" --font-size 10 -V -llx 0 -lly 0 -urx 0 -ury 0 --bg-path merged.png --hash-algorithm SHA256'
			],
			[
				[self::getElement([
					'page' => 2,
					'llx' => 10,
					'lly' => 20,
					'urx' => 30,
					'ury' => 40,
				])],
				'',
				'default',
				'DESCRIPTION_ONLY',
				'%PDF-1.6',
				'',
				'-a -kst PKCS12 --l2-text "" --font-size 10 -V -pg 2 -llx 10 -lly 20 -urx 30 -ury 40 --bg-path merged.png --hash-algorithm SHA256'
			],
			[
				[self::getElement([
					'page' => 2,
					'llx' => 10,
					'lly' => 20,
					'urx' => 30,
					'ury' => 40,
				])],
				'aaaaa',
				'default',
				'DESCRIPTION_ONLY',
				'%PDF-1.6',
				'',
				'-a -kst PKCS12 --l2-text "aaaaa" --font-size 10 -V -pg 2 -llx 10 -lly 20 -urx 30 -ury 40 --bg-path merged.png --hash-algorithm SHA256'
			],
			[
				[self::getElement([
					'page' => 2,
					'llx' => 10,
					'lly' => 20,
					'urx' => 30,
					'ury' => 40,
				])],
				'aaaaa',
				'deleted',
				'DESCRIPTION_ONLY',
				'%PDF-1.6',
				'',
				'-a -kst PKCS12 --l2-text "aaaaa" --font-size 10 -V -pg 2 -llx 10 -lly 20 -urx 30 -ury 40 --bg-path background.png --hash-algorithm SHA256'
			],
			[
				[self::getElement([
					'page' => 2,
					'llx' => 10,
					'lly' => 20,
					'urx' => 30,
					'ury' => 40,
				])],
				'aaaaa',
				'default',
				'GRAPHIC_AND_DESCRIPTION',
				'%PDF-1.6',
				'',
				'-a -kst PKCS12 --l2-text "aaaaa" --font-size 10 -V -pg 2 -llx 10 -lly 20 -urx 30 -ury 40 --render-mode GRAPHIC_AND_DESCRIPTION --bg-path background.png --img-path signature.png --hash-algorithm SHA256'
			],
		];
	}

	private static function getElement(array $attributes = []): VisibleElementAssoc {
		$element = new FileElement();
		foreach ($attributes as $attribute => $value) {
			$method = 'set' . ucfirst($attribute);
			$element->$method($value);
		}
		return new VisibleElementAssoc(
			$element,
			realpath(__DIR__ . '/../../../img/app-dark.png'),
		);
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

	#[DataProvider('providerGetSignatureText')]
	public function testGetSignatureText(string $renderMode, string $template, string $expected): void {
		$this->signatureTextService->method('parse')
			->willReturn(['parsed' => trim($template, '"')]);
		$this->signatureTextService->method('getRenderMode')
			->willReturn($renderMode);
		$jSignPdfHandler = $this->getClass();
		$actual = $jSignPdfHandler->getSignatureText();
		$this->assertEquals($expected, $actual);
	}

	public static function providerGetSignatureText(): array {
		return [
			['FAKE_RENDER_MODE', '',     '""'],
			['FAKE_RENDER_MODE', 'a',    '"a"'],
			['FAKE_RENDER_MODE', "a\na", "\"a\na\""],
			['FAKE_RENDER_MODE', 'a"a',  '"a\"a"'],
			['FAKE_RENDER_MODE', 'a$a',  '"a\$a"'],
			['GRAPHIC_ONLY',     '',     '""'],
			['GRAPHIC_ONLY',     'a',    '""'],
			['GRAPHIC_ONLY',     "a\na", '""'],
			['GRAPHIC_ONLY',     'a"a',  '""'],
			['GRAPHIC_ONLY',     'a$a',  '""'],
		];
	}
}
