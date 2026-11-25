<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Signing;

use OCA\Libresign\Controller\ExternalSigningWorkflowController;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
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
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\SequentialSigningService;
use OCA\Libresign\Service\Signature\ExternalPdfSigningService;
use OCA\Libresign\Service\Signature\PdfSignatureValidationService;
use OCA\Libresign\Service\Signing\ExternalIntegrationAvailability;
use OCA\Libresign\Service\Signing\ExternalIntegrationStore;
use OCA\Libresign\Service\Signing\External\Authorization;
use OCA\Libresign\Service\Signing\External\CredentialSelection;
use OCA\Libresign\Service\Signing\ExternalSigningIntegrationScopeAccess;
use OCA\Libresign\Service\Signing\External\LaboratoryFactory;
use OCA\Libresign\Service\Signing\External\Publication;
use OCA\Libresign\Service\Signing\External\SessionStore;
use OCA\Libresign\Service\Signing\ExternalSigningWorkflowService;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscDriver;
use OCA\Libresign\Service\Signing\SyntheticCscSigningOperation;
use OCA\Libresign\Service\Validation\SignerValidator;
use OCA\Libresign\Service\Validation\SigningRequestValidator;
use OCA\Libresign\Service\Webhook\NativeWebhookEventPublisher;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Infrastructure\Remote\Csc\CscCertificateDiscovery;
use OCP\AppFramework\Http;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\Group\ISubAdmin;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Security\ICrypto;
use OCP\Server;
use Psr\Log\LoggerInterface;

/**
 * @group DB
 *
 * A disposable Nextcloud with real SQLite migration, filesystem and
 * published SignRequest. The OCP HTTP client is deterministic and never
 * contacts a real CSC provider, but SignerPHP signs actual prepared digests.
 */
final class ExternalSigningWorkflowServiceTest extends \OCA\Libresign\Tests\Integration\TestCase {
	private const URL = 'https://synthetic-csc.example.org';
	private const EMAIL = 'authorized@example.org';
	private const TOKEN = 'verified-synthetic-code';
	private const CREDENTIAL = 'synthetic-lab-key';

	private IDBConnection $db;
	private ExternalIntegrationStore $integrations;
	private SessionStore $sessions;
	private ExternalSigningWorkflowService $service;
	private PdfSignatureValidationService $validation;
	private SignRequest $request;
	private FileEntity $file;
	private \OCP\Files\File $source;
	private string $integrationId;
	private int $submitted = 0;
	private bool $timeout = false;
	private bool $policyAllowed = true;
	private string $certificatePem;
	private \OpenSSLAsymmetricKey $privateKey;

