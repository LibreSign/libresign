<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Signing\External\Fixtures;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\SignRequestMapper;
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
use OCA\Libresign\Service\Signing\External\Authorization;
use OCA\Libresign\Service\Signing\External\CredentialSelection;
use OCA\Libresign\Service\Signing\External\DocumentSource;
use OCA\Libresign\Service\Signing\External\ImmutableArtifactStore;
use OCA\Libresign\Service\Signing\External\IntegrationScopeAccess;
use OCA\Libresign\Service\Signing\External\LaboratoryFactory;
use OCA\Libresign\Service\Signing\External\Publication;
use OCA\Libresign\Service\Signing\External\PublicationCommitter;
use OCA\Libresign\Service\Signing\External\PublicationEvents;
use OCA\Libresign\Service\Signing\External\PublicationPreparer;
use OCA\Libresign\Service\Signing\External\SessionStore;
use OCA\Libresign\Service\Signing\External\Workflow;
use OCA\Libresign\Service\Signing\ExternalIntegrationAvailability;
use OCA\Libresign\Service\Signing\ExternalIntegrationStore;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscDriver;
use OCA\Libresign\Service\Signing\SyntheticCscSigningOperation;
use OCA\Libresign\Service\Validation\SignerValidator;
use OCA\Libresign\Service\Validation\SigningRequestValidator;
use OCA\Libresign\Service\Webhook\NativeWebhookEventPublisher;
use OCA\Libresign\Tests\Integration\TestCase;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Group\ISubAdmin;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Security\ICrypto;
use OCP\Server;
use Psr\Log\LoggerInterface;

/**
 * Test-only composition root: real DB, PDF, publication and session services.
 * Signer verification and policy are controlled doubles, covered separately
 * by the authorization and policy tests.
 */
