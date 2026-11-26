<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Signing\External;

use OCA\Libresign\Controller\ExternalSigningWorkflowController;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\Signature\PdfSignatureValidationService;
use OCA\Libresign\Service\Signing\External\Workflow;
use OCA\Libresign\Tests\Integration\Service\Signing\External\Fixtures\SigningRequestFixture;
use OCA\Libresign\Tests\Integration\Service\Signing\External\Fixtures\SyntheticCscResponder;
use OCA\Libresign\Tests\Integration\Service\Signing\External\Fixtures\WorkflowServices;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Server;
use Psr\Log\LoggerInterface;

/**
 * @group DB
 *
 * A disposable Nextcloud with real SQLite migration, filesystem and
 * published SignRequest. The OCP HTTP client is deterministic and never
 * contacts a real CSC provider, but SignerPHP signs actual prepared digests.
 */
final class WorkflowTest extends \OCA\Libresign\Tests\Integration\TestCase {
	private const EMAIL = 'authorized@example.org';
	private const TOKEN = 'verified-synthetic-code';
	private const CREDENTIAL = SyntheticCscResponder::CREDENTIAL;

	private Workflow $service;
	private PdfSignatureValidationService $validation;
	private SignRequest $request;
	private FileEntity $file;
	private string $integrationId;
	private bool $policyAllowed = true;
	private SyntheticCscResponder $csc;
	private WorkflowServices $wiring;

	#[\Override]
	public function setUp(): void {
		parent::setUp();
		$mock = fn (string $class) => $this->createMock($class);
		$this->csc = new SyntheticCscResponder($mock);
		$document = SigningRequestFixture::create($this);
		$this->file = $document->file;
		$this->request = $document->request;
		$this->wiring = WorkflowServices::create($mock, Server::get(IDBConnection::class),
			$this->csc, self::EMAIL, fn (): bool => $this->policyAllowed);
		$this->service = $this->wiring->workflow;
		$this->validation = $this->wiring->validation;
		$this->integrationId = $this->wiring->integrationId;
	}

	#[\Override]
	public function tearDown(): void {
		try {
			if (isset($this->wiring, $this->request)) {
				$this->wiring->cleanup($this->request->getId());
			}
		} finally {
			parent::tearDown();
		}
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
		self::assertSame(1, $this->csc->submissions());
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
		self::assertSame(0, $this->csc->submissions());
	}

	public function testAuthenticatedApiRequiresCsrfBeforeSignerOrProviderWork(): void {
		$controller = $this->controller(authenticated: true, csrf: false);
		$response = $controller->availability($this->uuid(), 'email', self::EMAIL,
			self::TOKEN, $this->integrationId);
		self::assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		self::assertArrayNotHasKey('credentials', $response->getData());
		self::assertSame(0, $this->csc->submissions());
	}

	public function testUnknownRemoteOutcomeIsAcceptedNotRetriedByPublicController(): void {
		$controller = $this->controller();
		$started = $controller->start($this->uuid(), 'email', self::EMAIL, self::TOKEN,
			$this->integrationId, self::CREDENTIAL, 'uncertain', 'pdf-basic');
		self::assertSame(Http::STATUS_OK, $started->getStatus());
		$this->csc->simulateUnknownSigningOutcome();
		$first = $controller->continue($this->uuid(), 'email', self::EMAIL, self::TOKEN,
			(string)$started->getData()['session']['id']);
		self::assertSame(Http::STATUS_ACCEPTED, $first->getStatus());
		self::assertSame('uncertain', $first->getData()['session']['status']);
		self::assertFalse($first->getData()['retryAllowed']);
		$again = $controller->continue($this->uuid(), 'email', self::EMAIL, self::TOKEN,
			(string)$started->getData()['session']['id']);
		self::assertSame(Http::STATUS_ACCEPTED, $again->getStatus());
		self::assertSame(1, $this->csc->submissions());
	}

