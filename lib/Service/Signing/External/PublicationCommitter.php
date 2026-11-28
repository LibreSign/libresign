<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\External;

use DateTime;
use DateTimeZone;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\File\SigningWorkflowStatusResolver;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\SequentialSigningService;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Commit the signed request, document pointer, next-signer state and durable
 * CSC session completion in one database transaction.
 */
final class PublicationCommitter implements PublicationTransaction {
	use TTransactional;

	public function __construct(
		private IDBConnection $db,
		private SessionStore $sessions,
		private FileMapper $files,
		private SignRequestMapper $signRequests,
		private FileStatusService $fileStatuses,
		private SigningWorkflowStatusResolver $statusResolver,
		private SequentialSigningService $sequential,
		private NotificationDeliveryMapper $notificationDeliveries,
	) {
	}

	/** @return array{file:FileEntity,activated:list<SignRequest>,previousStatus:int} */
	#[\Override]
	public function commit(PublicationContext $context): array {
		$previousStatus = $context->file->getStatus();
		// Never expose an uncommitted SIGNED state through a cached request entity.
		$signedRequest = clone $context->authorized->signRequest;
		$committed = $this->atomic(function () use ($context, $previousStatus, $signedRequest): array {
			$request = $signedRequest;
			$requestId = (int)$request->getId();
			$fileId = (int)$context->file->getId();
			$locked = $this->files->getByIdForUpdate($fileId);
			$this->assertCurrentSource($locked, $context);
			$now = new DateTime('now', new DateTimeZone('UTC'));
			$this->claimSignRequest($requestId, $fileId, $context->signedHash, $now);
			$this->storeSignerCertificate($requestId, $context->certificatePem, $context->sessionId);
			$request->setSigned($now);
			$request->setSignedHash($context->signedHash);
			$request->setStatusEnum(SignRequestStatus::SIGNED);
			$activated = $this->advanceSigners($locked, $request, $context);
			$this->sessions->markCompleted(
				$context->sessionId, $context->authorized->actorId,
				$context->sessionRevision, $context->document->revision,
				$context->document->pdf, $context->certificatePem,
			);
			return [
				'file' => $locked,
				'activated' => $activated,
				'previousStatus' => $previousStatus,
				'signedAt' => $now,
			];
		}, $this->db);
		$request = $context->authorized->signRequest;
		$request->setSigned($committed['signedAt']);
		$request->setSignedHash($context->signedHash);
		$request->setStatusEnum(SignRequestStatus::SIGNED);
		unset($committed['signedAt']);
		return $committed;
	}

	private function assertCurrentSource(FileEntity $locked, PublicationContext $context): void {
		if ($locked->getSignedNodeId() !== $context->file->getSignedNodeId()
			|| $locked->getStatus() === FileStatus::SIGNED->value
			|| $locked->getNodeId() !== $context->file->getNodeId()) {
			throw new \DomainException('Document revision or workflow changed during publication');
		}
		if ($context->document->node->getEtag() !== $context->document->revision) {
			throw new \DomainException('Document changed during CSC publication');
		}
	}

	private function claimSignRequest(int $requestId, int $fileId, string $hash, DateTime $now): void {
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
	}

	/** @return list<SignRequest> */
	private function advanceSigners(FileEntity $locked, SignRequest $request, PublicationContext $context): array {
		$locked->setSignedNodeId($context->output->getId());
		$locked->setSignedHash($context->signedHash);
		$fileId = (int)$locked->getId();
		$signers = $this->signRequests->getByFileId($fileId);
		foreach ($signers as $index => $signer) {
			if ($signer->getId() === $request->getId()) {
				$signers[$index] = $request;
			}
		}
		$newStatus = $this->statusResolver->resolve($locked, $signers);
		if ($newStatus !== null) {
			$locked->setStatus($newStatus->value);
		}
		$this->fileStatuses->updateForQueuedJob($locked);
		$this->notificationDeliveries->cancelPendingInvitations((int)$request->getId());
		$this->sequential->setFile($locked);
		return $this->sequential->activateNextOrder($fileId, $request->getSigningOrder());
	}

	/** Keep concurrent signer metadata; do not overwrite the request's JSON snapshot. */
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
		if (!is_array($metadata) || (array_is_list($metadata) && $metadata !== [])) {
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