final readonly class WorkflowServices {
	private function __construct(
		public Workflow $workflow,
		public SessionStore $sessions,
		public ExternalIntegrationStore $integrations,
		public LaboratoryFactory $laboratories,
		public SyntheticCscSigningOperation $operation,
		public PdfSignatureValidationService $validation,
		public DocumentSource $documents,
		public string $integrationId,
		private IDBConnection $db,
	) {
	}

	/** @param callable():bool $isPolicyAllowed */
	public static function create(\Closure $mock, IDBConnection $db, SyntheticCscResponder $csc, string $email, callable $isPolicyAllowed): self {
		$integrations = Server::get(ExternalIntegrationStore::class);
		$record = $integrations->create(CscDriver::ID, 'Laboratory only', ['baseUrl' => SyntheticCscResponder::URL], []);
		$record = $integrations->setEnabled($record->id, $record->revision, true);
		$features = $mock(ExperimentalFeaturesService::class);
		$features->method('isEnabled')->willReturn(true);
		$sessions = new SessionStore($db, Server::get(ICrypto::class), $features, $integrations);
		$authorization = self::authorization($mock, $features, $email);
		$availability = new ExternalIntegrationAvailability($features);
		$config = $mock(IConfig::class);
		$config->method('getSystemValueBool')->willReturnCallback(static fn (string $name, bool $default = false): bool => $default);
		$fingerprint = openssl_x509_fingerprint($csc->certificatePem, 'sha256');
		TestCase::assertIsString($fingerprint);
		$config->method('getSystemValueString')->willReturnCallback(
			static fn (string $name, string $default = ''): string => match ($name) {
				'libresign_csc_synthetic_lab_url' => SyntheticCscResponder::URL,
				'libresign_csc_synthetic_lab_certificate_sha256' => $fingerprint,
				default => $default,
			});
		$client = $mock(IClient::class);
		$client->method('post')->willReturnCallback($csc->respond(...));
		$clientService = $mock(IClientService::class);
		$clientService->method('newClient')->willReturn($client);
		$laboratories = new LaboratoryFactory($integrations, $availability,
			Server::get(CscDriver::class), $clientService, $config);
		$selection = new CredentialSelection($authorization, $integrations, $availability, Server::get(CscDriver::class));
		$l10n = Server::get(IFactory::class)->get('libresign');
		$validation = new PdfSignatureValidationService(TestCase::getMockAppConfigWithReset(), $l10n,
			Server::get(LoggerInterface::class));
		$operation = new SyntheticCscSigningOperation(new ExternalPdfSigningService($validation,
			new DocMdpHandler($l10n)), $sessions);
		$documents = new DocumentSource(Server::get(FileMapper::class), Server::get(FolderService::class));
		$workflow = new Workflow($authorization, $laboratories, $selection, $sessions, $operation,
			self::publication($db, $sessions, $operation, $documents), $documents,
			self::scopeAccess($mock, $isPolicyAllowed));
		return new self($workflow, $sessions, $integrations, $laboratories, $operation, $validation,
			$documents, $record->id, $db);
	}

	private static function authorization(\Closure $mock, ExperimentalFeaturesService $features, string $email): Authorization {
		$identity = new IdentifyMethod();
		$identity->setIdentifierKey('email');
		$identity->setIdentifierValue($email);
		$method = $mock(IIdentifyMethod::class);
		$method->method('getEntity')->willReturn($identity);
		$methods = $mock(IdentifyMethodService::class);
		$methods->method('getIdentifyMethodsFromSignRequestId')->willReturn(['email' => [$method]]);
		$session = $mock(IUserSession::class);
		$session->method('getUser')->willReturn(null);
		return new Authorization($features, Server::get(SignRequestMapper::class), Server::get(FileMapper::class),
			$mock(SignerValidator::class), $mock(SigningRequestValidator::class),
			$methods, $session);
	}

	/** @param callable():bool $isPolicyAllowed */
	private static function scopeAccess(\Closure $mock, callable $isPolicyAllowed): IntegrationScopeAccess {
		$policy = $mock(PolicyService::class);
		$policy->method('resolveForUserId')->willReturnCallback(
			static fn (): ResolvedPolicy => (new ResolvedPolicy())->setEffectiveValue($isPolicyAllowed()),
		);
		return new IntegrationScopeAccess($policy, Server::get(IGroupManager::class),
			Server::get(IUserManager::class), Server::get(IUserSession::class), Server::get(ISubAdmin::class));
	}

	private static function publication(IDBConnection $db, SessionStore $sessions,
		SyntheticCscSigningOperation $operation, DocumentSource $documents): Publication {
		return new Publication(
			new PublicationPreparer($sessions, $operation, Server::get(FileMapper::class), $documents,
				new ImmutableArtifactStore(Server::get(IUserSession::class))),
			new PublicationCommitter($db, $sessions, Server::get(FileMapper::class),
				Server::get(SignRequestMapper::class), Server::get(FileStatusService::class),
				Server::get(SigningWorkflowStatusResolver::class), Server::get(SequentialSigningService::class),
				Server::get(\OCA\Libresign\Db\NotificationDeliveryMapper::class)),
			new PublicationEvents(Server::get(FileStatusService::class), Server::get(SignRequestMapper::class),
				Server::get(SequentialSigningService::class), Server::get(SignedEventFactory::class),
				Server::get(IEventDispatcher::class), Server::get(NativeWebhookEventPublisher::class),
				Server::get(LoggerInterface::class)),
		);
	}

	/** Remove only this test's CSC rows before parent TestCase cleans native data. */
	public function cleanup(int $signRequestId): void {
		$qb = $this->db->getQueryBuilder();
		$ids = $qb->select('public_id')->from('libresign_ext_sign_session')
			->where($qb->expr()->eq('sign_request_id', $qb->createNamedParameter($signRequestId)))
			->executeQuery()->fetchFirstColumn();
		if ($ids !== []) {
			foreach (['libresign_ext_sign_private', 'libresign_ext_sign_attempt', 'libresign_ext_sign_audit'] as $table) {
				$qb = $this->db->getQueryBuilder();
				$qb->delete($table)->where($qb->expr()->in('session_id',
					$qb->createNamedParameter($ids, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_STR_ARRAY)))
					->executeStatement();
			}
		}
		$qb = $this->db->getQueryBuilder();
		$qb->delete('libresign_ext_sign_session')->where($qb->expr()->eq('sign_request_id',
			$qb->createNamedParameter($signRequestId)))->executeStatement();
		$qb = $this->db->getQueryBuilder();
		$qb->delete('libresign_signing_integration')->where($qb->expr()->eq('public_id',
			$qb->createNamedParameter($this->integrationId)))->executeStatement();
	}
}
