<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Handler;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File as LibreSignFile;
use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\IEngineHandler;
use OCA\Libresign\Handler\DocMdpHandler;
use OCA\Libresign\Handler\SignEngine\PhpNativeHandler;
use OCA\Libresign\Service\DocMdp\ConfigService;
use OCA\Libresign\Service\Signature\PdfSignatureValidationService;
use OCA\Libresign\Tests\Integration\TestCase;
use OCA\Libresign\Vendor\LibreSign\PdfSignatureValidator\Parser\PdfSignatureInventoryReader;
use OCP\Files\File;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;

/**
 * A PDF certified with DocMDP P=1 by the native engine stays valid when a DSS
 * (long-term validation data) is appended as an incremental update, while a
 * content change in the same kind of update is still a violation.
 */
final class DocMdpHandlerValidationDataTest extends TestCase {
	/** @var string[] */
	private array $cleanupPaths = [];
	private string $caCertificate = '';

	#[\Override]
	public function tearDown(): void {
		foreach ($this->cleanupPaths as $path) {
			if (is_file($path)) {
				unlink($path);
			}
		}
		parent::tearDown();
	}

	public function testDssAddedAfterP1CertificationIsAllowed(): void {
		$signed = $this->signCertified(DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED);
		$withDss = $this->appendIncrementalUpdate($signed, $this->dssObjects($signed));

		$result = $this->extractDocMdpData($withDss);

		$this->assertSame(DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED->value, $result['docmdp']['level']);
		$this->assertTrue($result['modifications']['modified']);
		$this->assertTrue($result['modification_validation']['valid'], $result['modification_validation']['message']);
		$this->assertSame(LibreSignFile::MODIFICATION_ALLOWED, $result['modification_validation']['status']);
		$this->assertSignatureStillValid($withDss);
	}

	public function testContentChangeNextToDssAfterP1CertificationIsRejected(): void {
		$signed = $this->signCertified(DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED);
		$this->assertSame(1, preg_match('/\/Contents\s+(\d+)\s+0\s+R/', $signed, $contentRef));
		$changedText = 'Changed after certification!!';
		$objects = $this->dssObjects($signed);
		$objects[(int)$contentRef[1]] = '<< /Length ' . strlen($changedText) . " >>\nstream\n{$changedText}\nendstream";
		$tampered = $this->appendIncrementalUpdate($signed, $objects);

		$result = $this->extractDocMdpData($tampered);

		$this->assertFalse($result['modification_validation']['valid']);
		$this->assertSame(LibreSignFile::MODIFICATION_VIOLATION, $result['modification_validation']['status']);
	}

	public static function forgeryProvider(): array {
		return [
			'trailer /Root replaced by a new catalog and page tree' => ['rootSwap'],
			'comment between generation and obj keyword' => ['commentObj'],
			'redefinition swallowed by an unterminated stream' => ['swallow'],
			'visible signature field with a non-timestamp value' => ['visibleField'],
			'duplicate /Pages key in the catalog' => ['duplicateKey'],
			'object stream behind a name escape' => ['objStmEscape'],
		];
	}

