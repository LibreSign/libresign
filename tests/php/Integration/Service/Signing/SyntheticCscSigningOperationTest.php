<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Signing;

use OCA\Libresign\Handler\DocMdpHandler;
use OCA\Libresign\Service\ExperimentalFeaturesService;
use OCA\Libresign\Service\Signature\Exception\ExternalSigningException;
use OCA\Libresign\Service\Signature\ExternalPdfSigningService;
use OCA\Libresign\Service\Signature\PdfSignatureValidationService;
use OCA\Libresign\Service\Signing\ExternalIntegrationRecord;
use OCA\Libresign\Service\Signing\ExternalIntegrationStore;
use OCA\Libresign\Service\Signing\ExternalSigningSessionStore;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscDriver;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscIntegration;
use OCA\Libresign\Service\Signing\SyntheticCscSigningOperation;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\L10N\IFactory;
use OCP\Security\ICrypto;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use SignerPHP\PdfSigner\Application\DTO\CertificationLevel;
use SignerPHP\PdfSigner\Application\DTO\PdfContentDto;
use SignerPHP\PdfSigner\Application\DTO\SignatureActorDto;
use SignerPHP\PdfSigner\Application\DTO\SignatureMetadataDto;
use SignerPHP\PdfSigner\Application\DTO\SignatureProfile;
use SignerPHP\PdfSigner\Application\DTO\SigningCertificateDto;
use SignerPHP\PdfSigner\Application\DTO\SigningContextDto;
use SignerPHP\PdfSigner\Application\DTO\SigningOptionsDto;
use SignerPHP\PdfSigner\Infrastructure\Native\Contract\HttpClientInterface;
use SignerPHP\PdfSigner\Infrastructure\Native\ValueObject\HttpResponse;
use SignerPHP\PdfSigner\Infrastructure\Remote\Csc\CscCertificateDiscovery;

/**
 * @group DB
 *
 * Synthetic CSC laboratory only: never contacts production services or
 * acquires signer credentials. Disposable Nextcloud/database required.
 */
final class SyntheticCscSigningOperationTest extends \OCA\Libresign\Tests\Integration\TestCase {
	private const ENDPOINT = 'http://127.0.0.1:15432';
	private const REQUEST_ID = 23456;
	private const ACTOR = 'synthetic-invited-signer';

	private IDBConnection $db;
	private ExternalIntegrationStore $integrations;
	private ExternalSigningSessionStore $sessions;
	private SyntheticCscSigningOperation $workflow;
	private ExternalPdfSigningService $pdfSigning;
	private PdfSignatureValidationService $signatureValidation;
	/** @var list<string> */
	private array $createdSessions = [];
	/** @var list<string> */
	private array $createdIntegrations = [];
	private bool $featureEnabled = true;

	#[\Override]
	public function setUp(): void {
		parent::setUp();
		$this->db = Server::get(IDBConnection::class);
		$this->integrations = Server::get(ExternalIntegrationStore::class);
		$features = $this->createMock(ExperimentalFeaturesService::class);
		$features->method('isEnabled')->willReturnCallback(fn (): bool => $this->featureEnabled);
		$this->sessions = new ExternalSigningSessionStore($this->db, Server::get(ICrypto::class),
			$features, $this->integrations);
		$l10n = Server::get(IFactory::class)->get('libresign');
		$this->signatureValidation = new PdfSignatureValidationService(
			$this->getMockAppConfigWithReset(), $l10n, Server::get(LoggerInterface::class));
		$this->pdfSigning = new ExternalPdfSigningService($this->signatureValidation, new DocMdpHandler($l10n));
		$this->workflow = new SyntheticCscSigningOperation($this->pdfSigning, $this->sessions);
	}