	#[\Override]
	public function setUp(): void {
		parent::setUp();
		$this->db = Server::get(IDBConnection::class);
		$certificate = $this->syntheticCertificate();
		$this->certificatePem = $certificate[0];
		$this->privateKey = $certificate[1];
		$pdf = file_get_contents(dirname(__DIR__, 3) . '/fixtures/pdfs/small_valid.pdf');
		self::assertIsString($pdf);
		$user = $this->createAccount('csc-api-' . bin2hex(random_bytes(5)), 'temporary-password');
		$this->source = Server::get(IRootFolder::class)->getUserFolder($user->getUID())
			->newFile('source.pdf', $pdf);
		$this->file = new FileEntity();
		$this->file->setUuid(bin2hex(random_bytes(16)));
		$this->file->setName('source.pdf');
		$this->file->setNodeId($this->source->getId());
		$this->file->setUserId($user->getUID());
		$this->file->setStatus(FileStatus::ABLE_TO_SIGN->value);
		$this->file->setCreatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
		$this->file = Server::get(FileMapper::class)->insert($this->file);
		$this->request = new SignRequest();
		$this->request->setUuid(bin2hex(random_bytes(16)));
		$this->request->setFileId($this->file->getId());
		$this->request->setStatusEnum(SignRequestStatus::ABLE_TO_SIGN);
		$this->request->setDisplayName('Synthetic authorized signer');
		$this->request->setCreatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
		$this->request = Server::get(SignRequestMapper::class)->insert($this->request);
		$this->integrations = Server::get(ExternalIntegrationStore::class);
		$created = $this->integrations->create(CscDriver::ID, 'Laboratory only', ['baseUrl' => self::URL], []);
		$record = $this->integrations->setEnabled($created->id, $created->revision, true);
		$this->integrationId = $record->id;

		$features = $this->createMock(ExperimentalFeaturesService::class);
		$features->method('isEnabled')->willReturn(true);
		$this->sessions = new SessionStore($this->db, Server::get(ICrypto::class),
			$features, $this->integrations);
		$methods = $this->createMock(IdentifyMethodService::class);
		$identity = new IdentifyMethod();
		$identity->setIdentifierKey('email');
		$identity->setIdentifierValue(self::EMAIL);
		$method = $this->createMock(IIdentifyMethod::class);
		$method->method('getEntity')->willReturn($identity);
		$methods->method('getIdentifyMethodsFromSignRequestId')->willReturn(['email' => [$method]]);
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn(null);
		$authorization = new Authorization(
			$features, Server::get(SignRequestMapper::class), Server::get(FileMapper::class),
			$this->createMock(SignerValidator::class),
			$this->createMock(SigningRequestValidator::class),
			$methods, $userSession,
		);
		$availability = new ExternalIntegrationAvailability($features);
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueBool')->willReturnCallback(static fn (string $name, bool $default = false): bool => $default);
		$fingerprint = openssl_x509_fingerprint($this->certificatePem, 'sha256');
		self::assertIsString($fingerprint);
		$config->method('getSystemValueString')->willReturnCallback(
			static fn (string $name, string $default = ''): string => match ($name) {
				'libresign_csc_synthetic_lab_url' => self::URL,
				'libresign_csc_synthetic_lab_certificate_sha256' => $fingerprint,
				default => $default,
			});
		$client = $this->createMock(IClient::class);
		$client->method('post')->willReturnCallback($this->respondToCsc(...));
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($client);
		$laboratories = new LaboratoryFactory(
			$this->integrations, $availability, Server::get(CscDriver::class),
			$clientService, $config,
		);
		$selection = new CredentialSelection($authorization,
			$this->integrations, $availability, Server::get(CscDriver::class));
		$l10n = Server::get(IFactory::class)->get('libresign');
		$this->validation = new PdfSignatureValidationService(
			$this->getMockAppConfigWithReset(), $l10n, Server::get(LoggerInterface::class));
		$pdfSigning = new ExternalPdfSigningService($this->validation, new DocMdpHandler($l10n));
		$crypto = new SyntheticCscSigningOperation($pdfSigning, $this->sessions);
		$publisher = new Publication(
			$this->db, $this->sessions, $crypto,
			Server::get(FileMapper::class), Server::get(SignRequestMapper::class),
			Server::get(FolderService::class), Server::get(FileStatusService::class),
			Server::get(SigningWorkflowStatusResolver::class), Server::get(SequentialSigningService::class),
			Server::get(\OCA\Libresign\Db\NotificationDeliveryMapper::class),
			Server::get(SignedEventFactory::class), Server::get(IEventDispatcher::class),
			Server::get(NativeWebhookEventPublisher::class), Server::get(IUserSession::class),
			Server::get(LoggerInterface::class),
		);
		$this->service = new ExternalSigningWorkflowService(
			$authorization, $laboratories, $selection, $this->sessions, $crypto, $publisher,
			Server::get(FileMapper::class), Server::get(FolderService::class),
			new ExternalSigningIntegrationScopeAccess(
				$this->allowPolicyForWorkflowTest(),
				Server::get(IGroupManager::class),
				Server::get(IUserManager::class), Server::get(IUserSession::class),
				Server::get(ISubAdmin::class),
			),
		);
	}

	/** The signing laboratory tests exercise the CSC lifecycle, not the policy resolver. */
	private function allowPolicyForWorkflowTest(): PolicyService {
		$policy = $this->createMock(PolicyService::class);
		$policy->method('resolveForUserId')->willReturnCallback(
			fn (): ResolvedPolicy => (new ResolvedPolicy())->setEffectiveValue($this->policyAllowed),
		);
		return $policy;
	}

