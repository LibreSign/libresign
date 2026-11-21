<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCP\AppFramework\Db\TTransactional;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUser;

final class IdDocRejectionService {
	use TTransactional;

	public function __construct(
		private IdDocsPolicyService $policy,
		private IdDocsMapper $idDocs,
		private FileMapper $files,
		private ITimeFactory $clock,
		private IL10N $l10n,
		private IDBConnection $db,
		private IdDocRejectionNotifier $notifier,
	) {
	}

	public function reject(int $nodeId, string $reason, ?IUser $approver): void {
		if (!$this->policy->userCanApproveValidationDocuments($approver, false)) {
			// TRANSLATORS Authorization error when the current user cannot review identification documents.
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
				// TRANSLATORS Authorization error when the reviewer cannot approve or reject this identification document.
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
				// TRANSLATORS Conflict error when another reviewer has already rejected this identification document.
				throw new LibresignException($this->l10n->t('This document has already been rejected.'));
			}
			$this->notifier->queue($doc, $file, $reason);
		}, $this->db);
		$this->notifier->flushCommitted();
	}

}