	#[\Override]
	public function tearDown(): void {
		try {
			foreach ($this->createdSessions as $id) {
				foreach (['libresign_ext_sign_private', 'libresign_ext_sign_audit', 'libresign_ext_sign_attempt'] as $table) {
					$qb = $this->db->getQueryBuilder();
					$qb->delete($table)->where($qb->expr()->eq('session_id', $qb->createNamedParameter($id)))->executeStatement();
				}
				$qb = $this->db->getQueryBuilder();
				$qb->delete('libresign_ext_sign_session')
					->where($qb->expr()->eq('public_id', $qb->createNamedParameter($id)))->executeStatement();
			}
			foreach ($this->createdIntegrations as $id) {
				$qb = $this->db->getQueryBuilder();
				$qb->delete('libresign_signing_integration')
					->where($qb->expr()->eq('public_id', $qb->createNamedParameter($id)))->executeStatement();
			}
		} finally {
			parent::tearDown();
		}
	}

	private function integration(): ExternalIntegrationRecord {
		$record = $this->integrations->create(CscDriver::ID, 'Dedicated synthetic CSC laboratory',
			['baseUrl' => self::ENDPOINT], []);
		$this->createdIntegrations[] = $record->id;
		return $this->integrations->setEnabled($record->id, $record->revision, true);
	}

	/** @return array{string, \OpenSSLAsymmetricKey} */
	private function certificate(): array {
		$key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
		self::assertInstanceOf(\OpenSSLAsymmetricKey::class, $key);
		$config = tempnam(sys_get_temp_dir(), 'ls-csc-cert-');
		self::assertIsString($config);
		try {
			file_put_contents($config, "[req]\ndistinguished_name = dn\n[dn]\n[v3_signer]\nbasicConstraints = critical,CA:FALSE\nkeyUsage = critical,digitalSignature,nonRepudiation\nsubjectKeyIdentifier = hash\n");
			$csr = openssl_csr_new(['commonName' => 'Synthetic CSC laboratory signer'], $key);
			self::assertNotFalse($csr);
			$cert = openssl_csr_sign($csr, null, $key, 1, [
				'config' => $config, 'x509_extensions' => 'v3_signer', 'digest_alg' => 'sha256',
			]);
			self::assertNotFalse($cert);
			self::assertTrue(openssl_x509_export($cert, $pem));
			return [$pem, $key];
		} finally {
			@unlink($config);
		}
	}

	private function pdf(): string {
		$pdf = file_get_contents(dirname(__DIR__, 3) . '/fixtures/pdfs/small_valid.pdf');
		self::assertIsString($pdf);
		return $pdf;
	}

	private function context(
		string $pdf,
		string $pem,
		SignatureProfile $profile = SignatureProfile::PdfBasic,
		?CertificationLevel $certification = null,
	): SigningContextDto {
		return new SigningContextDto(new PdfContentDto($pdf), new SigningOptionsDto(
			metadata: new SignatureMetadataDto(actor: new SignatureActorDto(name: 'Synthetic laboratory signer')),
			signatureProfile: $profile, useDefaultAppearance: false,
			certificationLevel: $certification,
		), new SigningCertificateDto($pem));
	}

	private function makePreviouslySignedPdf(string $pdf, string $certificatePem,
		\OpenSSLAsymmetricKey $key, ?CertificationLevel $certification = null): string {
		$prepared = $this->pdfSigning->prepare($this->context($pdf, $certificatePem,
			SignatureProfile::PdfBasic, $certification));
		$digestInfo = hex2bin('3031300d060960864801650304020105000420')
			. $prepared->upstream->payload->input();
		self::assertTrue(openssl_private_encrypt($digestInfo, $signature, $key, OPENSSL_PKCS1_PADDING));
		return $this->pdfSigning->complete($prepared, $signature, $pdf, $certificatePem);
	}