	#[\Override]
	public function tearDown(): void {
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('libresign_ext_sign_private')->where($qb->expr()->in('session_id',
				$qb->createNamedParameter($this->createdSessionIds(), \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_STR_ARRAY)))
				->executeStatement();
			$qb = $this->db->getQueryBuilder();
			$qb->delete('libresign_ext_sign_attempt')->where($qb->expr()->in('session_id',
				$qb->createNamedParameter($this->createdSessionIds(), \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_STR_ARRAY)))
				->executeStatement();
			$qb = $this->db->getQueryBuilder();
			$qb->delete('libresign_ext_sign_audit')->where($qb->expr()->in('session_id',
				$qb->createNamedParameter($this->createdSessionIds(), \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_STR_ARRAY)))
				->executeStatement();
			$qb = $this->db->getQueryBuilder();
			$qb->delete('libresign_ext_sign_session')->where($qb->expr()->eq('sign_request_id',
				$qb->createNamedParameter($this->request->getId())))
				->executeStatement();
			$qb = $this->db->getQueryBuilder();
			$qb->delete('libresign_signing_integration')->where($qb->expr()->eq('public_id',
				$qb->createNamedParameter($this->integrationId)))
				->executeStatement();
		} finally {
			parent::tearDown();
		}
	}

	/** @return list<string> */
	private function createdSessionIds(): array {
		$qb = $this->db->getQueryBuilder();
		$ids = $qb->select('public_id')->from('libresign_ext_sign_session')
			->where($qb->expr()->eq('sign_request_id', $qb->createNamedParameter($this->request->getId())))
			->executeQuery()->fetchFirstColumn();
		return array_map(strval(...), $ids);
	}

	/** @return array{string, \OpenSSLAsymmetricKey} */
	private function syntheticCertificate(): array {
		$key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
		self::assertInstanceOf(\OpenSSLAsymmetricKey::class, $key);
		$config = tempnam(sys_get_temp_dir(), 'csc-ocs-cert-');
		self::assertIsString($config);
		try {
			file_put_contents($config, "[req]\ndistinguished_name = dn\n[dn]\n[v3_signer]\nbasicConstraints = critical,CA:FALSE\nkeyUsage = critical,digitalSignature,nonRepudiation\n");
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

	/** @param array<string, mixed> $options */
	private function respondToCsc(string $url, array $options): IResponse {
		self::assertFalse($options['allow_redirects']);
		self::assertArrayNotHasKey('Authorization', $options['headers']);
		$operation = substr((string)parse_url($url, PHP_URL_PATH), strlen('/csc/v1/'));
		$body = json_decode((string)$options['body'], true, flags: JSON_THROW_ON_ERROR);
		$der = base64_decode((string)preg_replace('/-----[^-]+-----|\s+/', '', $this->certificatePem), true);
		self::assertIsString($der);
		$payload = match ($operation) {
			'info' => ['specs' => '1.0.4.0'],
			'credentials/list' => ['credentialIDs' => [self::CREDENTIAL]],
			'credentials/info' => [
				'key' => ['status' => 'enabled', 'algo' => [CscCertificateDiscovery::SHA256_RSA_OID]],
				'cert' => ['certificates' => [base64_encode($der)]],
			],
			'credentials/authorize' => ['SAD' => 'synthetic-bound-operation'],
			'signatures/signHash' => $this->signHash($body),
			default => throw new \LogicException('Unexpected CSC request ' . $operation),
		};
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn($this->timeout && $operation === 'signatures/signHash' ? 503 : 200);
		$response->method('getBody')->willReturn(json_encode($payload, JSON_THROW_ON_ERROR));
		return $response;
	}

	/** @param array<string, mixed> $body @return array<string, mixed> */
	private function signHash(array $body): array {
		$this->submitted++;
		self::assertSame('synthetic-bound-operation', $body['SAD']);
		$hash = base64_decode($body['hash'][0], true);
		self::assertIsString($hash);
		self::assertSame(32, strlen($hash));
		self::assertTrue(openssl_private_encrypt(hex2bin('3031300d060960864801650304020105000420') . $hash,
			$signature, $this->privateKey, OPENSSL_PKCS1_PADDING));
		return ['signatures' => [base64_encode($signature)]];
	}

	private function uuid(): string {
		return $this->request->getUuid();
	}

	private function controller(bool $authenticated = false, bool $csrf = false): ExternalSigningWorkflowController {
		$request = $this->createMock(IRequest::class);
		$request->method('passesCSRFCheck')->willReturn($csrf);
		$session = $this->createMock(IUserSession::class);
		$user = $authenticated ? $this->createMock(IUser::class) : null;
		$session->method('getUser')->willReturn($user);
		return new ExternalSigningWorkflowController($request, $session, $this->service,
			Server::get(LoggerInterface::class));
	}

	public function testPublicApiRoutesCompleteSyntheticGuestSigningWithoutExposingSecrets(): void {
		$controller = $this->controller();
		$available = $controller->availability($this->uuid(), 'email', self::EMAIL,
			self::TOKEN, $this->integrationId);
		self::assertSame(Http::STATUS_OK, $available->getStatus());
		self::assertCount(1, $available->getData()['credentials']);
		self::assertFalse($available->getData()['personalSignerAuthorization']);
		$started = $controller->start($this->uuid(), 'email', self::EMAIL, self::TOKEN,
			$this->integrationId, self::CREDENTIAL, 'request-attempt', 'pdf-basic');
		self::assertSame(Http::STATUS_OK, $started->getStatus());
		self::assertSame('preparing', $started->getData()['session']['status']);
		self::assertArrayNotHasKey('actorId', $started->getData()['session']);
		self::assertStringNotContainsString('BEGIN CERTIFICATE', json_encode($started->getData(), JSON_THROW_ON_ERROR));
		$status = $controller->status($this->uuid(), 'email', self::EMAIL, self::TOKEN);
		self::assertSame(Http::STATUS_OK, $status->getStatus());
		self::assertSame('preparing', $status->getData()['session']['status']);
		$completed = $controller->continue($this->uuid(), 'email', self::EMAIL, self::TOKEN,
			(string)$started->getData()['session']['id']);
		self::assertSame(Http::STATUS_OK, $completed->getStatus());
		self::assertSame('completed', $completed->getData()['session']['status']);
		self::assertSame(1, $this->submitted);
		self::assertSame(SignRequestStatus::SIGNED,
			Server::get(SignRequestMapper::class)->getById($this->request->getId())->getStatusEnum());
	}

	public function testGuestCanListOnlyOperatorPinnedIntegrationsWithoutCredentialEnumeration(): void {
		$controller = $this->controller();
		$available = $controller->availability($this->uuid(), 'email', self::EMAIL, self::TOKEN);
		self::assertSame(Http::STATUS_OK, $available->getStatus());
		self::assertCount(1, $available->getData()['integrations']);
		self::assertSame($this->integrationId, $available->getData()['integrations'][0]['id']);
		self::assertSame([], $available->getData()['credentials']);
		self::assertNull($available->getData()['integration']);
		self::assertNull($available->getData()['session']);
	}

	public function testPublicApiDoesNotDiscloseRemoteCredentialsForMissingGuestCode(): void {
		$controller = $this->controller();
		$response = $controller->availability($this->uuid(), 'email', self::EMAIL,
			'', $this->integrationId);
		self::assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		self::assertArrayNotHasKey('credentials', $response->getData());
		self::assertSame(0, $this->submitted);
	}

	public function testAuthenticatedApiRequiresCsrfBeforeSignerOrProviderWork(): void {
		$controller = $this->controller(authenticated: true, csrf: false);
		$response = $controller->availability($this->uuid(), 'email', self::EMAIL,
			self::TOKEN, $this->integrationId);
		self::assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		self::assertArrayNotHasKey('credentials', $response->getData());
		self::assertSame(0, $this->submitted);
	}

	public function testUnknownRemoteOutcomeIsAcceptedNotRetriedByPublicController(): void {
		$controller = $this->controller();
		$started = $controller->start($this->uuid(), 'email', self::EMAIL, self::TOKEN,
			$this->integrationId, self::CREDENTIAL, 'uncertain', 'pdf-basic');
		self::assertSame(Http::STATUS_OK, $started->getStatus());
		$this->timeout = true;
		$first = $controller->continue($this->uuid(), 'email', self::EMAIL, self::TOKEN,
			(string)$started->getData()['session']['id']);
		self::assertSame(Http::STATUS_ACCEPTED, $first->getStatus());
		self::assertSame('uncertain', $first->getData()['session']['status']);
		self::assertFalse($first->getData()['retryAllowed']);
		$again = $controller->continue($this->uuid(), 'email', self::EMAIL, self::TOKEN,
			(string)$started->getData()['session']['id']);
		self::assertSame(Http::STATUS_ACCEPTED, $again->getStatus());
		self::assertSame(1, $this->submitted);
	}

	/**
	 * Revoking a provider policy must be enforced even for sessions whose
	 * remote result is uncertain, without submitting the private key again.
	 */
	public function testRevokedPolicyDeniesUncertainSessionContinuationWithoutResubmission(): void {
		$started = $this->service->start($this->uuid(), 'email', self::EMAIL,
			self::TOKEN, $this->integrationId, self::CREDENTIAL, 'revoked-policy', 'pdf-basic');
		$this->timeout = true;
		$result = $this->service->continue($this->uuid(), 'email', self::EMAIL,
			self::TOKEN, (string)$started['id']);
		self::assertSame('uncertain', $result['session']['status']);
		self::assertSame(1, $this->submitted);

		$this->policyAllowed = false;
		$this->expectException(\DomainException::class);
		try {
			$this->service->continue($this->uuid(), 'email', self::EMAIL,
				self::TOKEN, (string)$started['id']);
		} finally {
			self::assertSame(1, $this->submitted, 'Revoked access must never retry the remote signature');
		}
	}

	public function testAuthorizedGuestCanDiscoverPrepareContinueAndPublishSyntheticPdf(): void {
		$discover = $this->service->availability($this->uuid(), 'email', self::EMAIL, self::TOKEN);
		self::assertCount(1, $discover['integrations']);
		self::assertSame($this->integrationId, $discover['integrations'][0]['id']);
		self::assertNull($discover['integration']);
		self::assertSame([], $discover['credentials']);
		$available = $this->service->availability($this->uuid(), 'email', self::EMAIL,
			self::TOKEN, $this->integrationId);
		self::assertCount(1, $available['credentials']);
		self::assertNull($available['session']);
		self::assertFalse($available['personalSignerAuthorization']);
		$started = $this->service->start($this->uuid(), 'email', self::EMAIL,
			self::TOKEN, $this->integrationId, self::CREDENTIAL, 'one-attempt', 'pades-baseline-b');
		self::assertSame('preparing', $started['status']);
		self::assertSame($started['id'], $this->service->status(
			$this->uuid(), 'email', self::EMAIL, self::TOKEN)['id']);
		$result = $this->service->continue($this->uuid(), 'email', self::EMAIL,
			self::TOKEN, (string)$started['id']);
		self::assertSame('completed', $result['session']['status']);
		self::assertSame('completed', $result['publication']['status']);
		self::assertSame(1, $this->submitted);
		$storedFile = Server::get(FileMapper::class)->getById($this->file->getId());
		self::assertSame($result['publication']['signedNodeId'], $storedFile->getSignedNodeId());
		self::assertSame($result['publication']['signedHash'], $storedFile->getSignedHash());
		self::assertSame(SignRequestStatus::SIGNED,
			Server::get(SignRequestMapper::class)->getById($this->request->getId())->getStatusEnum());
		$stored = Server::get(FolderService::class)->getReadableNodeById(
			$this->file->getUserId(), $storedFile->getSignedNodeId());
		self::assertInstanceOf(\OCP\Files\File::class, $stored);
		$validations = $this->validation->validateFromString($stored->getContent());
		self::assertCount(1, $validations);
		self::assertTrue($validations[0]['signatureValidation']['isValid']);
	}

	public function testUnknownRemoteOutcomeCannotAutomaticallyResubmitAcrossNewRequests(): void {
		$started = $this->service->start($this->uuid(), 'email', self::EMAIL,
			self::TOKEN, $this->integrationId, self::CREDENTIAL, 'uncertain-attempt', 'pdf-basic');
		$this->timeout = true;
		$result = $this->service->continue($this->uuid(), 'email', self::EMAIL,
			self::TOKEN, (string)$started['id']);
		self::assertSame('uncertain', $result['session']['status']);
		self::assertFalse($result['retryAllowed']);
		self::assertNull($result['publication']);
		self::assertSame(1, $this->submitted);
		$again = $this->service->continue($this->uuid(), 'email', self::EMAIL,
			self::TOKEN, (string)$started['id']);
		self::assertSame('uncertain', $again['session']['status']);
		self::assertSame(1, $this->submitted);
		self::assertSame(SignRequestStatus::ABLE_TO_SIGN,
			Server::get(SignRequestMapper::class)->getById($this->request->getId())->getStatusEnum());
	}

	public function testUnknownCredentialIsRejectedBeforePreparingOrSigning(): void {
		$this->expectException(\DomainException::class);
		try {
			$this->service->start($this->uuid(), 'email', self::EMAIL,
				self::TOKEN, $this->integrationId, 'forged-credential', 'attempt', 'pdf-basic');
		} finally {
			self::assertSame(0, $this->submitted);
			self::assertNull($this->service->status($this->uuid(), 'email', self::EMAIL, self::TOKEN));
		}
	}

	public function testUnsupportedPdfProfileIsRejectedBeforeSessionCreation(): void {
		$this->expectException(\DomainException::class);
		$this->service->start($this->uuid(), 'email', self::EMAIL,
			self::TOKEN, $this->integrationId, self::CREDENTIAL, 'attempt', 'pades-lta');
	}

	public function testSessionStatusDoesNotAuthorizeAnotherSigner(): void {
		$this->service->start($this->uuid(), 'email', self::EMAIL,
			self::TOKEN, $this->integrationId, self::CREDENTIAL, 'attempt', 'pdf-basic');
		$this->expectException(\DomainException::class);
		$this->service->status($this->uuid(), 'email', 'unrelated@example.org', self::TOKEN);
	}
}
