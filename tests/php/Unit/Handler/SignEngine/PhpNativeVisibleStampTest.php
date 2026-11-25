<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Handler\SignEngine;

use OCA\Libresign\Service\SignatureStampPreview\SignatureStampAppearanceBuilder;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Vendor\Symfony\Component\Process\ExecutableFinder;
use OCA\Libresign\Vendor\Symfony\Component\Process\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SignerPHP\Application\DTO\CertificateCredentialsDto;
use SignerPHP\Application\DTO\PdfContentDto;
use SignerPHP\Application\DTO\SignatureAppearanceDto;
use SignerPHP\Application\DTO\SignatureAppearanceXObjectDto;
use SignerPHP\Application\DTO\SigningOptionsDto;
use SignerPHP\Application\DTO\SignPdfRequestDto;
use SignerPHP\Application\Service\PdfSigningService;
use SignerPHP\Infrastructure\Legacy\OpenSslCertificateValidator;
use SignerPHP\Infrastructure\Native\NativePdfSigningEngine;

final class PhpNativeVisibleStampTest extends TestCase {
	#[DataProvider('providerVisibleStamp')]
	public function testSignedPdfPreservesVisibleStampText(string $mode, string $name, string $text, string $expected): void {
		$finder = new ExecutableFinder();
		$pdftotext = $finder->find('pdftotext');
		$pdfsig = $finder->find('pdfsig');
		if ($pdftotext === null || $pdfsig === null) {
			$this->markTestSkipped('Poppler is required to verify the rendered and signed PDF.');
		}

		$appearance = $this->buildXObject($mode, $name, $text, 360);
		$privateKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
		$this->assertNotFalse($privateKey);
		$csr = openssl_csr_new(['commonName' => 'Synthetic stamp signer'], $privateKey, ['digest_alg' => 'sha256']);
		$this->assertNotFalse($csr);
		$certificate = openssl_csr_sign($csr, null, $privateKey, 1, ['digest_alg' => 'sha256']);
		$this->assertNotFalse($certificate);
		$this->assertTrue(openssl_pkcs12_export($certificate, $pfx, $privateKey, 'stamp-test'));

		$pdf = file_get_contents(dirname(__DIR__, 3) . '/fixtures/pdfs/small_valid.pdf');
		$this->assertIsString($pdf);
		$signed = (new PdfSigningService(new OpenSslCertificateValidator(), new NativePdfSigningEngine()))->sign(
			SignPdfRequestDto::fromRequired(
				new PdfContentDto($pdf),
				CertificateCredentialsDto::fromContent($pfx, 'stamp-test'),
				new SigningOptionsDto(
					appearance: new SignatureAppearanceDto(
						backgroundImagePath: null,
						rect: [20.0, 20.0, 380.0, 120.0],
						page: 0,
						xObject: $appearance,
					),
					useDefaultAppearance: false,
				),
			),
		);

		$process = new Process([$pdftotext, '-raw', '-nopgbrk', '-enc', 'UTF-8', '-', '-'], input: $signed);
		$process->mustRun();
		$this->assertSame($expected, trim($process->getOutput()));

		$path = tempnam(sys_get_temp_dir(), 'libresign-stamp-');
		$this->assertNotFalse($path);
		try {
			$this->assertSame(strlen($signed), file_put_contents($path, $signed));
			$process = new Process([$pdfsig, '-nocert', $path], env: ['LC_ALL' => 'C']);
			$process->mustRun();
			$this->assertStringContainsString('Signature Validation: Signature is Valid.', $process->getOutput());
		} finally {
			unlink($path);
		}
	}

	public static function providerVisibleStamp(): array {
		return [
			'ASCII control' => [SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY, '', 'Signed by Test User', 'Signed by Test User'],
			'French issue 8155' => [
				SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY,
				'',
				"Signé avec LibreSign\nRenée Dupont\nÉmetteur : Phenix Tech",
				"Signé avec LibreSign\nRenée Dupont\nÉmetteur : Phenix Tech",
			],
			'Portuguese and PDF delimiters' => [
				SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY,
				'',
				'João (Ação) \\ €',
				'João (Ação) \\ €',
			],
			'decomposed accents' => [
				SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY,
				'',
				"Rene\u{0301}e",
				'Renée',
			],
			'visible signer name' => [
				SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION,
				'Renée',
				'Émetteur',
				"Renée\nÉmetteur",
			],
		];
	}

	public function testWrapDoesNotSplitUtf8Characters(): void {
		$appearance = $this->buildXObject(SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY, '', 'ééééé', 16);
		$this->assertSame('/WinAnsiEncoding', $appearance->resources['Font']['F1']['Encoding'] ?? null);
		$this->assertStringContainsString("(\xe9\xe9\xe9) Tj", $appearance->stream);
		$this->assertStringContainsString("(\xe9\xe9) Tj", $appearance->stream);
	}

	#[DataProvider('providerUnsupportedText')]
	public function testUnsupportedTextIsNotSilentlyCorrupted(string $text): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->buildXObject(SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY, '', $text, 360);
	}

	public static function providerUnsupportedText(): array {
		return [
			'outside WinAnsi repertoire' => ['Иван'],
			'supplementary character' => ['😀'],
			'invalid UTF-8' => ["\xff"],
		];
	}

	private function buildXObject(string $mode, string $name, string $text, int $width): SignatureAppearanceXObjectDto {
		$signatureTextService = $this->createMock(SignatureTextService::class);
		$signatureTextService->method('parse')->willReturn(['parsed' => $text, 'templateFontSize' => 10.0]);
		$signatureTextService->method('getSignatureFontSize')->willReturn(20.0);
		return (new SignatureStampAppearanceBuilder($signatureTextService))->buildXObject(
			$width,
			100,
			$mode,
			['SignerCommonName' => $name],
		);
	}
}
