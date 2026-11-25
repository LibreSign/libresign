<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

use OCA\Libresign\Service\Signing\External\AuthorizedRequest;
use OCA\Libresign\Service\Signing\External\SessionState;
use DateTime;
use DateTimeZone;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Events\SignedEventFactory;
use OCA\Libresign\Service\File\SigningWorkflowStatusResolver;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\SequentialSigningService;
use OCA\Libresign\Service\Webhook\NativeWebhookEventPublisher;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\File as NextcloudFile;
use OCP\IDBConnection;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Reconcile the synthetic CSC PDF with the actual LibreSign workflow.
 *
 * A signed artifact is immutable and identified by session. An interrupted
 * publication may leave that artifact in the owner's folder; its exact hash
 * is checked before reuse. Older signed revisions are never overwritten.
 *
 * The SignRequest state, file pointer/status, next signer activation and
 * durable CSC COMPLETED transition share a database transaction. Event
 * dispatch occurs only after commit, following LibreSign's existing best-
 * effort delivery model (which does not promise exactly-once webhooks).
 *
 * The caller MUST obtain AuthorizedRequest immediately before
 * calling this service, and the certificate PEM MUST come from trusted CSC
 * discovery, never a browser-supplied parameter. No private key is used.
 */
final class ExternalSigningPublicationService {
	use TTransactional;
	public function __construct(
		private IDBConnection $db,
		private ExternalSigningSessionStore $sessions,
		private SyntheticCscSigningOperation $signing,
		private FileMapper $files,
		private SignRequestMapper $signRequests,
		private FolderService $folders,
		private FileStatusService $fileStatuses,
		private SigningWorkflowStatusResolver $statusResolver,
		private SequentialSigningService $sequential,
		private NotificationDeliveryMapper $notificationDeliveries,
		private SignedEventFactory $signedEventFactory,
		private IEventDispatcher $dispatcher,
		private NativeWebhookEventPublisher $webhooks,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Reconcile one already-validated CSC signature without submitting signHash.
	 * A trusted caller must recheck current signer authorization first.
	 *
	 * @return array{status:string,sessionId:string,signedNodeId:int,signedHash:string}
	 */
	public function publish(
		AuthorizedRequest $authorized,
		string $sessionId,
		string $expectedCertificatePem,
	): array {
		$request = $authorized->signRequest;
		$requestId = $request->getId();
		$fileId = $authorized->file->getId();
		if ($requestId === null || $requestId < 1 || $fileId === null || $fileId < 1
			|| $request->getFileId() !== $fileId) {
			throw new \DomainException('CSC signing request context is invalid');
		}
		$session = $this->sessions->getForSignRequest($sessionId, $requestId, $authorized->actorId);
		if ($session['status'] !== SessionState::COMPLETING->value) {
			throw new \DomainException('CSC signature has not reserved publication');
		}

		// Resolve the source exclusively from current LibreSign storage. Never
		// accept a source PDF, document revision or resulting PDF from the UI.
		$file = $this->files->getById($fileId);
		$sourceNodeId = $file->getSignedNodeId() ?: $file->getNodeId();
		if ($sourceNodeId === null || $sourceNodeId < 1) {
			throw new \DomainException('Authoritative PDF source is missing');
		}
		$storageUid = $this->files->getStorageUserIdByUuid($file->getUuid());
		$source = $this->folders->getReadableNodeById($storageUid, $sourceNodeId);
		if (!$source instanceof NextcloudFile) {
			throw new \DomainException('Authoritative PDF source cannot be read');
		}
		$sourceRevision = $source->getEtag();
		$pdfBytes = $source->getContent();
		if ($sourceRevision === '' || !str_starts_with($pdfBytes, '%PDF-')) {
			throw new \DomainException('Authoritative PDF source is invalid');
		}
		$validatedPdf = $this->signing->reconstructForReconciliation(
			$sessionId, $requestId, $authorized->actorId,
			$sourceRevision, $pdfBytes, $expectedCertificatePem,
		);
		$hash = hash('sha256', $validatedPdf);
		$output = $this->ensureImmutableOutput($source, $sessionId, $fileId, $requestId, $validatedPdf, $hash);
		if ($source->getEtag() !== $sourceRevision || !hash_equals(hash('sha256', $pdfBytes), hash('sha256', $source->getContent()))) {
			throw new \DomainException('PDF source changed before CSC publication');
		}

		$previousStatus = $file->getStatus();
		$activated = [];
		$locked = null;
		$this->atomic(function () use ($fileId, $file, $source, $sourceRevision, $requestId, $request, $hash, $output, $sessionId, $authorized, $session, $pdfBytes, $expectedCertificatePem, &$locked, &$activated): void {
			$locked = $this->files->getByIdForUpdate($fileId);
			if ($locked->getSignedNodeId() !== $file->getSignedNodeId()
				|| $locked->getStatus() === FileStatus::SIGNED->value
				|| $locked->getNodeId() !== $file->getNodeId()) {
				throw new \DomainException('Document revision or workflow changed during publication');
			}
			if ($source->getEtag() !== $sourceRevision) {
				throw new \DomainException('Document changed during CSC publication');
			}
			$now = new DateTime('now', new DateTimeZone('UTC'));
			$qb = $this->db->getQueryBuilder();
			$claimed = $qb->update('libresign_sign_request')
				->set('signed', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_MUTABLE))
				->set('signed_hash', $qb->createNamedParameter($hash))
				->set('status', $qb->createNamedParameter(SignRequestStatus::SIGNED->value, IQueryBuilder::PARAM_INT))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($requestId, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(SignRequestStatus::ABLE_TO_SIGN->value, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->isNull('signed'))
				->andWhere($qb->expr()->isNull('signed_hash'))
				->executeStatement();
			if ($claimed !== 1) {
				throw new \DomainException('Signing request already completed or changed concurrently');
			}
			$this->storeSignerCertificate($requestId, $expectedCertificatePem, $sessionId);
			$request->setSigned($now);
			$request->setSignedHash($hash);
			$request->setStatusEnum(SignRequestStatus::SIGNED);
			$locked->setSignedNodeId($output->getId());
			$locked->setSignedHash($hash);
			$signers = $this->signRequests->getByFileId($fileId);
			foreach ($signers as $index => $signer) {
				if ($signer->getId() === $requestId) {
					$signers[$index] = $request;
				}
			}
			$newStatus = $this->statusResolver->resolve($locked, $signers);
			if ($newStatus !== null) {
				$locked->setStatus($newStatus->value);
			}
			$this->fileStatuses->updateForQueuedJob($locked);
			$this->notificationDeliveries->cancelPendingInvitations($requestId);
			$this->sequential->setFile($locked);
			$activated = $this->sequential->activateNextOrder($fileId, $request->getSigningOrder());
			$this->sessions->markCompleted($sessionId, $authorized->actorId,
				(int)$session['revision'], $sourceRevision, $pdfBytes, $expectedCertificatePem);
		}, $this->db);

		try {
			$this->fileStatuses->onQueuedJobCommitted($locked);
			$this->signRequests->invalidateEntityCache($request);
			$this->sequential->notifyActivatedSigners($activated);
			$this->dispatcher->dispatchTyped($this->signedEventFactory->make(
				$request, $locked, $output,
			));
			$this->webhooks->signerSigned($locked, $request);
			$this->webhooks->requestCompleted($locked, $previousStatus);
		} catch (\Throwable $e) {
			$this->logger->error('CSC signing committed but a post-commit event failed', [
				'exception' => $e, 'signRequestId' => $requestId,
			]);
		}
		return [
			'status' => 'completed',
			'sessionId' => $sessionId,
			'signedNodeId' => $output->getId(),
			'signedHash' => $hash,
		];
	}

	private function ensureImmutableOutput(
		NextcloudFile $source,
		string $sessionId,
		int $fileId,
		int $requestId,
		#[\SensitiveParameter] string $content,
		string $hash,
	): NextcloudFile {
		if (preg_match('/^[a-f0-9]{32}$/D', $sessionId) !== 1) {
			throw new \DomainException('Invalid CSC publication session identifier');
		}
		$name = substr(pathinfo($source->getName(), PATHINFO_FILENAME), 0, 80);
		$filename = $name . '_signed_' . $fileId . '_' . $requestId . '_' . substr($sessionId, 0, 12) . '.pdf';
		$folder = $source->getParent();
		if ($folder->nodeExists($filename)) {
			$existing = $folder->get($filename);
			if (!$existing instanceof NextcloudFile || !hash_equals($hash, hash('sha256', $existing->getContent()))) {
				throw new \DomainException('Existing CSC publication artifact does not match the validated PDF');
			}
			return $existing;
		}
		$owner = $source->getOwner();
		$currentUser = $this->userSession->getUser();
		if ($currentUser?->getUID() !== $owner->getUID()) {
			$this->userSession->setVolatileActiveUser($owner);
		}
		try {
			$created = $folder->newFile($filename, $content);
		} finally {
			if ($currentUser?->getUID() !== $owner->getUID()) {
				$this->userSession->setVolatileActiveUser($currentUser);
			}
		}
		if (!hash_equals($hash, hash('sha256', $created->getContent()))) {
			throw new \DomainException('New CSC publication artifact failed integrity verification');
		}
		return $created;
	}

	/** Preserve all concurrent signer metadata, never overwrite its JSON. */
	private function storeSignerCertificate(int $requestId, string $pem, string $sessionId): void {
		$certificate = @openssl_x509_parse($pem);
		if (!is_array($certificate)) {
			throw new \DomainException('Invalid CSC signing certificate');
		}
		$qb = $this->db->getQueryBuilder();
		$raw = $qb->select('metadata')->from('libresign_sign_request')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($requestId, IQueryBuilder::PARAM_INT)))
			->executeQuery()->fetchOne();
		$metadata = $raw === null || $raw === false || $raw === '' ? [] : json_decode((string)$raw, true, 64, JSON_THROW_ON_ERROR);
		if (!is_array($metadata) || array_is_list($metadata) && $metadata !== []) {
			throw new \DomainException('Malformed signer metadata');
		}
		$metadata['certificate_info'] = [
			'serialNumberHex' => $certificate['serialNumberHex'] ?? '',
			'subject' => $certificate['subject'] ?? [],
			'hash' => openssl_x509_fingerprint($pem, 'sha256'),
		];
		$metadata['external_signing'] = ['sessionId' => $sessionId, 'driver' => 'csc-v1', 'laboratory' => true];
		$qb = $this->db->getQueryBuilder();
		$qb->update('libresign_sign_request')
			->set('metadata', $qb->createNamedParameter(json_encode($metadata, JSON_THROW_ON_ERROR)))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($requestId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
