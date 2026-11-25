<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Signing;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Events\SignedEventFactory;
use OCA\Libresign\Handler\DocMdpHandler;
use OCA\Libresign\Service\ExperimentalFeaturesService;
use OCA\Libresign\Service\File\SigningWorkflowStatusResolver;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\SequentialSigningService;
use OCA\Libresign\Service\Signature\Exception\ExternalSigningException;
use OCA\Libresign\Service\Signature\ExternalPdfSigningService;
use OCA\Libresign\Service\Signature\PdfSignatureValidationService;
use OCA\Libresign\Service\Signing\External\AuthorizedRequest;
use OCA\Libresign\Service\Signing\External\DocumentSource;
use OCA\Libresign\Service\Signing\External\ImmutableArtifactStore;
use OCA\Libresign\Service\Signing\External\Publication;
use OCA\Libresign\Service\Signing\External\PublicationCommitter;
use OCA\Libresign\Service\Signing\External\PublicationEvents;
use OCA\Libresign\Service\Signing\External\PublicationPreparer;
use OCA\Libresign\Service\Signing\External\SessionStore;
use OCA\Libresign\Service\Signing\ExternalIntegrationRecord;
use OCA\Libresign\Service\Signing\ExternalIntegrationStore;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscDriver;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscIntegration;
use OCA\Libresign\Service\Signing\SyntheticCscSigningOperation;
use OCA\Libresign\Service\Webhook\NativeWebhookEventPublisher;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\CertificationLevel;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\PdfContentDto;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SignatureActorDto;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SignatureMetadataDto;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SignatureProfile;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SigningCertificateDto;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SigningContextDto;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SigningOptionsDto;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Infrastructure\Native\Contract\HttpClientInterface;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Infrastructure\Native\ValueObject\HttpResponse;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Infrastructure\Remote\Csc\CscCertificateDiscovery;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Security\ICrypto;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;

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
	private SessionStore $sessions;
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
		$this->sessions = new SessionStore($this->db, Server::get(ICrypto::class),
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

	private function publisher(): Publication {
		return new Publication(
			new PublicationPreparer($this->sessions, $this->workflow, Server::get(FileMapper::class),
				new DocumentSource(Server::get(FileMapper::class), Server::get(FolderService::class)),
				new ImmutableArtifactStore(Server::get(IUserSession::class))),
			new PublicationCommitter($this->db, $this->sessions, Server::get(FileMapper::class),
				Server::get(SignRequestMapper::class), Server::get(FileStatusService::class),
				Server::get(SigningWorkflowStatusResolver::class), Server::get(SequentialSigningService::class),
				Server::get(NotificationDeliveryMapper::class)),
			new PublicationEvents(Server::get(FileStatusService::class),
				Server::get(SignRequestMapper::class), Server::get(SequentialSigningService::class),
				Server::get(SignedEventFactory::class), Server::get(IEventDispatcher::class),
				Server::get(NativeWebhookEventPublisher::class), Server::get(LoggerInterface::class)),
		);
	}

	/**
	 * @return array{AuthorizedRequest, \OCP\Files\File, string}
	 */
	private function persistRequestPdf(string $contents): array {
		$username = 'csc-owner-' . bin2hex(random_bytes(5));
		$user = $this->createAccount($username, 'discard-after-test');
		$node = Server::get(IRootFolder::class)->getUserFolder($user->getUID())
			->newFile('csc-request.pdf', $contents);
		$file = new FileEntity();
		$file->setUuid(bin2hex(random_bytes(16)));
		$file->setName($node->getName());
		$file->setNodeId($node->getId());
		$file->setUserId($user->getUID());
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);
		$file->setCreatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
		$file = Server::get(FileMapper::class)->insert($file);
		$request = new SignRequest();
		$request->setUuid(bin2hex(random_bytes(16)));
		$request->setFileId($file->getId());
		$request->setStatusEnum(SignRequestStatus::ABLE_TO_SIGN);
		$request->setDisplayName('Synthetic CSC invited signer');
		$request->setCreatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
		$request = Server::get(SignRequestMapper::class)->insert($request);
		$method = new IdentifyMethod();
		$method->setSignRequestId($request->getId());
		$method->setIdentifierKey('account');
		$method->setIdentifierValue($username);
		$method->setMandatory(1);
		Server::get(IdentifyMethodMapper::class)->insert($method);
		return [new AuthorizedRequest($file, $request,
			'sign-request:' . $request->getId()), $node, $node->getEtag()];
	}

	/** @return array<string, int|string> */
	private function prepareForPersistedRequest(
		CscIntegration $laboratory,
		ExternalIntegrationRecord $integration,
		SigningContextDto $context,
		AuthorizedRequest $request,
		string $etag,
	): array {
		$session = $this->workflow->prepare($laboratory, $integration, 'synthetic-credential', $context,
			$request->actorId, $request->signRequest->getId(),
			'approved-laboratory-attempt', $etag, new \DateTimeImmutable('+1 hour'));
		$this->createdSessions[] = (string)$session['id'];
		return $session;
	}

	public function testCscPdfPublishesIntoTheOriginalRequestExactlyOnce(): void {
		[$pem, $key] = $this->certificate();
		$pdf = $this->pdf();
		[$request, $source, $etag] = $this->persistRequestPdf($pdf);
		$labCalls = 0;
		$lab = $this->laboratory($pem, $key, $labCalls);
		$integration = $this->integration();
		$session = $this->prepareForPersistedRequest($lab, $integration,
			$this->context($pdf, $pem, SignatureProfile::PadesBaselineB), $request, $etag);
		$result = $this->workflow->sign($lab, $integration, (string)$session['id'],
			$request->signRequest->getId(), $request->actorId, $etag, $pdf);
		self::assertSame('completing', $result['session']['status']);
		self::assertSame(1, $labCalls);
		$published = $this->publisher()->publish($request, (string)$session['id'], $pem);
		self::assertSame('completed', $published['status']);
		self::assertSame(hash('sha256', $result['pdf']), $published['signedHash']);
		self::assertSame('completed', $this->sessions->getForSignRequest((string)$session['id'],
			$request->signRequest->getId(), $request->actorId)['status']);
		$fromDatabase = Server::get(SignRequestMapper::class)->getById($request->signRequest->getId());
		self::assertSame(SignRequestStatus::SIGNED, $fromDatabase->getStatusEnum());
		self::assertNotNull($fromDatabase->getSigned());
		self::assertSame(hash('sha256', $result['pdf']), $fromDatabase->getSignedHash());
		$storedFile = Server::get(FileMapper::class)->getById($request->file->getId());
		self::assertSame($published['signedNodeId'], $storedFile->getSignedNodeId());
		self::assertSame(FileStatus::SIGNED->value, $storedFile->getStatus());
		self::assertSame($pdf, $source->getContent());
		$output = Server::get(FolderService::class)->getReadableNodeById(
			$storedFile->getUserId(), $published['signedNodeId']);
		self::assertInstanceOf(\OCP\Files\File::class, $output);
		self::assertSame($result['pdf'], $output->getContent());
		$validations = $this->signatureValidation->validateFromString($output->getContent());
		self::assertCount(1, $validations);
		self::assertTrue($validations[0]['signatureValidation']['isValid']);
		try {
			$this->publisher()->publish($request, (string)$session['id'], $pem);
			self::fail('Repeated publication of a completed session must be rejected');
		} catch (\DomainException $e) {
			self::assertStringContainsString('not reserved', $e->getMessage());
		}
		self::assertSame(1, $labCalls);
		self::assertSame($result['pdf'], $output->getContent());
	}

	public function testPublicationRejectsModifiedSourceAfterSyntheticSigning(): void {
		[$pem, $key] = $this->certificate();
		$pdf = $this->pdf();
		[$request, $source, $etag] = $this->persistRequestPdf($pdf);
		$submissions = 0;
		$lab = $this->laboratory($pem, $key, $submissions);
		$integration = $this->integration();
		$session = $this->prepareForPersistedRequest($lab, $integration,
			$this->context($pdf, $pem), $request, $etag);
		$result = $this->workflow->sign($lab, $integration, (string)$session['id'],
			$request->signRequest->getId(), $request->actorId, $etag, $pdf);
		self::assertSame('completing', $result['session']['status']);
		$source->putContent($pdf . "\nmodified after signHash");
		try {
			$this->publisher()->publish($request, (string)$session['id'], $pem);
			self::fail('Modified document must never be published as signed');
		} catch (\DomainException $e) {
			self::assertStringContainsString('revision', $e->getMessage());
		}
		$fromDb = Server::get(SignRequestMapper::class)->getById($request->signRequest->getId());
		self::assertSame(SignRequestStatus::ABLE_TO_SIGN, $fromDb->getStatusEnum());
		self::assertNull($fromDb->getSignedHash());
		self::assertNull(Server::get(FileMapper::class)->getById($request->file->getId())->getSignedNodeId());
		self::assertSame('completing', $this->sessions->getForSignRequest((string)$session['id'],
			$request->signRequest->getId(), $request->actorId)['status']);
		self::assertSame(1, $submissions);
	}

	public function testAlreadyStoredImmutableArtifactIsReusedAfterPublicationCasConflict(): void {
		[$pem, $key] = $this->certificate();
		$pdf = $this->pdf();
		[$request, $source, $etag] = $this->persistRequestPdf($pdf);
		$submissions = 0;
		$lab = $this->laboratory($pem, $key, $submissions);
		$integration = $this->integration();
		$session = $this->prepareForPersistedRequest($lab, $integration,
			$this->context($pdf, $pem), $request, $etag);
		$this->workflow->sign($lab, $integration, (string)$session['id'],
			$request->signRequest->getId(), $request->actorId, $etag, $pdf);
		$signRequestId = $request->signRequest->getId();
		// Simulate another writer changing the request after the PDF was
		// validated but before CSC can claim the authoritative signer row.
		$qb = $this->db->getQueryBuilder();
		$qb->update('libresign_sign_request')
			->set('status', $qb->createNamedParameter(SignRequestStatus::SIGNED->value,
				\OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($signRequestId)))
			->executeStatement();
		try {
			$this->publisher()->publish($request, (string)$session['id'], $pem);
			self::fail('A concurrently changed SignRequest must reject publication');
		} catch (\DomainException $e) {
			self::assertStringContainsString('already completed', $e->getMessage());
		}
		self::assertSame('completing', $this->sessions->getForSignRequest((string)$session['id'],
			$signRequestId, $request->actorId)['status']);
		self::assertSame(1, $submissions);
		self::assertNull(Server::get(FileMapper::class)->getById($request->file->getId())->getSignedNodeId());
		// Simulate explicit authorized conflict reconciliation restoring the
		// unsigned state after the competing writer is rolled back.
		$qb = $this->db->getQueryBuilder();
		$qb->update('libresign_sign_request')
			->set('status', $qb->createNamedParameter(SignRequestStatus::ABLE_TO_SIGN->value,
				\OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($signRequestId)))
			->executeStatement();
		$outputName = substr(pathinfo($source->getName(), PATHINFO_FILENAME), 0, 80)
			. '_signed_' . $request->file->getId() . '_' . $signRequestId
			. '_' . substr((string)$session['id'], 0, 12) . '.pdf';
		$existingNode = $source->getParent()->get($outputName);
		$existingId = $existingNode->getId();
		$published = $this->publisher()->publish($request, (string)$session['id'], $pem);
		self::assertSame('completed', $published['status']);
		self::assertSame($existingId, $published['signedNodeId']);
		self::assertSame(1, $submissions);
	}

	/**
	 * A failure after claiming the signer row must roll back every database
	 * update. The already-validated artifact can then be reused safely.
	 */
	public function testMetadataFailureRollsBackAndReconcilesWithoutAnotherSignature(): void {
		[$pem, $key] = $this->certificate();
		$pdf = $this->pdf();
		[$request, $source, $etag] = $this->persistRequestPdf($pdf);
		$submissions = 0;
		$lab = $this->laboratory($pem, $key, $submissions);
		$integration = $this->integration();
		$session = $this->prepareForPersistedRequest($lab, $integration,
			$this->context($pdf, $pem), $request, $etag);
		$this->workflow->sign($lab, $integration, (string)$session['id'],
			$request->signRequest->getId(), $request->actorId, $etag, $pdf);
		$requestId = (int)$request->signRequest->getId();
		$qb = $this->db->getQueryBuilder();
		$qb->update('libresign_sign_request')
			->set('metadata', $qb->createNamedParameter('{invalid-json'))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($requestId)))
			->executeStatement();
		try {
			$this->publisher()->publish($request, (string)$session['id'], $pem);
			self::fail('Invalid metadata must reject publication before commit');
		} catch (\JsonException) {
			// The signer row was claimed inside the aborted transaction.
		}
		$stored = Server::get(SignRequestMapper::class)->getById($requestId);
		self::assertSame(SignRequestStatus::ABLE_TO_SIGN, $stored->getStatusEnum());
		self::assertNull($stored->getSigned());
		self::assertNull($stored->getSignedHash());
		self::assertNull(Server::get(FileMapper::class)->getById($request->file->getId())->getSignedNodeId());
		self::assertSame('completing', $this->sessions->getForSignRequest((string)$session['id'],
			$requestId, $request->actorId)['status']);
		self::assertSame(1, $submissions);

		$qb = $this->db->getQueryBuilder();
		$qb->update('libresign_sign_request')
			->set('metadata', $qb->createNamedParameter('{}'))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($requestId)))
			->executeStatement();
		$published = $this->publisher()->publish($request, (string)$session['id'], $pem);
		self::assertSame('completed', $published['status']);
		self::assertSame(SignRequestStatus::SIGNED,
			Server::get(SignRequestMapper::class)->getById($requestId)->getStatusEnum());
		self::assertSame(1, $submissions);
	}

	public function testPublicationPreservesOriginalAndPriorSignedPdfRevision(): void {
		[$priorPem, $priorKey] = $this->certificate();
		$previous = $this->makePreviouslySignedPdf($this->pdf(), $priorPem, $priorKey);
		[$pem, $key] = $this->certificate();
		[$request, $source, $etag] = $this->persistRequestPdf($previous);
		$submissions = 0;
		$lab = $this->laboratory($pem, $key, $submissions);
		$integration = $this->integration();
		$session = $this->prepareForPersistedRequest($lab, $integration,
			$this->context($previous, $pem, SignatureProfile::PadesBaselineB), $request, $etag);
		$this->workflow->sign($lab, $integration, (string)$session['id'],
			$request->signRequest->getId(), $request->actorId, $etag, $previous);
		$published = $this->publisher()->publish($request, (string)$session['id'], $pem);
		self::assertSame('completed', $published['status']);
		self::assertSame($previous, $source->getContent());
		$stored = Server::get(FolderService::class)->getReadableNodeById(
			$request->file->getUserId(), $published['signedNodeId']);
		self::assertInstanceOf(\OCP\Files\File::class, $stored);
		$signatures = $this->signatureValidation->validateFromString($stored->getContent());
		self::assertCount(2, $signatures);
		foreach ($signatures as $signature) {
			self::assertTrue($signature['signatureValidation']['isValid']);
		}
		self::assertSame(1, $submissions);
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
