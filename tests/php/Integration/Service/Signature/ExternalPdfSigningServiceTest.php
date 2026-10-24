<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 rodrigodev
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Signature;

use OCA\Libresign\Handler\DocMdpHandler;
use OCA\Libresign\Service\Signature\Exception\ExternalSigningException;
use OCA\Libresign\Service\Signature\ExternalPdfSigningService;
use OCA\Libresign\Service\Signature\PdfSignatureValidationService;
use OCA\Libresign\Service\Signature\PreparedExternalOperation;
use OCA\Libresign\Tests\Integration\TestCase;
use OCA\Libresign\Vendor\phpseclib4\File\X509;
use OCP\ITempManager;
use Psr\Log\LoggerInterface;
use SignerPHP\PdfSigner\Application\DTO\CertificationLevel;
use SignerPHP\PdfSigner\Application\DTO\PdfContentDto;
use SignerPHP\PdfSigner\Application\DTO\SignatureActorDto;
use SignerPHP\PdfSigner\Application\DTO\SignatureMetadataDto;
use SignerPHP\PdfSigner\Application\DTO\SigningCertificateDto;
use SignerPHP\PdfSigner\Application\DTO\SigningContextDto;
use SignerPHP\PdfSigner\Application\DTO\SigningOptionsDto;
use SignerPHP\PdfSigner\Application\DTO\TimestampOptionsDto;
use SignerPHP\PdfSigner\Domain\Exception\SignerException;
use SignerPHP\PdfSigner\Presentation\Signer;

/**
 * Integration tests for the internal resumable external PDF-signing
 * service, against the real signerphp/pdf-signer prepare/complete API.
 *
 * Every positive scenario succeeds through LibreSign's
 * ExternalPdfSigningService — including its real validation dependency —
 * and is cross-checked with the independent OpenSSL CLI. The private-key
 * operation is performed outside the service under test
 * (openssl_private_encrypt DigestInfo for RSA, OpenSSL CLI pkeyutl for EC)
 * exactly like a real external signer: the private key never enters the
 * service. One scenario runs completion in a separate PHP process that
 * reconstructs the prepared operation explicitly and invokes LibreSign's
 * service — not only the upstream library.
 *
 * Certificate fixtures are generated with EXPLICIT extensions: the CA
 * carries CA:TRUE + keyCertSign/cRLSign, the default signing leaf carries
 * CA:FALSE + digitalSignature/nonRepudiation — no reliance on
 * machine-specific OpenSSL configuration defaults. With these realistic
 * signing certificates every positive scenario passes against the
 * PRISTINE vendored validation submodule; no local patch is involved.
 *
 * Separate tests document the observed behavior for certificates WITHOUT
 * a keyUsage extension and with explicitly INCOMPATIBLE key usage, and
 * the global keyUsage-toggle restoration contract of the service.
 */
final class ExternalPdfSigningServiceTest extends TestCase {
	private ExternalPdfSigningService $service;
	private PdfSignatureValidationService $validation;
	private ITempManager $tempManager;

	#[\Override]
	public function setUp(): void {
		$appConfig = $this->getMockAppConfigWithReset();
		$l10n = \OCP\Server::get(\OCP\L10N\IFactory::class)->get(\OCA\LibreSign\AppInfo\Application::APP_ID);
		$this->validation = new PdfSignatureValidationService(
			$appConfig,
			$l10n,
			\OCP\Server::get(LoggerInterface::class),
		);
		$this->service = new ExternalPdfSigningService(
			$this->validation,
			new DocMdpHandler($l10n),
		);
		$this->tempManager = \OCP\Server::get(ITempManager::class);
	}

	public function testRsaLifecyclePreparesSignsExternallyAndCompletes(): void {
		$identity = $this->generateIdentity('rsa');
		$pdf = $this->makePdf('External signing integration test');

		$prepared = $this->service->prepare(
			$this->buildContext($pdf, $identity['certificate'], [$identity['ca']]),
		);

		$this->assertSame('digest', $prepared->upstream->payload->inputType->value);
		$this->assertSame('sha256', $prepared->upstream->payload->digestAlgorithm->value);
		$this->assertSame('rsa-pkcs1-v1_5', $prepared->upstream->payload->signatureAlgorithm->value);
		$this->assertSame(32, strlen($prepared->upstream->payload->input()));
		$this->assertSame(hash('sha256', $pdf), $prepared->preparationInputSha256);

		$signature = $this->externalSign($prepared->upstream->payload->input(), $identity['privateKey'], 'rsa');
		$this->assertCompletedOutputValid(
			$prepared,
			$signature,
			$pdf,
			$identity['certificate'],
			1,
		);
	}

	public function testEcLifecycleWhenSupported(): void {
		$identity = $this->generateIdentity('ec');
		$pdf = $this->makePdf('EC external signing integration test');

		$prepared = $this->service->prepare(
			$this->buildContext($pdf, $identity['certificate'], [$identity['ca']]),
		);
		$this->assertSame('ecdsa', $prepared->upstream->payload->signatureAlgorithm->value);
		$this->assertSame('ecdsa-der', $prepared->upstream->payload->signatureEncoding->value);

		$signature = $this->externalSign($prepared->upstream->payload->input(), $identity['privateKey'], 'ec');
		$this->assertCompletedOutputValid(
			$prepared,
			$signature,
			$pdf,
			$identity['certificate'],
			1,
		);
	}