	/**
	 * Forged updates that look like validation data to a lenient reader must
	 * stay DocMDP violations.
	 */
	#[DataProvider('forgeryProvider')]
	public function testForgedUpdateAfterP1CertificationIsRejected(string $forgery): void {
		$signed = $this->signCertified(DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED);
		$this->assertGreaterThan(0, preg_match_all('/\/Size\s+(\d+)/', $signed, $size));
		$n = (int)end($size[1]) + 10;
		$this->assertSame(1, preg_match('/\/Contents\s+(\d+)\s+0\s+R/', $signed, $contentRef));
		$contentNum = (int)$contentRef[1];
		$rootNum = $this->rootNumber($signed);
		$certificationObjectNumber = null;
		if ($forgery === 'rootSwap') {
			$inventory = (new PdfSignatureInventoryReader())->readFromString($signed);
			$this->assertTrue($inventory->isConclusive);
			$certifications = array_values(array_filter(
				$inventory->activeSignatures,
				static fn ($signature): bool => $signature->isCertifying,
			));
			$this->assertCount(1, $certifications);
			$certificationObjectNumber = $certifications[0]->objectNumber;
			$this->assertNotNull($certificationObjectNumber);
		}
		$text = 'BT /F1 24 Tf 72 720 Td (Forged) Tj ET';
		$forged = '<< /Length ' . strlen($text) . " >>\nstream\n{$text}\nendstream";
		$newPageTree = [
			$n + 1 => '<< /Type /Pages /Kids [' . ($n + 2) . ' 0 R] /Count 1 >>',
			$n + 2 => '<< /Type /Page /Parent ' . ($n + 1) . ' 0 R /MediaBox [0 0 612 792] /Contents ' . ($n + 3) . ' 0 R >>',
			$n + 3 => $forged,
		];

		$pdf = match ($forgery) {
			'rootSwap' => (string)preg_replace(
				'/\/Root\s+\d+\s+0\s+R(?=[^\/]*\/Prev[^%]*%%EOF\n$)/',
				"/Root {$n} 0 R",
				$this->appendIncrementalUpdate($signed, [$n => '<< /Type /Catalog /Pages ' . ($n + 1) . ' 0 R /Perms << /DocMDP ' . $certificationObjectNumber . ' 0 R >> >>'] + $newPageTree),
			),
			'commentObj' => $this->appendIncrementalUpdate($signed, [$n => '<< /Type /DSS >>', $contentNum => $forged], [$contentNum => "{$contentNum} 0 %x\nobj"]),
			'swallow' => $this->appendSwallowedRedefinition($signed, $n, $contentNum, $forged),
			'visibleField' => $this->appendIncrementalUpdate($signed, $this->visibleFieldObjects($signed, $n)),
			'duplicateKey' => $this->appendIncrementalUpdate($signed, [
				$rootNum => substr_replace($this->latestObject($signed, $rootNum), '/Pages ' . ($n + 1) . ' 0 R>>', (int)strrpos($this->latestObject($signed, $rootNum), '>>'), 2),
			] + $newPageTree),
			'objStmEscape' => $this->appendIncrementalUpdate($signed, [$n => "<< /Type /Obj#53tm /N 0 /First 0 /Length 0 >>\nstream\n\nendstream"]),
		};

		$result = $this->extractDocMdpData($pdf);

		$this->assertFalse($result['modification_validation']['valid'], $forgery);
		$this->assertSame(LibreSignFile::MODIFICATION_VIOLATION, $result['modification_validation']['status']);
	}

	/**
	 * A visible full-page signature field whose value is an approval signature
	 * without /Type, appended to the page and the AcroForm.
	 *
	 * @return array<int, string>
	 */
	private function visibleFieldObjects(string $signed, int $n): array {
		$appearance = '0 0 0 rg BT /F1 24 Tf 72 720 Td (Forged overlay) Tj ET';
		$pageNum = 3;
		$page = $this->latestObject($signed, $pageNum);
		$catalog = $this->latestObject($signed, $this->rootNumber($signed));
		$this->assertSame(1, preg_match('/\/AcroForm\s+(\d+)\s+0\s+R/', $catalog, $acroFormRef));
		$acroFormNum = (int)$acroFormRef[1];

		return [
			$n => "<< /FT /Sig /Type /Annot /Subtype /Widget /T (TS1) /Rect [0 0 612 792] /F 4 /P {$pageNum} 0 R /AP << /N " . ($n + 1) . ' 0 R >> /V ' . ($n + 2) . ' 0 R >>',
			$n + 1 => '<< /Type /XObject /Subtype /Form /BBox [0 0 612 792] /Length ' . strlen($appearance) . " >>\nstream\n{$appearance}\nendstream",
			$n + 2 => '<< /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /ByteRange [0 0 0 0] /Contents <00> >>',
			$pageNum => (string)preg_replace('/\/Annots\s*\[([^\]]*)\]/', "/Annots [\$1 {$n} 0 R]", $page),
			$acroFormNum => (string)preg_replace('/\/Fields\s*\[([^\]]*)\]/', "/Fields [\$1 {$n} 0 R]", $this->latestObject($signed, $acroFormNum)),
		];
	}

	/**
	 * An unterminated stream object hides the redefinition from a
	 * regex-based object scan, while the xref points at it.
	 */
	private function appendSwallowedRedefinition(string $signed, int $n, int $contentNum, string $forged): string {
		$this->assertGreaterThan(0, preg_match_all('/startxref\s+(\d+)/', $signed, $startxref));
		$update = "\n{$n} 0 obj\n<< /Length 0 >>\nstream\n";
		$offset = strlen($signed) + strlen($update);
		$update .= "{$contentNum} 0 obj\n{$forged}\nendobj\n";
		$xrefOffset = strlen($signed) + strlen($update);
		$update .= "xref\n{$contentNum} 1\n" . sprintf("%010d 00000 n \n", $offset)
			. "trailer\n<< /Size " . ($n + 1) . ' /Root ' . $this->rootNumber($signed) . ' 0 R /Prev ' . end($startxref[1]) . " >>\n"
			. "startxref\n{$xrefOffset}\n%%EOF\n";
		return $signed . $update;
	}

	private function latestObject(string $pdf, int $number): string {
		$this->assertGreaterThan(0, preg_match_all('/(?<!\d)' . $number . '\s+0\s+obj\s*(.*?)\s*endobj/s', $pdf, $objects));
		return (string)end($objects[1]);
	}

