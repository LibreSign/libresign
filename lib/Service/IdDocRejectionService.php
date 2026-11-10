<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Notification\IdDocRejectionPayload;
use OCA\Libresign\Service\Notification\InAppChannel;
use OCA\Libresign\Service\Notification\InAppPayload;
use OCA\Libresign\Service\Notification\MailChannel;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCP\AppFramework\Db\TTransactional;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager;

final class IdDocRejectionService {
	use TTransactional;

	public function __construct(
		private IdDocsPolicyService $policy,
		private IdDocsMapper $idDocs,
		private FileMapper $files,
		private SignRequestMapper $signRequests,
		private IdentifyMethodMapper $identifyMethods,
		private IUserManager $userManager,
		private ITimeFactory $clock,
		private IL10N $l10n,
		private IDBConnection $db,
		private NotificationDeliveryService $notifications,
		private MailChannel $mail,
		private InAppChannel $inApp,
		private IManager $notificationManager,
	) {
	}

	public function reject(int $nodeId, string $reason, ?IUser $approver): void {
		if (!$this->policy->userCanApproveValidationDocuments($approver, false)) {
			throw new LibresignException($this->l10n->t('Not allowed'));
		}
		$reason = trim($reason);
		if ($reason === '' || mb_strlen($reason) > 1024) {
			// TRANSLATORS Validation error when a document rejection has no meaningful reason.
			throw new LibresignException($this->l10n->t('Provide a rejection reason of up to 1024 characters.'));
		}
		$this->atomic(function () use ($nodeId, $reason, $approver): void {
			$doc = $this->idDocs->getByNodeId($nodeId);
			$file = $this->files->getById($doc->getFileId());
			if (!$this->policy->canApproverSignIdDoc($approver, $file->getId(), $file->getStatus())) {
				throw new LibresignException($this->l10n->t('Not allowed'));
			}
			if ($doc->getRejectedAt() !== null
				|| !in_array($file->getStatus(), [FileStatus::ABLE_TO_SIGN->value, FileStatus::PARTIAL_SIGNED->value], true)) {
				// TRANSLATORS Rejection is only allowed before an identification document is fully approved.
				throw new LibresignException($this->l10n->t('This document is no longer awaiting approval.'));
			}
			if (!$this->idDocs->rejectIfPending(
				$doc,
				$approver->getUID(),
				$reason,
				\DateTime::createFromInterface($this->clock->getDateTime()),
			)) {
				throw new LibresignException($this->l10n->t('This document has already been rejected.'));
			}
			$this->registerNotification($doc->getUserId(), $doc->getSignRequestId(), $doc->getId(), $file, $reason);
		}, $this->db);
		$this->notifications->flushCommitted();
	}

	private function registerNotification(?string $userId, ?int $signRequestId, int $documentId, \OCA\Libresign\Db\File $file, string $reason): void {
		if ($signRequestId === null) {
			return;
		}
		$signRequest = $this->signRequests->getById($signRequestId);
		$recipients = [];
		if ($userId !== null) {
			$recipients[] = ['uid' => $userId, 'email' => $this->userManager->get($userId)?->getEMailAddress()];
		} else {
			foreach ($this->identifyMethods->getIdentifyMethodsFromSignRequestId($signRequestId) as $method) {
				$key = $method->getIdentifierKey();
				if ($key === 'email') {
					$recipients[] = ['uid' => null, 'email' => $method->getIdentifierValue()];
				} elseif ($key === 'account') {
					$uid = $method->getIdentifierValue();
					$recipients[] = ['uid' => $uid, 'email' => $this->userManager->get($uid)?->getEMailAddress()];
				}
			}
		}
		foreach ($recipients as $recipient) {
			$eventId = 'id-doc-rejected:' . $documentId;
			$email = $recipient['email'];
			if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$this->notifications->dispatch(new NotificationMessage(
					$eventId, 'id_doc_rejected', 'mail', $file, $signRequest, $email,
					new IdDocRejectionPayload($reason, $userId !== null),
				), $this->mail);
			}
			$uid = $recipient['uid'];
			if (!is_string($uid) || $uid === '') {
				continue;
			}
			$notification = $this->notificationManager->createNotification()
				->setApp(Application::APP_ID)
				->setUser($uid)
				->setObject('identificationDocument', (string)$documentId)
				->setDateTime($this->clock->getDateTime())
				->setSubject('identification_document_rejected', [
					'reason' => $reason,
					'documentName' => $file->getName(),
				]);
			$this->notifications->dispatch(new NotificationMessage(
				$eventId, 'id_doc_rejected', 'nextcloud', $file, $signRequest, $uid,
				new InAppPayload($notification),
			), $this->inApp);
		}
	}
}