	/**
	 * Completion runs in a fresh PHP process that reconstructs the prepared
	 * operation explicitly and invokes LibreSign's service — its real
	 * validation dependency included — proving the prepared state survives
	 * process termination through the full service path.
	 */
	public function testCompletionAcrossSeparatePhpProcess(): void {
		$identity = $this->generateIdentity('rsa');
		$pdf = $this->makePdf('Resumable state across processes');

		$prepared = $this->service->prepare(
			$this->buildContext($pdf, $identity['certificate'], [$identity['ca']]),
		);

		$stateFile = $this->tempManager->getTemporaryFile();
		file_put_contents($stateFile, serialize($prepared));
		$signature = $this->externalSign($prepared->upstream->payload->input(), $identity['privateKey'], 'rsa');
		$signatureFile = $this->tempManager->getTemporaryFile();
		file_put_contents($signatureFile, $signature);
		$originalFile = $this->tempManager->getTemporaryFile();
		file_put_contents($originalFile, $pdf);
		$certificateFile = $this->tempManager->getTemporaryFile();
		file_put_contents($certificateFile, $identity['certificate']);
		$outputFile = $this->tempManager->getTemporaryFile();
		$completerScript = $this->tempManager->getTemporaryFile('.php');
		file_put_contents($completerScript, <<<'PHP'
			<?php
			declare(strict_types=1);
			[, $vendorAutoload, $appRoot, $stateFile, $signatureFile, $originalFile, $certificateFile, $outputFile] = $argv;
			require $vendorAutoload;
			if (! interface_exists('OCP\\IAppConfig')) {
				// Packaged app: the distributable vendor carries no dev
				// dependencies; OCP interfaces and shared runtime packages
				// (e.g. psr/log) come from the Nextcloud server harness
				// that loads the package.
				require_once dirname($appRoot, 2) . '/lib/composer/autoload.php';
				require_once dirname($appRoot, 2) . '/3rdparty/autoload.php';
			}
			require_once $appRoot . '/tests/php/Integration/Service/Signature/SubprocessInfrastructure.php';
			use OCA\LibreSign\Tests\Integration\Service\Signature\SubprocessAppConfig;
			use OCA\LibreSign\Tests\Integration\Service\Signature\SubprocessL10n;
			use OCA\LibreSign\Tests\Integration\Service\Signature\SubprocessLogger;
			$l10n = new SubprocessL10n();
			$service = new OCA\Libresign\Service\Signature\ExternalPdfSigningService(
				new OCA\Libresign\Service\Signature\PdfSignatureValidationService(
					new SubprocessAppConfig(),
					$l10n,
					new SubprocessLogger(),
				),
				new OCA\Libresign\Handler\DocMdpHandler($l10n),
			);
			$prepared = unserialize(file_get_contents($stateFile));
			if (! $prepared instanceof OCA\Libresign\Service\Signature\PreparedExternalOperation) {
				throw new RuntimeException('Could not reconstruct the prepared operation.');
			}
			$completed = $service->complete(
				$prepared,
				file_get_contents($signatureFile),
				file_get_contents($originalFile),
				file_get_contents($certificateFile),
			);
			file_put_contents($outputFile, $completed);
			PHP);

		exec(
			sprintf(
				'%s %s %s %s %s %s %s %s %s 2>&1',
				escapeshellarg(PHP_BINARY),
				escapeshellarg($completerScript),
				escapeshellarg(dirname(__DIR__, 5) . '/vendor/autoload.php'),
				escapeshellarg(dirname(__DIR__, 5)),
				escapeshellarg($stateFile),
				escapeshellarg($signatureFile),
				escapeshellarg($originalFile),
				escapeshellarg($certificateFile),
				escapeshellarg($outputFile),
			),
			$outputLines,
			$exitCode,
		);
		$this->assertSame(0, $exitCode, "Separate-process completion failed:\n" . implode("\n", $outputLines));

		$completed = (string)file_get_contents($outputFile);
		$this->assertStringStartsWith('%PDF', $completed);

		// The subprocess ran the full service path; independently confirm
		// the bytes carry exactly one valid signature.
		$signatures = $this->extractSignatures($completed);
		$this->assertCount(1, $signatures);
		$this->assertTrue(
			$this->independentOpensslVerify($signatures[0]['cms'], $signatures[0]['content']),
			'the subprocess-completed document must carry a valid signature',
		);
	}

	public function testSecondSignaturePreservesFirstSignature(): void {
		$identity = $this->generateIdentity('rsa');
		$pdf = $this->makePdf('Two signatures integration test');

		$firstPrepared = $this->service->prepare(
			$this->buildContext($pdf, $identity['certificate'], [$identity['ca']]),
		);
		$firstSignature = $this->externalSign($firstPrepared->upstream->payload->input(), $identity['privateKey'], 'rsa');
		$signedOnce = $this->service->complete($firstPrepared, $firstSignature, $pdf, $identity['certificate']);

		$secondPrepared = $this->service->prepare(
			$this->buildContext($signedOnce, $identity['certificate'], [$identity['ca']]),
		);
		$secondSignature = $this->externalSign($secondPrepared->upstream->payload->input(), $identity['privateKey'], 'rsa');
		$signedTwice = $this->service->complete($secondPrepared, $secondSignature, $signedOnce, $identity['certificate']);

		$first = $this->extractSignatures($signedOnce);
		$both = $this->extractSignatures($signedTwice);
		$this->assertCount(2, $both, 'both signatures must be present');
		$this->assertSame(
			$first[0]['cms'],
			$both[0]['cms'],
			'the first signature contents must be byte-identical after the second operation',
		);
		foreach ($both as $index => $signature) {
			$this->assertTrue(
				$this->independentOpensslVerify($signature['cms'], $signature['content']),
				"signature #$index must remain valid after the second signing operation",
			);
		}
	}

