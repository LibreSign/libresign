<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Handler\SignEngine;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\DataObjects\VisibleElementAssoc;
use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Handler\SignEngine\PhpNativeHandler;
use OCA\Libresign\Service\Policy\Provider\SignatureText\SignatureTextPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureText\SignatureTextPolicyValue;
use OCA\Libresign\Service\Signature\PdfSignatureValidationService;
use OCA\Libresign\Tests\Integration\TestCase;
use OCA\Libresign\Tests\Mock\CertificateChainFixture;
use OCA\Libresign\Vendor\Symfony\Component\Process\ExecutableFinder;
use OCA\Libresign\Vendor\Symfony\Component\Process\Process;
use OCP\Files\File;
use OCP\ITempManager;
use OCP\L10N\IFactory;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;

final class PhpNativeVisibleStampTest extends TestCase {
	#[\Override]
	public function setUp(): void {
		// Use the same app-config instance as the native signing regression suite.
		// Replacing it per dataset leaves already-resolved policy services bound to stale config.
		$this->getMockAppConfigWithReset();
	}

	#[DataProvider('providerVisibleStampText')]
	public function testNativeSigningPreservesVisibleStampText(string $mode, string $name, string $template, string $expected): void {
		$pdftotext = (new ExecutableFinder())->find('pdftotext');
		if ($pdftotext === null) {
			$this->markTestSkipped('Poppler is required to read the signature appearance.');
		}

		$appConfig = $this->getMockAppConfig();
		$key = SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY;
		$hadValue = $appConfig->hasKey(Application::APP_ID, $key);
		$previous = $appConfig->getValueString(Application::APP_ID, $key);
		$appConfig->setValueString(Application::APP_ID, $key, SignatureTextPolicyValue::encode(array_replace(
			SignatureTextPolicyValue::DEFAULTS,
			[
				'template' => $template,
				'template_font_size' => 10.0,
				'render_mode' => $mode,
				'background_type' => 'deleted',
			],
		)));
		try {
			$chain = (new CertificateChainFixture(Server::get(ITempManager::class)))->create('Synthetic Unicode Stamp Signer');
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
			$image = $mode === 'default' ? dirname(__DIR__, 5) . '/img/preview_signature.png' : '';
			$signed = Server::get(PhpNativeHandler::class)
				->setInputFile($input)
				->setCertificate($pfx)
				->setPassword('stamp-test')
				->setSignatureParams([
					'PageDimensions' => [['w' => 595.276, 'h' => 841.89]],
					'SignerCommonName' => $name,
					'IssuerCommonName' => 'Phenix Tech',
				])
				->setVisibleElements([new VisibleElementAssoc($element, $image)])
				->getSignedContent();

			$process = new Process([$pdftotext, '-raw', '-nopgbrk', '-enc', 'UTF-8', '-', '-'], input: $signed);
			$process->mustRun();
			$this->assertSame($expected, trim($process->getOutput()));

			$validator = new PdfSignatureValidationService(
				$appConfig,
				Server::get(IFactory::class)->get(Application::APP_ID),
				Server::get(LoggerInterface::class),
			);
			$validator->addTrustedRoot($chain['rootCertificate']);
			$signatures = $validator->validateFromString($signed);
			$this->assertCount(1, $signatures);
			$this->assertTrue($signatures[0]['signatureValidation']['isValid']);
		} finally {
			if ($hadValue) {
				$appConfig->setValueString(Application::APP_ID, $key, $previous);
			} else {
				$appConfig->deleteKey(Application::APP_ID, $key);
			}
		}
	}

	public static function providerVisibleStampText(): array {
		return [
			'ASCII description control' => ['description_only', 'Test User', 'Signed by {{SignerCommonName}}', 'Signed by Test User'],
			'French issue 8155' => [
				'description_only',
				'Renée Dupont',
				"Signé avec LibreSign\n{{SignerCommonName}}\nÉmetteur : {{IssuerCommonName}}",
				"Signé avec LibreSign\nRenée Dupont\nÉmetteur : Phenix Tech",
			],
			'Unicode alongside drawn signature' => ['default', 'Иван Иванов', '{{SignerCommonName}}', 'Иван Иванов'],
			'decomposed name and description' => ['text', "Rene\u{0301}e", 'Émetteur : {{IssuerCommonName}}', "Renée\nÉmetteur : Phenix Tech"],
		];
	}
}