	private function laboratory(string $pem, \OpenSSLAsymmetricKey $key, int &$submissions, bool $timeout = false): CscIntegration {
		$transport = new class($pem, $key, $submissions, $timeout) implements HttpClientInterface {
			public function __construct(
				private string $pem,
				private \OpenSSLAsymmetricKey $key,
				private int &$submissions,
				private bool $timeout,
			) {
			}

			public function request(string $method, string $url, array $headers = [], string $body = '',
				int $timeoutSeconds = 10, bool $followRedirects = false): HttpResponse {
				SyntheticCscSigningOperationTest::assertSame('POST', $method);
				SyntheticCscSigningOperationTest::assertFalse($followRedirects);
				$operation = substr((string)parse_url($url, PHP_URL_PATH), strlen('/csc/v1/'));
				$data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
				$der = base64_decode((string)preg_replace('/-----[^-]+-----|\s+/', '', $this->pem), true);
				SyntheticCscSigningOperationTest::assertIsString($der);
				$result = match ($operation) {
					'info' => ['specs' => '1.0.4.0'],
					'credentials/info' => [
						'key' => ['status' => 'enabled', 'algo' => [CscCertificateDiscovery::SHA256_RSA_OID]],
						'cert' => ['certificates' => [base64_encode($der)]],
					],
					'credentials/authorize' => ['SAD' => 'synthetic-bound-sad'],
					'signatures/signHash' => $this->signHash($data),
					default => throw new \LogicException('Unexpected laboratory request ' . $operation),
				};
				if ($operation === 'signatures/signHash' && $this->timeout) {
					return new HttpResponse(0, '', 'synthetic timeout after submission');
				}
				return new HttpResponse(200, json_encode($result, JSON_THROW_ON_ERROR));
			}

			/** @param array<string, mixed> $data @return array<string, mixed> */
			private function signHash(array $data): array {
				$this->submissions++;
				SyntheticCscSigningOperationTest::assertSame('synthetic-bound-sad', $data['SAD']);
				$hash = base64_decode($data['hash'][0], true);
				SyntheticCscSigningOperationTest::assertIsString($hash);
				SyntheticCscSigningOperationTest::assertSame(32, strlen($hash));
				SyntheticCscSigningOperationTest::assertTrue(openssl_private_encrypt(
					hex2bin('3031300d060960864801650304020105000420') . $hash,
					$signature, $this->key, OPENSSL_PKCS1_PADDING));
				return ['signatures' => [base64_encode($signature)]];
			}
		};
		return CscIntegration::forSyntheticLab($transport, self::ENDPOINT);
	}

	/** @return array<string, int|string> */
	private function prepare(CscIntegration $laboratory, ExternalIntegrationRecord $integration,
		SigningContextDto $context): array {
		$session = $this->workflow->prepare($laboratory, $integration, 'synthetic-credential', $context,
			self::ACTOR, self::REQUEST_ID, 'approved-laboratory-attempt', 'pdf-etag-original',
			new \DateTimeImmutable('+1 hour'));
		$this->createdSessions[] = (string)$session['id'];
		return $session;
	}

	/** @return array<string, array{SignatureProfile}> */
	public static function profiles(): array {
		return ['PDF Basic' => [SignatureProfile::PdfBasic], 'PAdES B-B' => [SignatureProfile::PadesBaselineB]];
	}

	#[DataProvider('profiles')]
	public function testOneSyntheticSubmissionProducesValidatedPdfAndReservesPublication(SignatureProfile $profile): void {
		[$pem, $key] = $this->certificate();
		$submissions = 0;
		$lab = $this->laboratory($pem, $key, $submissions);
		$integration = $this->integration();
		$pdf = $this->pdf();
		$session = $this->prepare($lab, $integration, $this->context($pdf, $pem, $profile));
		self::assertSame('preparing', $session['status']);
		$result = $this->workflow->sign($lab, $integration, (string)$session['id'],
			self::REQUEST_ID, self::ACTOR, 'pdf-etag-original', $pdf);
		self::assertSame(1, $submissions);
		self::assertSame('completing', $result['session']['status']);
		self::assertStringStartsWith('%PDF-', $result['pdf']);
		self::assertGreaterThan(strlen($pdf), strlen($result['pdf']));
		try {
			$this->workflow->completeAndReserve((string)$session['id'], self::REQUEST_ID, self::ACTOR,
				'pdf-etag-original', $pdf, $pem);
			self::fail('A second publication reservation must never be granted');
		} catch (\DomainException $e) {
			self::assertStringContainsString('already reserved', $e->getMessage());
		}
		$reconstructed = $this->workflow->reconstructForReconciliation((string)$session['id'],
			self::REQUEST_ID, self::ACTOR, 'pdf-etag-original', $pdf, $pem);
		self::assertSame($result['pdf'], $reconstructed);
		self::assertSame('completing', $this->sessions->getForSignRequest(
			(string)$session['id'], self::REQUEST_ID, self::ACTOR)['status']);
		// No File/SignRequest publication was performed in this isolated test;
		// deliberately do not mark the session as completed.
	}