	public function testCrossOperationSignatureRejected(): void {
		$identity = $this->generateIdentity('rsa');
		$documentA = $this->makePdf('Cross lifecycle document A');
		$documentB = $this->makePdf('Cross lifecycle document B');

		$preparedA = $this->service->prepare(
			$this->buildContext($documentA, $identity['certificate'], [$identity['ca']]),
		);
		$preparedB = $this->service->prepare(
			$this->buildContext($documentB, $identity['certificate'], [$identity['ca']]),
		);

		$this->assertNotSame(
			$preparedA->upstream->payload->inputBase64,
			$preparedB->upstream->payload->inputBase64,
			'Deliberately different documents must produce different prepared payloads.',
		);

		$signatureForA = $this->externalSign($preparedA->upstream->payload->input(), $identity['privateKey'], 'rsa');

		// Independent ground truth: A's signature cannot satisfy B's prepared
		// operation.
		$completedB = $this->completeIndependently($preparedB, $signatureForA);
		$signatures = $this->extractSignatures($completedB);
		$this->assertCount(1, $signatures);
		$this->assertFalse(
			$this->independentOpensslVerify($signatures[0]['cms'], $signatures[0]['content']),
			'a signature from another prepared operation must not validate',
		);

		$this->expectException(ExternalSigningException::class);
		$this->service->complete($preparedB, $signatureForA, $documentB, $identity['certificate']);
	}

	/**
	 * A valid, cryptographically correct signature for document B must not
	 * be completed against an unrelated original document A. The binding
	 * lives in the prepared operation, so the substitution is rejected
	 * before completion regardless of the signature's validity.
	 */
	public function testDocumentSubstitutionRejectedDespiteValidSignature(): void {
		$identity = $this->generateIdentity('rsa');
		$documentA = $this->makePdf('Substitution target document A');
		$documentB = $this->makePdf('Substitution source document B');

		$preparedB = $this->service->prepare(
			$this->buildContext($documentB, $identity['certificate'], [$identity['ca']]),
		);
		$signatureForB = $this->externalSign($preparedB->upstream->payload->input(), $identity['privateKey'], 'rsa');

		// Ground truth: B's signature really is cryptographically valid for B.
		$completedB = $this->completeIndependently($preparedB, $signatureForB);
		$signatures = $this->extractSignatures($completedB);
		$this->assertCount(1, $signatures);
		$this->assertTrue(
			$this->independentOpensslVerify($signatures[0]['cms'], $signatures[0]['content']),
			'precondition: the prepared signature must be valid for its own document',
		);

		$this->expectException(ExternalSigningException::class);
		$this->expectExceptionMessage('does not match the document the prepared operation was created for');
		$this->service->complete($preparedB, $signatureForB, $documentA, $identity['certificate']);
	}

	public function testWrongKeySignatureRejectedAtValidation(): void {
		$identity = $this->generateIdentity('rsa');
		$otherKey = openssl_pkey_new([
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		]);
		$this->assertNotFalse($otherKey);
		$this->assertTrue(openssl_pkey_export($otherKey, $otherKeyPem));

		$pdf = $this->makePdf('Wrong key test');
		$prepared = $this->service->prepare(
			$this->buildContext($pdf, $identity['certificate'], [$identity['ca']]),
		);
		$signature = $this->externalSign($prepared->upstream->payload->input(), $otherKeyPem, 'rsa');

		// Independent ground truth: with the wrong key the signature really
		// is invalid.
		$completed = $this->completeIndependently($prepared, $signature);
		$signatures = $this->extractSignatures($completed);
		$this->assertCount(1, $signatures);
		$this->assertFalse(
			$this->independentOpensslVerify($signatures[0]['cms'], $signatures[0]['content']),
			'a wrong-key signature must not validate',
		);

		$this->expectException(ExternalSigningException::class);
		$this->service->complete($prepared, $signature, $pdf, $identity['certificate']);
	}

	public function testGarbageSignatureRejectedAtValidation(): void {
		$identity = $this->generateIdentity('rsa');
		$pdf = $this->makePdf('Garbage signature test');
		$prepared = $this->service->prepare(
			$this->buildContext($pdf, $identity['certificate'], [$identity['ca']]),
		);

		$this->expectException(ExternalSigningException::class);
		$this->service->complete($prepared, random_bytes(256), $pdf, $identity['certificate']);
	}

	public function testEmptySignatureRejectedBeforeReturningOutput(): void {
		$identity = $this->generateIdentity('rsa');
		$pdf = $this->makePdf('Empty signature test');
		$prepared = $this->service->prepare(
			$this->buildContext($pdf, $identity['certificate'], [$identity['ca']]),
		);

		try {
			$this->service->complete($prepared, '', $pdf, $identity['certificate']);
			$this->fail('Empty signature must not complete.');
		} catch (SignerException $exception) {
			$this->addToAssertionCount(1);
		}
	}

