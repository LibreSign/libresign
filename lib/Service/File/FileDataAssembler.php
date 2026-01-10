<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use DateTimeInterface;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\FileElementService;
use OCP\IURLGenerator;
use OCP\IUserManager;

class FileDataAssembler {
	public function __construct(
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
		private FileElementService $fileElementService,
		private EnvelopeAssembler $envelopeAssembler,
		private IURLGenerator $urlGenerator,
		private IUserManager $userManager,
	) {
	}

	public function assemble(File $file, \stdClass $fileData, FileResponseOptions $options): void {
		$fileData->id = $file->getId();
		$fileData->uuid = $file->getUuid();
		$fileData->name = $file->getName();
		$fileData->status = $file->getStatus();
		$fileData->created_at = $file->getCreatedAt()->format(DateTimeInterface::ATOM);
		$fileData->statusText = $this->fileMapper->getTextOfStatus($file->getStatus());
		$fileData->nodeId = $file->getNodeId();
		$fileData->signatureFlow = $file->getSignatureFlow();
		$fileData->docmdpLevel = $file->getDocmdpLevel();
		$fileData->nodeType = $file->getNodeType();

		if ($fileData->nodeType !== 'envelope' && !$file->getParentFileId()) {
			$fileId = $file->getId();
			$childrenFiles = $this->fileMapper->getChildrenFiles($fileId);
			if (!empty($childrenFiles)) {
				$file->setNodeType('envelope');
				$this->fileMapper->update($file);

				$fileData->nodeType = 'envelope';
				$fileData->filesCount = count($childrenFiles);
				$fileData->files = [];
			}
		}

		if ($fileData->nodeType === 'envelope') {
			$metadata = $file->getMetadata();
			$fileData->filesCount = $metadata['filesCount'] ?? 0;
			$fileData->files = [];
			$childrenFiles = $this->fileMapper->getChildrenFiles($file->getId());
			foreach ($childrenFiles as $childFile) {
				$fileData->files[] = $this->envelopeAssembler->buildEnvelopeChildData($childFile, $options);
			}
			if ($file->getStatus() === FileStatus::SIGNED->value) {
				$latestSignedDate = null;
				foreach ($childrenFiles as $childFile) {
					$signRequests = $this->signRequestMapper->getByFileId($childFile->getId());
					foreach ($signRequests as $signRequest) {
						$signed = $signRequest->getSigned();
						if ($signed && (!$latestSignedDate || $signed > $latestSignedDate)) {
							$latestSignedDate = $signed;
						}
					}
				}
				if ($latestSignedDate) {
					$fileData->signedDate = $latestSignedDate->format(DateTimeInterface::ATOM);
				}
			}
		}

		$fileData->requested_by = [
			'userId' => $file->getUserId(),
			'displayName' => $this->userManager->get($file->getUserId())->getDisplayName(),
		];
		$fileData->file = $this->urlGenerator->linkToRoute('libresign.page.getPdf', ['uuid' => $file->getUuid()]);

		if ($options->isShowVisibleElements()) {
			// For envelopes, the visibleElements are in the child files (buildEnvelopeChildData)
			if ($fileData->nodeType === 'envelope') {
				// The visibleElements of each child file are already loaded in the EnvelopeAssembler
				// No need to duplicate the logic here
				return;
			}

			// For individual files, fetch their visibleElements
			$signers = $this->signRequestMapper->getByFileId($file->getId());
			$fileData->visibleElements = [];
			foreach ($this->signRequestMapper->getVisibleElementsFromSigners($signers) as $row) {
				if (empty($row)) {
					continue;
				}
				$fileMetadata = $file->getMetadata();
				$fileData->visibleElements = array_merge(
					$this->fileElementService->formatVisibleElements($row, $fileMetadata),
					$fileData->visibleElements
				);
			}
		}
	}
}