	public function testSyntheticPadesBPreservesPreviouslyValidPdfSignature(): void {
		[$priorPem, $priorKey] = $this->certificate();
		$previouslySigned = $this->makePreviouslySignedPdf($this->pdf(), $priorPem, $priorKey);
		$before = $this->signatureValidation->validateFromString($previouslySigned);
		self::assertCount(1, $before);
		self::assertTrue($before[0]['signatureValidation']['isValid']);
		[$pem, $key] = $this->certificate();
		$submissions = 0;
		$lab = $this->laboratory($pem, $key, $submissions);
		$integration = $this->integration();
		$session = $this->prepare($lab, $integration,
			$this->context($previouslySigned, $pem, SignatureProfile::PadesBaselineB));
		$result = $this->workflow->sign($lab, $integration, (string)$session['id'],
			self::REQUEST_ID, self::ACTOR, 'pdf-etag-original', $previouslySigned);
		self::assertSame(1, $submissions);
		self::assertSame('completing', $result['session']['status']);
		$after = $this->signatureValidation->validateFromString($result['pdf']);
		self::assertCount(2, $after);
		foreach ($after as $signature) {
			self::assertTrue($signature['signatureValidation']['isValid']);
		}
	}

	public function testDocMdpNoChangesRefusesSyntheticAdditionalSignature(): void {
		[$priorPem, $priorKey] = $this->certificate();
		$certified = $this->makePreviouslySignedPdf($this->pdf(), $priorPem, $priorKey,
			CertificationLevel::NoChangesAllowed);
		[$pem, $key] = $this->certificate();
		$submissions = 0;
		$lab = $this->laboratory($pem, $key, $submissions);
		$integration = $this->integration();
		$this->expectException(ExternalSigningException::class);
		$this->expectExceptionMessage('DocMDP');
		$this->prepare($lab, $integration, $this->context($certified, $pem));
	}

	public function testDocumentSubstitutionIsRejectedBeforePrivateKeySubmission(): void {
		[$pem, $key] = $this->certificate();
		$submissions = 0;
		$lab = $this->laboratory($pem, $key, $submissions);
		$integration = $this->integration();
		$pdf = $this->pdf();
		$session = $this->prepare($lab, $integration, $this->context($pdf, $pem));
		$this->expectException(\DomainException::class);
		try {
			$this->workflow->sign($lab, $integration, (string)$session['id'],
				self::REQUEST_ID, self::ACTOR, 'pdf-etag-original', $pdf . "\nchanged");
		} finally {
			self::assertSame(0, $submissions);
		}
	}

	public function testForeignSignerAndRequestAreDeniedBeforePrivateKeySubmission(): void {
		[$pem, $key] = $this->certificate();
		$submissions = 0;
		$lab = $this->laboratory($pem, $key, $submissions);
		$integration = $this->integration();
		$pdf = $this->pdf();
		$session = $this->prepare($lab, $integration, $this->context($pdf, $pem));
		foreach ([[self::REQUEST_ID + 1, self::ACTOR], [self::REQUEST_ID, 'another-invited-signer']] as [$requestId, $actorId]) {
			try {
				$this->workflow->sign($lab, $integration, (string)$session['id'],
					$requestId, $actorId, 'pdf-etag-original', $pdf);
				self::fail('Cross-actor or cross-request access must be rejected');
			} catch (\OutOfBoundsException $e) {
				self::assertSame('Signing session not found', $e->getMessage());
			}
		}
		self::assertSame(0, $submissions);
	}

