<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Integration\Handler\SignEngine;

/**
 * SPDX-FileCopyrightText: 2026 rodrigodev
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File as LibreSignFile;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\IEngineHandler;
use OCA\Libresign\Handler\SignEngine\PhpNativeHandler;
use OCA\Libresign\Service\Signature\PdfSignatureValidationService;
use OCA\Libresign\Service\SigningAttemptGuard;
use OCA\Libresign\Service\SignRequest\Error\SignRequestErrorReporter;
use OCA\Libresign\Tests\Integration\TestCase;
use OCP\BackgroundJob\IJobList;
use OCP\Files\File;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\L10N\IFactory as IL10NFactory;
use OCP\Lock\ILockingProvider;
use Psr\Log\LoggerInterface;

/**
 * Regression coverage for the native (synchronous) PKCS#12 signing path
 * against the real signerphp/pdf-signer dependency.
 *
 * These tests sign through LibreSign's actual PhpNativeHandler and confirm
 * the produced signatures cryptographically with LibreSign's real
 * validation service, plus an independent OpenSSL check in test tooling.
 * The synthetic credentials carry proper keyUsage bits
 * (digitalSignature, nonRepudiation), matching real signing certificates;
 * key-usage-less certificates are known to be refused by the CMS signer
 * resolution and are out of scope here.
 */
final class PhpNativeSigningRegressionTest extends TestCase {
	private IAppConfig $appConfig;
	private IL10N $l10n;
	private LoggerInterface $logger;
	private PhpNativeHandler $handler;
	/** @var resource[] */
	private array $cleanupResources = [];
	/** @var string[] */
	private array $cleanupPaths = [];
	/** @var array<string, string> */
	private array $nodeContents = [];
	/** @var list<SignRequest> */
	private array $testSignRequests = [];
	/** @var list<LibreSignFile> */
	private array $testFiles = [];

	#[\Override]
	public function setUp(): void {
		$this->appConfig = $this->getMockAppConfigWithReset();
		$this->l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);
		$this->logger = \OCP\Server::get(LoggerInterface::class);

		$certificateEngine = $this->createMock(IEngineHandler::class);
		$certificateEngine->method('setPolicyUserIdForValidation')->willReturnSelf();
		$certificateEngine
			->method('parseCertificate')
			->willReturnCallback(static function (string $certificate): array {
				$resource = openssl_x509_read($certificate);
				if ($resource === false) {
					return [];
				}
				$parsed = openssl_x509_parse($resource);
				return is_array($parsed) ? $parsed : [];
			});
		$certificateEngine->method('isSetupOk')->willReturn(true);
		$certificateEngine->method('validateRootCertificate')->willReturnCallback(static function (): void {
		});
		$certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$certificateEngineFactory->method('getEngine')->willReturn($certificateEngine);