	public function testCorruptedStateRejectedDuringCompletion(): void {
		$identity = $this->generateIdentity('rsa');
		$pdf = $this->makePdf('Corrupted state test');
		$prepared = $this->service->prepare(
			$this->buildContext($pdf, $identity['certificate'], [$identity['ca']]),
		);

		$state = $prepared->upstream->state;
		$marker = strpos($state, 'unsignedPdf":"') + strlen('unsignedPdf":"');
		$char = $state[$marker];
		$state[$marker] = $char === 'A' ? 'B' : 'A';
		$corrupted = new PreparedExternalOperation(
			new \SignerPHP\PdfSigner\Application\DTO\PreparedExternalSignature(
				$prepared->upstream->payload,
				$state,
			),
			$prepared->preparationInputSha256,
		);

		$this->expectException(\SignerPHP\PdfSigner\Domain\Exception\SignerException::class);
		$this->service->complete($corrupted, random_bytes(256), $pdf, $identity['certificate']);
	}

	/**
	 * The signer certificate is resolved from the CMS SignerInfo, not from
	 * certificate-set ordering: a valid signature by the expected signer is
	 * accepted even though the certificate set's first entry is the CA, and
	 * a valid signature presented against an unrelated expected certificate
	 * is rejected.
	 */
	public function testMismatchedExpectedCertificateRejected(): void {
		$identity = $this->generateIdentity('rsa');
		$other = $this->generateIdentity('rsa');

		$pdf = $this->makePdf('Certificate binding test');
		$prepared = $this->service->prepare(
			$this->buildContext($pdf, $identity['certificate'], [$identity['ca']]),
		);
		$signature = $this->externalSign($prepared->upstream->payload->input(), $identity['privateKey'], 'rsa');

		$this->expectException(ExternalSigningException::class);
		$this->expectExceptionMessage('unexpected certificate');
		$this->service->complete($prepared, $signature, $pdf, $other['certificate']);
	}

	/**
	 * A document certified with DocMDP "no changes allowed" must be
	 * refused at preparation time.
	 */
	public function testCertifiedDocumentForbiddingAdditionalSignaturesRejected(): void {
		$identity = $this->generateIdentity('rsa');
		$certified = $this->makeCertifiedPdf($identity, CertificationLevel::NoChangesAllowed);

		$this->assertFalse(
			(new DocMdpHandler($this->l10n()))->allowsAdditionalSignatures($this->toResource($certified)),
			'precondition: the fixture must carry a no-changes-allowed certification signature',
		);

		$this->expectException(ExternalSigningException::class);
		$this->expectExceptionMessage('forbids additional signatures');
		$this->service->prepare(
			$this->buildContext($certified, $identity['certificate'], [$identity['ca']]),
		);
	}

	/**
	 * A document certified with a level that permits additional signatures
	 * passes the DocMDP gate and prepares normally.
	 */
	public function testCertifiedDocumentPermittingAdditionalSignaturesPrepares(): void {
		$identity = $this->generateIdentity('rsa');
		$certified = $this->makeCertifiedPdf($identity, CertificationLevel::FormFillAndSignatures);

		$this->assertTrue(
			(new DocMdpHandler($this->l10n()))->allowsAdditionalSignatures($this->toResource($certified)),
			'precondition: the fixture must permit additional signatures',
		);

		$prepared = $this->service->prepare(
			$this->buildContext($certified, $identity['certificate'], [$identity['ca']]),
		);
		$this->assertSame(hash('sha256', $certified), $prepared->preparationInputSha256);
	}

	public function testUnsupportedTimestampOptionRejected(): void {
		$identity = $this->generateIdentity('rsa');
		$pdf = $this->makePdf('Timestamp option test');

		$context = new SigningContextDto(
			new PdfContentDto($pdf),
			new SigningOptionsDto(
				metadata: new SignatureMetadataDto(
					reason: 'integration test',
					actor: new SignatureActorDto(name: 'Test Signer'),
				),
				timestamp: new TimestampOptionsDto('https://ts.invalid.example'),
				useDefaultAppearance: false,
			),
			new SigningCertificateDto($identity['certificate'], [$identity['ca']]),
		);

		$this->expectException(ExternalSigningException::class);
		$this->service->prepare($context);
	}

	/**
	 * Prior-signature retention compares signature CONTENTS, not just field
	 * name, range geometry and validity verdicts.
	 */
	public function testPriorSignatureContentChangeRejected(): void {
		$identity = $this->generateIdentity('rsa');
		$pdf = $this->makePdf('Retention content test');

		$firstPrepared = $this->service->prepare(
			$this->buildContext($pdf, $identity['certificate'], [$identity['ca']]),
		);
		$firstSignature = $this->externalSign($firstPrepared->upstream->payload->input(), $identity['privateKey'], 'rsa');
		$signedOnce = $this->service->complete($firstPrepared, $firstSignature, $pdf, $identity['certificate']);

		$secondPrepared = $this->service->prepare(
			$this->buildContext($signedOnce, $identity['certificate'], [$identity['ca']]),
		);
		$secondSignature = $this->externalSign($secondPrepared->upstream->payload->input(), $identity['privateKey'], 'rsa');
		$signedTwice = $this->service->complete($secondPrepared, $secondSignature, $signedOnce, $identity['certificate']);

		$baselineEntries = $this->validation->validateFromString($signedOnce);
		$completedEntries = $this->validation->validateFromString($signedTwice);
		$this->assertCount(1, $baselineEntries);
		$this->assertCount(2, $completedEntries);

		// Simulate altered prior contents: the prior entry keeps its
		// identity key but carries another signature's extracted CMS
		// (the real objects, a deliberately mismatched pairing).
		$baselineEntry = $baselineEntries[0];
		$otherSignature = $completedEntries[1]['signature'];
		$alteredEntry = array_merge($baselineEntry, ['signature' => $otherSignature]);

		$baselineByKey = [$this->entryKey($baselineEntry) => $baselineEntry];
		$completedByKey = [$this->entryKey($baselineEntry) => $alteredEntry];

		$this->expectException(ExternalSigningException::class);
		$this->expectExceptionMessage("signature's contents changed");
		$method = new \ReflectionMethod(ExternalPdfSigningService::class, 'assertPriorSignaturesRetained');
		$method->invoke($this->service, $baselineByKey, $completedByKey);
	}