	public function testReconciliationNeverReservesPublicationForAnUncertainSubmission(): void {
		[$pem, $key] = $this->certificate();
		$submissions = 0;
		$lab = $this->laboratory($pem, $key, $submissions, true);
		$integration = $this->integration();
		$pdf = $this->pdf();
		$session = $this->prepare($lab, $integration, $this->context($pdf, $pem));
		try {
			$this->workflow->sign($lab, $integration, (string)$session['id'],
				self::REQUEST_ID, self::ACTOR, 'pdf-etag-original', $pdf);
			self::fail('An unknown signHash result must not be published');
		} catch (\DomainException $e) {
			self::assertStringContainsString('uncertain', $e->getMessage());
		}
		$this->expectException(\DomainException::class);
		$this->expectExceptionMessage('No reserved CSC publication');
		$this->workflow->reconstructForReconciliation((string)$session['id'],
			self::REQUEST_ID, self::ACTOR, 'pdf-etag-original', $pdf, $pem);
	}

	public function testUncertainResultCannotBeSubmittedAgain(): void {
		[$pem, $key] = $this->certificate();
		$submissions = 0;
		$lab = $this->laboratory($pem, $key, $submissions, true);
		$integration = $this->integration();
		$pdf = $this->pdf();
		$session = $this->prepare($lab, $integration, $this->context($pdf, $pem));
		try {
			$this->workflow->sign($lab, $integration, (string)$session['id'],
				self::REQUEST_ID, self::ACTOR, 'pdf-etag-original', $pdf);
			self::fail('Provider timeout is an uncertain submission');
		} catch (\DomainException $e) {
			self::assertStringContainsString('uncertain', $e->getMessage());
		}
		self::assertSame(1, $submissions);
		self::assertSame('uncertain', $this->sessions->getForSignRequest((string)$session['id'], self::REQUEST_ID, self::ACTOR)['status']);
		// A fresh adapter models another PHP worker: its in-memory
		// signAttempted flag is reset, but durable session state must win.
		$freshLab = $this->laboratory($pem, $key, $submissions, true);
		$this->expectException(\DomainException::class);
		try {
			$this->workflow->sign($freshLab, $integration, (string)$session['id'],
				self::REQUEST_ID, self::ACTOR, 'pdf-etag-original', $pdf);
		} finally {
			self::assertSame(1, $submissions);
		}
	}

	public function testCertificateChangeIsRejectedBeforeSubmission(): void {
		[$pem, $key] = $this->certificate();
		[$otherPem, $otherKey] = $this->certificate();
		$submissions = 0;
		$lab = $this->laboratory($pem, $key, $submissions);
		$integration = $this->integration();
		$pdf = $this->pdf();
		$session = $this->prepare($lab, $integration, $this->context($pdf, $pem));
		$changedLab = $this->laboratory($otherPem, $otherKey, $submissions);
		$this->expectException(\DomainException::class);
		try {
			$this->workflow->sign($changedLab, $integration, (string)$session['id'],
				self::REQUEST_ID, self::ACTOR, 'pdf-etag-original', $pdf);
		} finally {
			self::assertSame(0, $submissions);
		}
	}

	public function testAdministratorTokenDoesNotAllowPersonalSigning(): void {
		[$pem] = $this->certificate();
		$integration = $this->integration();
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueBool')->willReturnCallback(
			static fn (string $key, bool $default = false): bool => $default,
		);
		$production = CscIntegration::fromConfiguredInstance($this->createMock(IClientService::class),
			$config, ['baseUrl' => 'https://signer.example.org'], 'admin-only-token');
		$this->expectException(\DomainException::class);
		$this->workflow->prepare($production, $integration, 'synthetic-credential',
			$this->context($this->pdf(), $pem), self::ACTOR, self::REQUEST_ID,
			'no-admin-consent', 'pdf-etag-original', new \DateTimeImmutable('+1 hour'));
	}
}
