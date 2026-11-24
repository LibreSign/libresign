<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Handler\SignEngine;

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\DataObjects\VisibleElementAssoc;
use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\IEngineHandler;
use OCA\Libresign\Handler\SignEngine\PhpNativeHandler;
use OCA\Libresign\Service\DocMdp\ConfigService as DocMdpConfigService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\Tsa\TsaPolicy;
use OCA\Libresign\Service\SandboxedTemplateRenderer;
use OCA\Libresign\Service\Signature\PdfSignatureValidationService;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignatureStampPreview\SignatureStampAppearanceBuilder;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Tests\Mock\CertificateChainFixture;
use OCA\Libresign\Vendor\Symfony\Component\Process\ExecutableFinder;
use OCA\Libresign\Vendor\Symfony\Component\Process\Process;
use OCP\Files\File;
use OCP\IL10N;
use OCP\ITempManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;

final class PhpNativeHandlerVisibleStampTest extends \OCA\Libresign\Tests\Unit\TestCase {
	#[DataProvider('providerVisibleStampText')]
	public function testNativeSigningPreservesVisibleStampText(string $renderMode, string $name, string $template, string $expected): void {
		$executable = (new ExecutableFinder())->find('pdftotext');
		if ($executable === null) {
			$this->markTestSkipped('Poppler is required to read the signature appearance.');
		}

		$appConfig = $this->getMockAppConfigWithReset();
		$signatureTextService = $this->createMock(SignatureTextService::class);
		$signatureBackgroundService = $this->createMock(SignatureBackgroundService::class);
		$certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$policyService = $this->createMock(PolicyService::class);
		$policyService->method('resolve')->with(TsaPolicy::KEY)->willReturn(
			(new ResolvedPolicy())->setPolicyKey(TsaPolicy::KEY)->setEffectiveValue(''),
		);

		$signatureTextService->method('getRenderMode')->willReturn($renderMode);
		$signatureTextService->method('getTemplateFontSize')->willReturn(10.0);
		$signatureTextService->method('getSignatureFontSize')->willReturn(20.0);
		$signatureTextService->method('parse')->willReturnCallback(
			static fn (string $ignored, array $context): array => [
				'parsed' => SandboxedTemplateRenderer::render($template, $context),
				'templateFontSize' => 10.0,
			],
		);
		$signatureBackgroundService->method('isEnabled')->willReturn(false);
		$certificateEngine = $this->createMock(IEngineHandler::class);
		$certificateEngine->method('readCertificate')->willReturn(['subject' => ['CN' => $name]]);
		$certificateEngineFactory->method('getEngine')->willReturn($certificateEngine);
		$handler = new PhpNativeHandler(
			$appConfig,
			$this->createMock(DocMdpConfigService::class),
			$signatureTextService,
			new SignatureStampAppearanceBuilder($signatureTextService),
			$signatureBackgroundService,
			$policyService,
			$certificateEngineFactory,
		);

		$config = tmpfile();
		$this->assertNotFalse($config);
		try {
			$tempManager = $this->createMock(ITempManager::class);
			$tempManager->method('getTemporaryFile')->willReturn(stream_get_meta_data($config)['uri']);
			$chain = (new CertificateChainFixture($tempManager))->create('Synthetic Unicode Stamp Signer');
			$this->assertTrue(openssl_pkcs12_export(
				$chain['leafCertificate'],
				$pfx,
				$chain['leafPrivateKey'],
				'stamp-test',
				['extra_certs' => [$chain['rootCertificate']]],
			));

			$pdf = file_get_contents(dirname(__DIR__, 3) . '/fixtures/pdfs/small_valid.pdf');
			$this->assertIsString($pdf);
			$input = $this->createMock(File::class);
			$input->method('getContent')->willReturn($pdf);
			$element = FileElement::fromParams(['page' => 1, 'llx' => 20, 'lly' => 700, 'urx' => 380, 'ury' => 800]);
			$image = $renderMode === SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION
				? dirname(__DIR__, 5) . '/img/preview_signature.png'
				: '';
			$handler->setInputFile($input)
				->setCertificate($pfx)
				->setPassword('stamp-test')
				->setSignatureParams([
					'PageDimensions' => [['w' => 595.276, 'h' => 841.89]],
					'SignerCommonName' => $name,
					'IssuerCommonName' => 'Phenix Tech',
				])
				->setVisibleElements([new VisibleElementAssoc($element, $image)]);

			$signed = $handler->getSignedContent();
			$process = new Process([$executable, '-raw', '-nopgbrk', '-enc', 'UTF-8', '-', '-'], input: $signed);
			$process->mustRun();
			$this->assertSame($expected, trim($process->getOutput()));

			$validator = new PdfSignatureValidationService(
				$appConfig,
				$this->createMock(IL10N::class),
				$this->createMock(LoggerInterface::class),
			);
			$validator->addTrustedRoot($chain['rootCertificate']);
			$signatures = $validator->validateFromString($signed);
			$this->assertCount(1, $signatures);
			$this->assertTrue($signatures[0]['signatureValidation']['isValid']);
		} finally {
			fclose($config);
		}
	}

	public static function providerVisibleStampText(): array {
		return [
			'ASCII description control' => [
				SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY,
				'Test User',
				'Signed by {{SignerCommonName}}',
				'Signed by Test User',
			],
			'French issue 8155' => [
				SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY,
				'Renée Dupont',
				"Signé avec LibreSign\n{{SignerCommonName}}\nÉmetteur : {{IssuerCommonName}}",
				"Signé avec LibreSign\nRenée Dupont\nÉmetteur : Phenix Tech",
			],
			'Unicode alongside drawn signature' => [
				SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION,
				'Иван Иванов',
				'{{SignerCommonName}}',
				'Иван Иванов',
			],
			'decomposed name and Unicode description' => [
				SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION,
				"Rene\u{0301}e",
				'Émetteur : {{IssuerCommonName}}',
				"Renée\nÉmetteur : Phenix Tech",
			],
		];
	}
}