	/**
	 * Duplicate signature identities are ambiguous and rejected instead of
	 * being silently overwritten in the associative identity map.
	 */
	public function testDuplicateSignatureIdentityRejected(): void {
		$identity = $this->generateIdentity('rsa');
		$pdf = $this->makePdf('Duplicate identity test');
		$prepared = $this->service->prepare(
			$this->buildContext($pdf, $identity['certificate'], [$identity['ca']]),
		);
		$signature = $this->externalSign($prepared->upstream->payload->input(), $identity['privateKey'], 'rsa');
		$completed = $this->service->complete($prepared, $signature, $pdf, $identity['certificate']);

		$entries = $this->validation->validateFromString($completed);
		$this->assertNotEmpty($entries);
		$duplicated = [$entries[0], $entries[0]];

		$this->expectException(ExternalSigningException::class);
		$this->expectExceptionMessage('Ambiguous signature identity');
		$method = new \ReflectionMethod(ExternalPdfSigningService::class, 'mapByIdentity');
		$method->invoke($this->service, $duplicated, 'completed');
	}

	/**
	 * Complete through the service and prove, with the independent OpenSSL
	 * check on the same bytes, that the returned document is validly
	 * signed. No skip paths: a service rejection fails the test.
	 */
	private function assertCompletedOutputValid(
		PreparedExternalOperation $prepared,
		string $signature,
		string $originalPdf,
		string $expectedCertificate,
		int $expectedSignatureCount,
	): void {
		$serviceBytes = $this->service->complete($prepared, $signature, $originalPdf, $expectedCertificate);
		$this->assertStringStartsWith('%PDF', $serviceBytes);
		$this->assertSame(
			$this->completeIndependently($prepared, $signature),
			$serviceBytes,
			'the service must return exactly the upstream completion bytes',
		);

		$signatures = $this->extractSignatures($serviceBytes);
		$this->assertCount($expectedSignatureCount, $signatures);
		foreach ($signatures as $signatureEntry) {
			$this->assertTrue(
				$this->independentOpensslVerify($signatureEntry['cms'], $signatureEntry['content']),
				'independent OpenSSL verification must accept the completed document',
			);
		}
	}

	private function completeIndependently(PreparedExternalOperation $prepared, string $signature): string {
		return Signer::externalSigner()->complete($prepared->upstream->state, $signature);
	}

	/**
	 * Build a certification-signature fixture through the upstream API.
	 */
	private function makeCertifiedPdf(array $identity, CertificationLevel $level): string {
		$pdf = $this->makePdf('Certified document fixture (' . $level->name . ')');
		$prepared = $this->service->prepare(
			$this->buildContext($pdf, $identity['certificate'], [$identity['ca']], $level),
		);
		$signature = $this->externalSign($prepared->upstream->payload->input(), $identity['privateKey'], 'rsa');

		return $this->completeIndependently($prepared, $signature);
	}

	private function toResource(string $bytes) {
		$resource = fopen('php://temp', 'r+');
		$this->assertNotFalse($resource);
		fwrite($resource, $bytes);
		rewind($resource);

		return $resource;
	}

	private function l10n(): \OCP\IL10N {
		return \OCP\Server::get(\OCP\L10N\IFactory::class)->get(\OCA\LibreSign\AppInfo\Application::APP_ID);
	}