	private function rootNumber(string $pdf): int {
		$this->assertGreaterThan(0, preg_match_all('/\/Root\s+(\d+)\s+0\s+R/', $pdf, $root));
		return (int)end($root[1]);
	}

	private function extractDocMdpData(string $pdf): array {
		$resource = $this->resource($pdf);
		$result = \OCP\Server::get(DocMdpHandler::class)->extractDocMdpData($resource);
		fclose($resource);
		return $result;
	}

	private function assertSignatureStillValid(string $pdf): void {
		$validationService = new PdfSignatureValidationService(
			$this->getMockAppConfigWithReset(),
			\OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID),
			\OCP\Server::get(LoggerInterface::class),
		);
		$resource = $this->resource($pdf);
		$entries = $validationService->validateFromResource($resource);
		fclose($resource);

		$this->assertCount(1, $entries);
		$this->assertTrue(
			$entries[0]['signatureValidation']['isValid'] ?? false,
			'the certification signature must stay valid: ' . ($entries[0]['signatureValidation']['reason'] ?? 'n/a'),
		);
	}

	/**
	 * DSS dictionary holding the throwaway CA certificate, the latest catalog
	 * with /DSS and /Version added, and the document information dictionary
	 * rewritten the way LTV tools do it (strings re-encoded, new /ModDate and
	 * /Producer).
	 *
	 * @return array<int, string> object number => object body
	 */
	private function dssObjects(string $pdf): array {
		$this->assertGreaterThan(0, preg_match_all('/\/Size\s+(\d+)/', $pdf, $size));
		$certObject = (int)end($size[1]);
		$dssObject = $certObject + 1;

		$this->assertGreaterThan(0, preg_match_all('/\/Root\s+(\d+)\s+0\s+R/', $pdf, $root));
		$catalogObject = (int)end($root[1]);
		$this->assertGreaterThan(0, preg_match_all('/(?<!\d)' . $catalogObject . '\s+0\s+obj\s*(.*?)\s*endobj/s', $pdf, $catalogs));
		$catalog = (string)end($catalogs[1]);
		$catalog = substr_replace($catalog, "/Version /1.7 /DSS {$dssObject} 0 R>>", (int)strrpos($catalog, '>>'), 2);

		$this->assertGreaterThan(0, preg_match_all('/\/Info\s+(\d+)\s+0\s+R/', $pdf, $info));
		$infoObject = (int)end($info[1]);
		$this->assertGreaterThan(0, preg_match_all('/(?<!\d)' . $infoObject . '\s+0\s+obj\s*(.*?)\s*endobj/s', $pdf, $infos));
		$infoDict = (string)preg_replace(
			['/(\/CreationDate\s*\(D):/', '/\/ModDate\s*\([^)]*\)/', '/\/Producer\s*\(([^)]*)\)/'],
			['$1\\\\072', '/ModDate (D:20260101000000Z)', '/Producer ($1; LTV)'],
			(string)end($infos[1]),
		);
		$this->assertStringContainsString('(D\\072', $infoDict);

		$this->assertSame(1, preg_match('/-----BEGIN CERTIFICATE-----(.+)-----END CERTIFICATE-----/s', $this->caCertificate, $pem));
		$der = (string)base64_decode($pem[1]);

		return [
			$catalogObject => $catalog,
			$infoObject => $infoDict,
			$certObject => '<< /Length ' . strlen($der) . " >>\nstream\n{$der}\nendstream",
			$dssObject => "<< /Type /DSS /Certs [{$certObject} 0 R] >>",
		];
	}

	/**
	 * @param array<int, string> $objects object number => object body
	 * @param array<int, string> $headers object number => header replacing "N 0 obj"
	 */
	private function appendIncrementalUpdate(string $pdf, array $objects, array $headers = []): string {
		$this->assertGreaterThan(0, preg_match_all('/startxref\s+(\d+)/', $pdf, $startxref));
		$this->assertGreaterThan(0, preg_match_all('/\/Size\s+(\d+)/', $pdf, $size));
		$this->assertGreaterThan(0, preg_match_all('/\/Root\s+(\d+\s+\d+\s+R)/', $pdf, $root));

		ksort($objects);
		$update = '';
		$offsets = [];
		foreach ($objects as $number => $body) {
			$offsets[$number] = strlen($pdf) + strlen($update);
			$header = $headers[$number] ?? "{$number} 0 obj";
			$update .= "{$header}\n{$body}\nendobj\n";
		}

		$xrefOffset = strlen($pdf) + strlen($update);
		$update .= "xref\n";
		foreach ($offsets as $number => $offset) {
			$update .= "{$number} 1\n" . sprintf("%010d 00000 n \n", $offset);
		}
		$newSize = max((int)end($size[1]), max(array_keys($objects)) + 1);
		$update .= "trailer\n<< /Size {$newSize} /Root " . end($root[1]) . ' /Prev ' . end($startxref[1]) . " >>\n";
		$update .= "startxref\n{$xrefOffset}\n%%EOF\n";

		return $pdf . $update;
	}

	private function signCertified(DocMdpLevel $level): string {
		$docMdpConfig = $this->createMock(ConfigService::class);
		$docMdpConfig->method('isEnabled')->willReturn(true);
		$docMdpConfig->method('getLevel')->willReturn($level);

		$certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$certificateEngineFactory->method('getEngine')->willReturn($this->createMock(IEngineHandler::class));

		$handler = new PhpNativeHandler(
			$this->getMockAppConfigWithReset(),
			$docMdpConfig,
			\OCP\Server::get(\OCA\Libresign\Service\SignatureTextService::class),
			\OCP\Server::get(\OCA\Libresign\Service\SignatureStampPreview\SignatureStampAppearanceBuilder::class),
			\OCP\Server::get(\OCA\Libresign\Service\SignatureBackgroundService::class),
			\OCP\Server::get(\OCA\Libresign\Service\Policy\PolicyService::class),
			$certificateEngineFactory,
		);

		$content = $this->simplePdf();
		$node = $this->createMock(File::class);
		$node->method('getContent')->willReturnCallback(static fn (): string => $content);

		$identity = $this->generatePkcs12();
		$handler->setInputFile($node)
			->setCertificate($identity['pfx'])
			->setPassword($identity['password']);

		return $handler->getSignedContent();
	}

	/**
	 * Throwaway CA and signing certificate, generated per test.
	 *
	 * @return array{pfx: string, password: string}
	 */
	private function generatePkcs12(): array {
		$configPath = $this->tempPath(<<< 'OPENSSL'
			[ req ]
			distinguished_name = req_distinguished_name
			[ req_distinguished_name ]
			[ v3_ca ]
			basicConstraints = critical,CA:TRUE
			keyUsage = keyCertSign, cRLSign
			[ signing_cert ]
			basicConstraints = CA:FALSE
			keyUsage = critical, digitalSignature, nonRepudiation
			OPENSSL);
		$configArgs = ['config' => $configPath, 'digest_alg' => 'sha256'];
		$keyOptions = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];

		$caKey = openssl_pkey_new($keyOptions);
		$this->assertNotFalse($caKey);
		$caCsr = openssl_csr_new(['commonName' => 'DocMDP Test Root'], $caKey, $configArgs);
		$this->assertNotFalse($caCsr);
		$caCert = openssl_csr_sign($caCsr, null, $caKey, 1, $configArgs + ['x509_extensions' => 'v3_ca'], random_int(1, 0x7fffffff));
		$this->assertNotFalse($caCert);
		$this->assertTrue(openssl_x509_export($caCert, $this->caCertificate));

		$leafKey = openssl_pkey_new($keyOptions);
		$this->assertNotFalse($leafKey);
		$leafCsr = openssl_csr_new(['commonName' => 'DocMDP Test Signer'], $leafKey, $configArgs);
		$this->assertNotFalse($leafCsr);
		$leafCert = openssl_csr_sign($leafCsr, $caCert, $caKey, 1, $configArgs + ['x509_extensions' => 'signing_cert'], random_int(1, 0x7fffffff));
		$this->assertNotFalse($leafCert);

		$password = bin2hex(random_bytes(8));
		$this->assertTrue(openssl_pkcs12_export($leafCert, $pfx, $leafKey, $password, ['extra_certs' => [$caCert]]));

		return ['pfx' => $pfx, 'password' => $password];
	}

	private function simplePdf(): string {
		$text = 'BT /F1 12 Tf 72 720 Td (Certified content) Tj ET';
		$objects = [
			1 => '<< /Type /Catalog /Pages 2 0 R >>',
			2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
			3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>',
			4 => '<< /Length ' . strlen($text) . " >>\nstream\n{$text}\nendstream",
		];

		$pdf = "%PDF-1.7\n";
		$offsets = [];
		foreach ($objects as $number => $body) {
			$offsets[$number] = strlen($pdf);
			$pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
		}
		$xrefOffset = strlen($pdf);
		$pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
		foreach ($offsets as $offset) {
			$pdf .= sprintf("%010d 00000 n \n", $offset);
		}
		$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

		return $pdf;
	}

	/**
	 * @return resource
	 */
	private function resource(string $content) {
		$resource = fopen('php://memory', 'r+');
		$this->assertNotFalse($resource);
		fwrite($resource, $content);
		rewind($resource);
		return $resource;
	}

	private function tempPath(string $content): string {
		$path = tempnam(sys_get_temp_dir(), 'libresign-docmdp-');
		$this->assertNotFalse($path);
		$this->cleanupPaths[] = $path;
		file_put_contents($path, $content);
		return $path;
	}
}