	/**
	 * Revoking a provider policy must be enforced even for sessions whose
	 * remote result is uncertain, without submitting the private key again.
	 */
	public function testRevokedPolicyDeniesUncertainSessionContinuationWithoutResubmission(): void {
		$started = $this->service->start($this->uuid(), 'email', self::EMAIL,
			self::TOKEN, $this->integrationId, self::CREDENTIAL, 'revoked-policy', 'pdf-basic');
		$this->csc->simulateUnknownSigningOutcome();
		$result = $this->service->continue($this->uuid(), 'email', self::EMAIL,
			self::TOKEN, (string)$started['id']);
		self::assertSame('uncertain', $result['session']['status']);
		self::assertSame(1, $this->csc->submissions());

		$this->policyAllowed = false;
		$this->expectException(\DomainException::class);
		try {
			$this->service->continue($this->uuid(), 'email', self::EMAIL,
				self::TOKEN, (string)$started['id']);
		} finally {
			self::assertSame(1, $this->csc->submissions(), 'Revoked access must never retry the remote signature');
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
		self::assertSame(1, $this->csc->submissions());
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

	/**
	 * A received and reserved signature must publish without re-contacting CSC.
	 * The raw hash signature has already been submitted exactly once.
	 */
	public function testReservedPublicationSurvivesCscDiscoveryOutage(): void {
		$started = $this->service->start($this->uuid(), 'email', self::EMAIL,
			self::TOKEN, $this->integrationId, self::CREDENTIAL, 'recover-without-csc', 'pdf-basic');
		$lab = $this->wiring->laboratories->resolve($this->integrationId);
		$source = $this->wiring->documents->get($this->file);
		$this->wiring->operation->sign($lab['laboratory'], $lab['record'], (string)$started['id'],
			$this->request->getId(), 'sign-request:' . $this->request->getId(),
			$source->revision, $source->pdf);
		self::assertSame('completing', $this->service->status(
			$this->uuid(), 'email', self::EMAIL, self::TOKEN)['status']);
		self::assertSame(1, $this->csc->submissions());

		$this->csc->makeDiscoveryUnavailable();
		$result = $this->service->continue($this->uuid(), 'email', self::EMAIL,
			self::TOKEN, (string)$started['id']);
		self::assertSame('completed', $result['session']['status']);
		self::assertSame(1, $this->csc->submissions(), 'Publication must never resubmit signHash');
		$published = Server::get(FileMapper::class)->getById($this->file->getId());
		$validations = $this->validation->validateFromString(
			Server::get(\OCA\Libresign\Service\FolderService::class)->getReadableNodeById(
				$this->file->getUserId(), $published->getSignedNodeId())->getContent());
		self::assertCount(1, $validations);
		self::assertTrue($validations[0]['signatureValidation']['isValid']);
	}

	public function testRevokedPolicyStillBlocksReservedPublicationDuringProviderOutage(): void {
		$started = $this->service->start($this->uuid(), 'email', self::EMAIL,
			self::TOKEN, $this->integrationId, self::CREDENTIAL, 'revoke-before-publishing', 'pdf-basic');
		$lab = $this->wiring->laboratories->resolve($this->integrationId);
		$source = $this->wiring->documents->get($this->file);
		$this->wiring->operation->sign($lab['laboratory'], $lab['record'], (string)$started['id'],
			$this->request->getId(), 'sign-request:' . $this->request->getId(),
			$source->revision, $source->pdf);
		$this->csc->makeDiscoveryUnavailable();
		$this->policyAllowed = false;
		$this->expectException(\DomainException::class);
		try {
			$this->service->continue($this->uuid(), 'email', self::EMAIL,
				self::TOKEN, (string)$started['id']);
		} finally {
			self::assertSame(1, $this->csc->submissions());
			self::assertSame(SignRequestStatus::ABLE_TO_SIGN,
				Server::get(SignRequestMapper::class)->getById($this->request->getId())->getStatusEnum());
		}
	}

	public function testUnknownRemoteOutcomeCannotAutomaticallyResubmitAcrossNewRequests(): void {
		$started = $this->service->start($this->uuid(), 'email', self::EMAIL,
			self::TOKEN, $this->integrationId, self::CREDENTIAL, 'uncertain-attempt', 'pdf-basic');
		$this->csc->simulateUnknownSigningOutcome();
		$result = $this->service->continue($this->uuid(), 'email', self::EMAIL,
			self::TOKEN, (string)$started['id']);
		self::assertSame('uncertain', $result['session']['status']);
		self::assertFalse($result['retryAllowed']);
		self::assertNull($result['publication']);
		self::assertSame(1, $this->csc->submissions());
		$again = $this->service->continue($this->uuid(), 'email', self::EMAIL,
			self::TOKEN, (string)$started['id']);
		self::assertSame('uncertain', $again['session']['status']);
		self::assertSame(1, $this->csc->submissions());
		self::assertSame(SignRequestStatus::ABLE_TO_SIGN,
			Server::get(SignRequestMapper::class)->getById($this->request->getId())->getStatusEnum());
	}

	public function testUnknownCredentialIsRejectedBeforePreparingOrSigning(): void {
		$this->expectException(\DomainException::class);
		try {
			$this->service->start($this->uuid(), 'email', self::EMAIL,
				self::TOKEN, $this->integrationId, 'forged-credential', 'attempt', 'pdf-basic');
		} finally {
			self::assertSame(0, $this->csc->submissions());
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
