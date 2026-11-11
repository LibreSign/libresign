<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignatureRejection;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Exception\LibresignException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class SignatureRejectionPersistence {
	public function __construct(
		private SignRequestMapper $signRequestMapper,
		private FileMapper $fileMapper,
		private SignatureRejectionWorkflow $workflow,
		private IDBConnection $db,
		private ITimeFactory $timeFactory,
		private IL10N $l10n,
		private LoggerInterface $logger,
	) {
	}

	public function commit(
		FileEntity $libreSignFile,
		SignRequestEntity $signRequest,
		RejectionInput $input,
	): CommittedRejection {
		$previousRootStatus = $libreSignFile->hasParent()
			? $this->fileMapper->getById($libreSignFile->getParentFileId())->getStatus()
			: $libreSignFile->getStatus();
		$signRequests = $this->workflow->collectSignRequestsToReject($libreSignFile, $signRequest);
		$previousSignerStatuses = array_map(
			static fn (SignRequestEntity $each): int => $each->getStatus(),
			$signRequests,
		);
		$previousFileStatus = $libreSignFile->getStatus();
		$rejectedAt = $this->timeFactory->getDateTime();

		$this->db->beginTransaction();
		try {
			foreach ($signRequests as $each) {
				$each->setStatusEnum(SignRequestStatus::REJECTED);
				$each->setRejectedAt($rejectedAt);
				$each->setRejectionComment($input->comment);
				$each->setRejectionCommentPrivate($input->commentIsPrivate);
				$this->signRequestMapper->update($each);
			}

			if ($input->cancelsWorkflow) {
				$this->workflow->cancelWorkflow($libreSignFile);
				$activatedSigners = [];
			} else {
				$activatedSigners = $this->workflow->continueWorkflow($libreSignFile, $signRequests);
			}

			$this->db->commit();
		} catch (\Exception $e) {
			$this->rollBackRejection($libreSignFile, $signRequests, $previousSignerStatuses, $previousFileStatus);
			$this->logger->error('Failed to register the signature rejection', [
				'errorType' => get_debug_type($e),
				'errorFile' => $e->getFile(),
				'errorLine' => $e->getLine(),
				'signRequestId' => $signRequest->getId(),
			]);

			// TRANSLATORS Error shown when the rejection could not be saved and nothing was changed.
			throw new LibresignException($this->l10n->t('It was not possible to register the rejection. Nothing was changed.'));
		} catch (\Error $e) {
			$this->rollBackRejection($libreSignFile, $signRequests, $previousSignerStatuses, $previousFileStatus);
			throw $e;
		}

		return new CommittedRejection($previousRootStatus, $activatedSigners);
	}

	/**
	 * @param list<SignRequestEntity> $signRequests
	 * @param list<int> $previousSignerStatuses
	 */
	private function rollBackRejection(
		FileEntity $file,
		array $signRequests,
		array $previousSignerStatuses,
		int $previousFileStatus,
	): void {
		$this->db->rollBack();
		$this->restoreState($file, $signRequests, $previousSignerStatuses, $previousFileStatus);
	}

	private function restoreState(
		FileEntity $libreSignFile,
		array $signRequests,
		array $previousSignerStatuses,
		int $previousFileStatus,
	): void {
		foreach ($signRequests as $index => $signRequest) {
			$signRequest->setStatus($previousSignerStatuses[$index]);
			$signRequest->setRejectedAt(null);
			$signRequest->setRejectionComment(null);
			$signRequest->setRejectionCommentPrivate(false);
		}
		$libreSignFile->setStatus($previousFileStatus);
	}
}