		$this->handler = new PhpNativeHandler(
			$this->appConfig,
			\OCP\Server::get(\OCA\Libresign\Service\DocMdp\ConfigService::class),
			\OCP\Server::get(\OCA\Libresign\Service\SignatureTextService::class),
			\OCP\Server::get(\OCA\Libresign\Service\SignatureStampPreview\SignatureStampAppearanceBuilder::class),
			\OCP\Server::get(\OCA\Libresign\Service\SignatureBackgroundService::class),
			\OCP\Server::get(\OCA\Libresign\Service\Policy\PolicyService::class),
			$certificateEngineFactory,
		);
	}

	#[\Override]
	public function tearDown(): void {
		// A test may throw during signing. Fixtures must always be removed.
		foreach ($this->testSignRequests as $request) {
			\OCP\Server::get(SignRequestMapper::class)->delete($request);
		}
		foreach ($this->testFiles as $file) {
			\OCP\Server::get(FileMapper::class)->delete($file);
		}
		foreach ($this->cleanupResources as $resource) {
			if (is_resource($resource)) {
				fclose($resource);
			}
		}
		foreach ($this->cleanupPaths as $path) {
			if (is_file($path)) {
				@unlink($path);
			}
		}
		parent::tearDown();
	}

	/**
	 * A synthetic RSA PKCS#12 credential signs through LibreSign's native
	 * path and the produced PDF carries a cryptographically valid signature.
	 */
	public function testNativeRsaPkcs12SignsCryptographicallyValidPdf(): void {
		$identity = $this->generatePkcs12('rsa');

		$this->handler
			->setInputFile($this->fileNode($this->simplePdf()))
			->setCertificate($identity['pfx'])
			->setPassword($identity['password']);

		$signed = $this->handler->getSignedContent();
		$this->assertStringStartsWith('%PDF', $signed);

		$this->assertCryptographicallyValid($signed, 1);
	}

	/**
	 * Signing an already-signed PDF through the native path adds a second
	 * signature while the first signature keeps its exact contents and
	 * both remain cryptographically valid. The fixture carries no DocMDP
	 * certification, so additional signatures are allowed.
	 */
	public function testSecondNativeSignaturePreservesFirstSignature(): void {
		$identity = $this->generatePkcs12('rsa');

		$this->handler
			->setInputFile($this->fileNode($this->simplePdf()))
			->setCertificate($identity['pfx'])
			->setPassword($identity['password']);
		$signedOnce = $this->handler->getSignedContent();
		$this->assertCryptographicallyValid($signedOnce, 1);

		$first = $this->extractSignatures($signedOnce);

		$this->handler
			->setInputFile($this->fileNode($signedOnce))
			->setCertificate($identity['pfx'])
			->setPassword($identity['password']);
		$signedTwice = $this->handler->getSignedContent();

		$both = $this->extractSignatures($signedTwice);
		$this->assertCount(2, $both, 'both signatures must be present after the second signing session');
		$this->assertSame(
			$first[0]['cms'],
			$both[0]['cms'],
			'the first signature contents must stay byte-identical',
		);

		$this->assertCryptographicallyValid($signedTwice, 2);
		foreach ($both as $index => $signature) {
			$this->assertTrue(
				$this->independentOpensslVerify($signature['cms'], $signature['content']),
				"signature #$index must verify independently after the second signing session",
			);
		}
	}

	/**
	 * An EC PKCS#12 credential exercises the ECDSA CMS path end to end:
	 * LibreSign signs, and the real validation layer parses and verifies
	 * the ECDSA signature that v0.3.0 assembles.
	 */
	public function testNativeEcdsaPkcs12SignsCryptographicallyValidPdf(): void {
		$identity = $this->generatePkcs12('ec');

		$this->handler
			->setInputFile($this->fileNode($this->simplePdf()))
			->setCertificate($identity['pfx'])
			->setPassword($identity['password']);

		$signed = $this->handler->getSignedContent();
		$this->assertStringStartsWith('%PDF', $signed);

		$this->assertCryptographicallyValid($signed, 1);
	}

	/**
	 * A crash after writing a cryptographically signed PDF must never cause a
	 * second signature when the signing job is recovered.
	 */
	public function testInterruptedPersistedPdfSignatureRequiresReviewBeforeRetry(): void {
		$identity = $this->generatePkcs12('rsa');
		$this->handler
			->setInputFile($this->fileNode($this->simplePdf()))
			->setCertificate($identity['pfx'])
			->setPassword($identity['password']);

		$request = $this->createSigningRequestFixture();
		$guard = $this->createAttemptGuard();
		$args = ['signRequestId' => $request->getId(), 'fileId' => $request->getFileId()];
		$pdfPath = $this->tempPath('');

		try {
			$guard->execute($args, '12345', function () use ($pdfPath): void {
				file_put_contents($pdfPath, $this->handler->getSignedContent());
				throw new \RuntimeException('Simulated interruption after writing PDF');
			});
			$this->fail('The simulated worker must stop after producing the signed PDF');
		} catch (\RuntimeException $e) {
			$this->assertSame('Simulated interruption after writing PDF', $e->getMessage());
		}

		$signedPdf = file_get_contents($pdfPath);
		$this->assertIsString($signedPdf);
		$this->assertCryptographicallyValid($signedPdf, 1);
		$this->assertFalse($guard->execute($args, '12346', function (): void {
			$this->fail('An interrupted signing operation cannot be repeated automatically');
		}));
		$this->assertSame($signedPdf, file_get_contents($pdfPath));
	}

	private function createSigningRequestFixture(): SignRequest {
		$file = new LibreSignFile();
		$file->setNodeId(random_int(300000, 999999));
		$file->setUserId('test-owner');
		$file->setUuid('crash-fixture-' . bin2hex(random_bytes(8)));
		$file->setCreatedAt(new \DateTime());
		$file->setName('synthetic-crash.pdf');
		$file->setStatus(FileStatus::SIGNING_IN_PROGRESS->value);
		$file = \OCP\Server::get(FileMapper::class)->insert($file);
		$this->testFiles[] = $file;

		$request = new SignRequest();
		$request->setUuid('crash-request-' . bin2hex(random_bytes(8)));
		$request->setFileId($file->getId());
		$request->setCreatedAt(new \DateTime());
		$request = \OCP\Server::get(SignRequestMapper::class)->insert($request);
		$this->testSignRequests[] = $request;
		return $request;
	}

	private function createAttemptGuard(): SigningAttemptGuard {
		return new SigningAttemptGuard(
			\OCP\Server::get(SignRequestMapper::class),
			\OCP\Server::get(ILockingProvider::class),
			\OCP\Server::get(IJobList::class),
			$this->createMock(SignRequestErrorReporter::class),
			$this->logger,
		);
	}

	private function assertCryptographicallyValid(string $pdf, int $expectedSignatureCount): void {
		$validationService = new PdfSignatureValidationService(
			$this->appConfig,
			$this->l10n,
			$this->logger,
		);
		$temp = tmpfile();
		$this->assertNotFalse($temp);
		$this->cleanupResources[] = $temp;
		fwrite($temp, $pdf);
		rewind($temp);

		$entries = $validationService->validateFromResource($temp);
		$this->assertCount($expectedSignatureCount, $entries, 'the validator must see every signature');
		foreach ($entries as $index => $entry) {
			$this->assertSame(
				true,
				$entry['signatureValidation']['isValid'] ?? null,
				"signature #$index must be cryptographically valid per LibreSign's validation service"
				. ' (failure reason: ' . ($entry['signatureValidation']['reason'] ?? 'n/a') . ')',
			);
		}

		$signatures = $this->extractSignatures($pdf);
		$this->assertCount($expectedSignatureCount, $signatures);
		foreach ($signatures as $index => $signature) {
			$this->assertTrue(
				$this->independentOpensslVerify($signature['cms'], $signature['content']),
				"signature #$index must verify independently with OpenSSL",
			);
		}
	}

	private function fileNode(string $content): File {
		// Integration storage is not writable in this context; the file node
		// is the only shim — the signing path itself is the real handler.
		$node = $this->createMock(File::class);
		$node->method('getContent')->willReturnCallback(static fn (): string => $content);
		$node->method('putContent')->willReturnCallback(static function (mixed $data) use (&$content): void {
			$content = (string)$data;
		});

		return $node;
	}

	/**
	 * @return array{pfx: string, password: string}
	 */
	private function generatePkcs12(string $keyType): array {
		$keyOptions = $keyType === 'ec'
			? ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]
			: ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];

		$configPath = $this->tempPath($this->opensslConfig());
		$configArgs = ['config' => $configPath, 'digest_alg' => 'sha256'];

		$caKey = openssl_pkey_new($keyOptions);
		$this->assertNotFalse($caKey);
		$caCsr = openssl_csr_new(['commonName' => 'Native Signing Regression Root'], $caKey, $configArgs);
		$this->assertNotFalse($caCsr);
		$caCert = openssl_csr_sign($caCsr, null, $caKey, 2, $configArgs + ['x509_extensions' => 'v3_ca'], random_int(1, 0x7fffffff));
		$this->assertNotFalse($caCert);

		$leafKey = openssl_pkey_new($keyOptions);
		$this->assertNotFalse($leafKey);
		$leafCsr = openssl_csr_new(['commonName' => 'Native Signing Regression Signer'], $leafKey, $configArgs);
		$this->assertNotFalse($leafCsr);
		$leafCert = openssl_csr_sign($leafCsr, $caCert, $caKey, 2, $configArgs + ['x509_extensions' => 'signing_cert'], random_int(1, 0x7fffffff));
		$this->assertNotFalse($leafCert);

		$password = 'regression-' . bin2hex(random_bytes(4));
		$this->assertTrue(
			openssl_pkcs12_export($leafCert, $pfx, $leafKey, $password, ['extra_certs' => [$caCert]]),
			'openssl_pkcs12_export must produce the synthetic credential',
		);

		return ['pfx' => $pfx, 'password' => $password];
	}

	private function tempPath(string $content): string {
		$path = tempnam(sys_get_temp_dir(), 'libresign-regression-');
		$this->assertNotFalse($path);
		$this->cleanupPaths[] = $path;
		file_put_contents($path, $content);

		return $path;
	}

	/**
	 * Minimal OpenSSL config so the synthetic certificates carry the key
	 * usage bits real signing certificates have.
	 */
	private function opensslConfig(): string {
		return <<< 'OPENSSL'
			[ req ]
			distinguished_name = req_distinguished_name
			[ req_distinguished_name ]
			[ v3_ca ]
			basicConstraints = critical,CA:TRUE
			keyUsage = keyCertSign, cRLSign
			subjectKeyIdentifier = hash
			authorityKeyIdentifier = keyid,issuer
			[ signing_cert ]
			basicConstraints = CA:FALSE
			keyUsage = critical, digitalSignature, nonRepudiation
			extendedKeyUsage = clientAuth
			subjectKeyIdentifier = hash
			authorityKeyIdentifier = keyid,issuer
			OPENSSL;
	}

	private function simplePdf(): string {
		$textLine = 'Native signing regression test';
		$objects = [
			1 => '<< /Type /Catalog /Pages 2 0 R >>',
			2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
			3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>',
			4 => '<< /Length ' . strlen($textLine) . " >>\nstream\n{$textLine}\nendstream",
		];

		$pdf = "%PDF-1.4\n";
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
		$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
		$pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

		return $pdf;
	}

	/**
	 * @return array<int, array{cms: string, content: string}>
	 */
	private function extractSignatures(string $pdf): array {
		if (! preg_match_all(
			'/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/',
			$pdf,
			$matches,
			\PREG_SET_ORDER,
		)) {
			$this->fail('No /ByteRange found in the signed PDF.');
		}

		$signatures = [];
		foreach ($matches as $match) {
			[$start, $length1, $start2, $length2] = array_map(intval(...), array_slice($match, 1, 4));
			$gap = substr($pdf, $start + $length1, $start2 - ($start + $length1));
			if (! preg_match('/<\s*([0-9A-Fa-f]*)\s*>/', $gap, $hexMatch)) {
				$this->fail('No /Contents placeholder inside a ByteRange gap.');
			}
			$hex = $hexMatch[1];
			if (strlen($hex) % 2 === 1) {
				$hex .= '0';
			}
			$signatures[] = [
				'cms' => hex2bin($hex),
				'content' => substr($pdf, $start, $length1) . substr($pdf, $start2, $length2),
			];
		}

		return $signatures;
	}

	/**
	 * Independent OpenSSL verification (test tooling only).
	 */
	private function independentOpensslVerify(string $cmsDer, string $coveredContent): bool {
		$cmsFile = tempnam(sys_get_temp_dir(), 'libresign-cms-');
		$contentFile = tempnam(sys_get_temp_dir(), 'libresign-content-');
		$this->assertNotFalse($cmsFile);
		$this->assertNotFalse($contentFile);
		$this->cleanupPaths[] = $cmsFile;
		$this->cleanupPaths[] = $contentFile;
		file_put_contents($cmsFile, $cmsDer);
		file_put_contents($contentFile, $coveredContent);

		$openssl = getenv('OPENSSL_BINARY') ?: (
			is_executable('/opt/homebrew/opt/openssl@3/bin/openssl')
				? '/opt/homebrew/opt/openssl@3/bin/openssl'
				: 'openssl'
		);
		exec(
			sprintf(
				'%s cms -verify -inform DER -in %s -content %s -out /dev/null -noverify -binary 2>&1',
				escapeshellarg($openssl),
				escapeshellarg($cmsFile),
				escapeshellarg($contentFile),
			),
			$outputLines,
			$exitCode,
		);

		return $exitCode === 0;
	}
}