	private function entryKey(array $entry): string {
		$method = new \ReflectionMethod(ExternalPdfSigningService::class, 'signatureEntryKey');
		return $method->invoke($this->service, $entry);
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
			$this->fail('No /ByteRange found in the completed PDF.');
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
	 * Independent verification with the OpenSSL CLI: the CMS is checked
	 * against the ByteRange-covered bytes. -noverify skips certificate-chain
	 * trust (synthetic test CA); -binary prevents text canonicalization.
	 * Test tooling only — the production path stays on PHP extensions.
	 */
	private function independentOpensslVerify(string $cmsDer, string $coveredContent): bool {
		$cmsFile = $this->tempManager->getTemporaryFile();
		$contentFile = $this->tempManager->getTemporaryFile();
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

	/**
	 * Global keyUsage state must survive service calls unchanged, in BOTH
	 * initial states. The scoped toggle inside signer resolution is an
	 * implementation detail, not a process-global policy change.
	 */
	public function testSignerResolutionPreservesGlobalKeyUsageStateInBothInitialStates(): void {
		$identity = $this->generateIdentity('rsa');
		$pdf = $this->makePdf('keyUsage state restoration');

		// Initially ENABLED (the engine default).
		X509::checkKeyUsage();
		$prepared = $this->service->prepare(
			$this->buildContext($pdf, $identity['certificate'], [$identity['ca']]),
		);
		$signature = $this->externalSign($prepared->upstream->payload->input(), $identity['privateKey'], 'rsa');
		$this->service->complete($prepared, $signature, $pdf, $identity['certificate']);
		$this->assertTrue(X509::isCheckKeyUsageEnabled(), 'keyUsage checking must stay enabled after completion.');

		// Initially DISABLED by a foreign caller: must be restored, not flipped.
		X509::ignoreKeyUsage();
		$prepared2 = $this->service->prepare(
			$this->buildContext($this->makePdf('keyUsage state restoration 2'), $identity['certificate'], [$identity['ca']]),
		);
		$signature2 = $this->externalSign($prepared2->upstream->payload->input(), $identity['privateKey'], 'rsa');
		$this->service->complete($prepared2, $signature2, $this->makePdf('keyUsage state restoration 2'), $identity['certificate']);
		$this->assertFalse(X509::isCheckKeyUsageEnabled(), 'A caller-disabled keyUsage state must be restored after completion.');
		X509::checkKeyUsage();
	}

	/**
	 * The service represents ONE cryptographic operation: a CMS structure
	 * with more than one SignerInfo is rejected outright instead of being
	 * resolved from the first entry. Fixture built with the supported
	 * OpenSSL cms tooling (two signers over the same content); resolution
	 * is identity-only here, so cryptographic validity is irrelevant.
	 */
	public function testMultipleSignerInfoEntriesAreRejected(): void {
		$identity = $this->generateIdentity('rsa');
		$other = $this->generateIdentity('rsa');

		$contentFile = $this->tempManager->getTemporaryFile();
		file_put_contents($contentFile, 'two-signer fixture');
		$certA = $this->tempManager->getTemporaryFile();
		file_put_contents($certA, $identity['certificate']);
		$keyA = $this->tempManager->getTemporaryFile();
		file_put_contents($keyA, $identity['privateKey']);
		chmod($keyA, 0600);
		$certB = $this->tempManager->getTemporaryFile();
		file_put_contents($certB, $other['certificate']);
		$keyB = $this->tempManager->getTemporaryFile();
		file_put_contents($keyB, $other['privateKey']);
		chmod($keyB, 0600);
		$cmsFile = $this->tempManager->getTemporaryFile();

		$openssl = getenv('OPENSSL_BINARY') ?: (
			is_executable('/opt/homebrew/opt/openssl@3/bin/openssl')
				? '/opt/homebrew/opt/openssl@3/bin/openssl'
				: 'openssl'
		);
		exec(
			sprintf(
				'%s cms -sign -in %s -outform DER -out %s -nodetach'
				. ' -signer %s -inkey %s -signer %s -inkey %s 2>&1',
				escapeshellarg($openssl),
				escapeshellarg($contentFile),
				escapeshellarg($cmsFile),
				escapeshellarg($certA),
				escapeshellarg($keyA),
				escapeshellarg($certB),
				escapeshellarg($keyB),
			),
			$outputLines,
			$exitCode,
		);
		$this->assertSame(0, $exitCode, "Two-signer CMS fixture failed:\n" . implode("\n", $outputLines));

		$cms = (string)file_get_contents($cmsFile);
		$this->assertNotSame('', $cms);

		$entry = ['signature' => $this->fakeSignatureWithBinary($cms)];
		$method = new \ReflectionMethod($this->service, 'resolveSignerCertificatePem');

		try {
			$method->invoke($this->service, $entry);
			$this->fail('Multiple SignerInfo entries must be rejected.');
		} catch (ExternalSigningException $exception) {
			$this->assertStringContainsString('SignerInfo', $exception->getMessage());
		}
	}

	/**
	 * The expected certificate appearing somewhere in the CMS certificate
	 * set (here: as chain material) does not make it the signer. The
	 * signer bound in the SignerInfo must win, in any certificate order.
	 */
	public function testExpectedCertificatePresentInChainButNotTheSignerIsRejected(): void {
		$identity = $this->generateIdentity('rsa');
		$other = $this->generateIdentity('rsa');
		$pdf = $this->makePdf('Chain membership is not signer identity');

		foreach ([[$identity['ca'], $other['certificate']], [$other['certificate'], $identity['ca']]] as $chain) {
			$prepared = $this->service->prepare(
				$this->buildContext($pdf, $identity['certificate'], $chain),
			);
			$signature = $this->externalSign($prepared->upstream->payload->input(), $identity['privateKey'], 'rsa');

			try {
				$this->service->complete($prepared, $signature, $pdf, $other['certificate']);
				$this->fail('A chain member that is not the signer must not be accepted as the expected certificate.');
			} catch (ExternalSigningException $exception) {
				$this->assertStringContainsString('unexpected certificate', $exception->getMessage());
			}

			// The actual signer still completes fine in the same CMS.
			$this->service->complete($prepared, $signature, $pdf, $identity['certificate']);
		}
	}

	/**
	 * keyUsage matrix, row 1 — certificate WITHOUT a keyUsage extension.
	 *
	 * Mathematical question: OpenSSL verifies the detached CMS signature
	 * (cryptographic validity is INDEPENDENT of key usage).
	 * Policy question: LibreSign's pristine validation layer REJECTS it,
	 * because the scoped CMS engine's signer resolution enforces keyUsage
	 * bits and a certificate without the extension fails that check.
	 *
	 * This documents CURRENT behavior on the committed dependencies; it is
	 * not an endorsement. Whether absent usage should be treated as "no
	 * signing permission" or "unspecified" is an open policy question for
	 * the maintainer (see handoff); the service deliberately does not
	 * decide it here.
	 */
	public function testCertificateWithoutKeyUsageIsCryptographicallyValidButRejectedByPolicy(): void {
		$identity = $this->generateIdentity('rsa', 'no-keyusage');
		$pdf = $this->makePdf('Absent keyUsage matrix row');
		$prepared = $this->service->prepare(
			$this->buildContext($pdf, $identity['certificate'], [$identity['ca']]),
		);
		$signature = $this->externalSign($prepared->upstream->payload->input(), $identity['privateKey'], 'rsa');

		// Mathematical verification is independent of key usage.
		$this->assertTrue(
			$this->independentOpensslCmsVerifies($prepared, $signature, $pdf),
			'A signature by a certificate without keyUsage must still verify mathematically.',
		);

		// The application layer rejects it on the pristine validator.
		try {
			$this->service->complete($prepared, $signature, $pdf, $identity['certificate']);
			$this->fail('Expected the validator to reject a signer certificate without keyUsage.');
		} catch (ExternalSigningException $exception) {
			$this->assertStringContainsString('not cryptographically valid', $exception->getMessage());
		}
	}

	/**
	 * keyUsage matrix, row 2 — certificate whose keyUsage explicitly
	 * ALLOWS only keyEncipherment (incompatible with document signing).
	 *
	 * Documents the observed behavior of the committed stack: whether
	 * mathematical verification succeeds and whether certificate policy
	 * rejects the use. Finalized to the observed outcome below.
	 */
	public function testCertificateWithExplicitlyIncompatibleKeyUsage(): void {
		$identity = $this->generateIdentity('rsa', 'encipherment');
		$pdf = $this->makePdf('Incompatible keyUsage matrix row');
		$prepared = $this->service->prepare(
			$this->buildContext($pdf, $identity['certificate'], [$identity['ca']]),
		);
		$signature = $this->externalSign($prepared->upstream->payload->input(), $identity['privateKey'], 'rsa');

		$this->assertTrue(
			$this->independentOpensslCmsVerifies($prepared, $signature, $pdf),
			'OpenSSL verifies the signature mathematically regardless of key usage.',
		);

		try {
			$this->service->complete($prepared, $signature, $pdf, $identity['certificate']);
			$this->fail('Expected the validator to reject a certificate whose key usage forbids signing.');
		} catch (ExternalSigningException $exception) {
			$this->assertStringContainsString('not cryptographically valid', $exception->getMessage());
		}
	}

	/**
	 * Mathematical (not policy) verification: complete through the
	 * upstream API alone, extract the CMS structure and verify the
	 * detached signature with the independent OpenSSL CLI.
	 */
	private function independentOpensslCmsVerifies(
		PreparedExternalOperation $prepared,
		string $signature,
		string $originalPdf,
	): bool {
		$completed = $this->completeIndependently($prepared, $signature);
		$this->assertNotFalse(strpos($completed, '%PDF'));
		$signatures = $this->extractSignatures($completed);
		if ($signatures === []) {
			return false;
		}
		foreach ($signatures as $entry) {
			if (! $this->independentOpensslVerify($entry['cms'], $entry['content'])) {
				return false;
			}
		}

		return true;
	}

	private function fakeSignatureWithBinary(string $binary): object {
		$fake = new \stdClass();
		$fake->binarySignature = $binary;

		return $fake;
	}

	/**
	 * Synthetic identity with EXPLICIT certificate extensions (no reliance
	 * on machine-specific OpenSSL configuration defaults).
	 *
	 * Profiles:
	 * - 'signing': leaf with keyUsage digitalSignature+nonRepudiation and
	 *   CA:FALSE — a realistic document-signing certificate.
	 * - 'no-keyusage': leaf with NO keyUsage extension (legacy fixtures).
	 * - 'encipherment': leaf whose keyUsage allows ONLY keyEncipherment —
	 *   explicitly incompatible with document signing.
	 *
	 * @return array{certificate: string, privateKey: string, ca: string, leafExtensions: array}
	 */
	private function generateIdentity(string $keyType, string $leafProfile = 'signing'): array {
		$keyOptions = $keyType === 'ec'
			? ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]
			: ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];

		$configFile = $this->tempManager->getTemporaryFile();
		$config = <<<'TXT'
			[req]
			distinguished_name = dn
			[dn]
			[v3_ca]
			basicConstraints = critical,CA:TRUE
			keyUsage = critical,keyCertSign,cRLSign
			subjectKeyIdentifier = hash
			authorityKeyIdentifier = keyid:always
			[v3_leaf_signing]
			basicConstraints = critical,CA:FALSE
			keyUsage = critical,digitalSignature,nonRepudiation
			subjectKeyIdentifier = hash
			authorityKeyIdentifier = keyid:always
			[v3_leaf_no_keyusage]
			basicConstraints = critical,CA:FALSE
			subjectKeyIdentifier = hash
			authorityKeyIdentifier = keyid:always
			[v3_leaf_encipherment]
			basicConstraints = critical,CA:FALSE
			keyUsage = critical,keyEncipherment
			subjectKeyIdentifier = hash
			authorityKeyIdentifier = keyid:always
			TXT;
		file_put_contents($configFile, $config);
		$section = match ($leafProfile) {
			'signing' => 'v3_leaf_signing',
			'no-keyusage' => 'v3_leaf_no_keyusage',
			'encipherment' => 'v3_leaf_encipherment',
			default => throw new \InvalidArgumentException("Unknown leaf profile $leafProfile"),
		};

		$caKey = openssl_pkey_new($keyOptions);
		$this->assertNotFalse($caKey);
		$caCsr = openssl_csr_new(['commonName' => 'Integration Test Root'], $caKey, ['digest_alg' => 'sha256']);
		$this->assertNotFalse($caCsr);
		$caCert = openssl_csr_sign($caCsr, null, $caKey, 2, [
			'digest_alg' => 'sha256',
			'config' => $configFile,
			'x509_extensions' => 'v3_ca',
		], random_int(1, 0x7fffffff));
		$this->assertNotFalse($caCert);
		openssl_x509_export($caCert, $caPem);

		$leafKey = openssl_pkey_new($keyOptions);
		$this->assertNotFalse($leafKey);
		$leafCsr = openssl_csr_new(['commonName' => 'Integration Test Signer'], $leafKey, ['digest_alg' => 'sha256']);
		$this->assertNotFalse($leafCsr);
		$leafCert = openssl_csr_sign($leafCsr, $caCert, $caKey, 2, [
			'digest_alg' => 'sha256',
			'config' => $configFile,
			'x509_extensions' => $section,
		], random_int(1, 0x7fffffff));
		$this->assertNotFalse($leafCert);
		openssl_x509_export($leafCert, $leafPem);
		$this->assertTrue(openssl_pkey_export($leafKey, $leafKeyPem));

		// Assert the intended extensions are actually present.
		$parsed = openssl_x509_parse($leafCert);
		$this->assertNotFalse($parsed);
		$extensions = $parsed['extensions'] ?? [];
		if ($leafProfile === 'signing') {
			$this->assertStringContainsString('Digital Signature', (string)($extensions['keyUsage'] ?? ''));
			$this->assertStringContainsString('CA:FALSE', (string)($extensions['basicConstraints'] ?? ''));
		} elseif ($leafProfile === 'no-keyusage') {
			$this->assertArrayNotHasKey('keyUsage', $extensions);
		} else {
			$this->assertStringContainsString('Key Encipherment', (string)($extensions['keyUsage'] ?? ''));
			$this->assertStringNotContainsString('Digital Signature', (string)($extensions['keyUsage'] ?? ''));
		}

		return ['certificate' => $leafPem, 'privateKey' => $leafKeyPem, 'ca' => $caPem, 'leafExtensions' => $extensions];
	}

	/**
	 * The private-key operation, performed outside the service under test.
	 * The input is the already-computed digest from the prepared payload: it
	 * must not be hashed again.
	 *
	 * - RSA: RSASSA-PKCS1-v1_5 over a DER DigestInfo of the digest, via
	 *   openssl_private_encrypt (the CMS sha256WithRSAEncryption signature
	 *   algorithm expects the DigestInfo form).
	 * - EC:  `openssl pkeyutl -sign` on the raw digest (for EC keys OpenSSL
	 *   signs the pre-hashed input directly), producing DER r||s.
	 */
	private function externalSign(string $digest, string $privateKeyPem, string $keyType): string {
		if ($keyType === 'rsa') {
			$key = openssl_pkey_get_private($privateKeyPem);
			$this->assertNotFalse($key);
			$digestInfo = hex2bin(match (strlen($digest)) {
				20 => '3021300906052b0e03021a05000414',
				32 => '3031300d060960864801650304020105000420',
				48 => '3041300d060960864801650304020205000430',
				64 => '3051300d060960864801650304020305000440',
				default => throw new \RuntimeException('Unsupported digest length ' . strlen($digest)),
			}) . $digest;
			$signature = '';
			$this->assertTrue(openssl_private_encrypt($digestInfo, $signature, $key, OPENSSL_PKCS1_PADDING));
			$this->assertNotSame('', $signature);

			return $signature;
		}

		$digestFile = $this->tempManager->getTemporaryFile();
		file_put_contents($digestFile, $digest);
		$keyFile = $this->tempManager->getTemporaryFile();
		file_put_contents($keyFile, $privateKeyPem);
		chmod($keyFile, 0600);
		$signatureFile = $this->tempManager->getTemporaryFile();

		$openssl = getenv('OPENSSL_BINARY') ?: (
			is_executable('/opt/homebrew/opt/openssl@3/bin/openssl')
				? '/opt/homebrew/opt/openssl@3/bin/openssl'
				: 'openssl'
		);
		exec(
			sprintf(
				'%s pkeyutl -sign -inkey %s -in %s -out %s 2>&1',
				escapeshellarg($openssl),
				escapeshellarg($keyFile),
				escapeshellarg($digestFile),
				escapeshellarg($signatureFile),
			),
			$outputLines,
			$exitCode,
		);
		$this->assertSame(0, $exitCode, "External signing failed:\n" . implode("\n", $outputLines));

		$signature = (string)file_get_contents($signatureFile);
		$this->assertNotSame('', $signature);

		return $signature;
	}

	private function buildContext(
		string $pdf,
		string $certificatePem,
		array $chainPem,
		?CertificationLevel $certificationLevel = null,
	): SigningContextDto {
		return new SigningContextDto(
			new PdfContentDto($pdf),
			new SigningOptionsDto(
				metadata: new SignatureMetadataDto(
					reason: 'issue 8354 integration test',
					actor: new SignatureActorDto(name: 'Integration Test Signer'),
				),
				certificationLevel: $certificationLevel,
				useDefaultAppearance: false,
			),
			new SigningCertificateDto($certificatePem, $chainPem),
		);
	}

	private function makePdf(string $textLine): string {
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
}
