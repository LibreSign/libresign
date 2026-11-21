<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\DocMdp;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\File\Pdf\PdfValidator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\IRootFolder;
use OCP\IL10N;

class Validator {
	public function __construct(
		private IL10N $l10n,
		private FileMapper $fileMapper,
		private ConfigService $configService,
		private PdfValidator $pdfValidator,
		private IRootFolder $root,
	) {
	}

	public function validateSignersCount(array $data): void {
		if (empty($data['signers'])) {
			return;
		}

		$file = null;
		$docMdpLevel = $this->getDocMdpLevel($data, $file);

		if ($docMdpLevel === 1) {
			if ($file && $file->getSignedHash()) {
				throw new LibresignException(
					$this->l10n->t('This document has been certified with no changes allowed. You cannot add more signers to this document.')
				);
			}

			if (count($data['signers']) > 1) {
				throw new LibresignException(
					$this->l10n->t('This document has been certified with no changes allowed. You cannot add more signers to this document.')
				);
			}
		}
	}

	public function validatePdfRestrictions(File $file): void {
		if (!$file->getSignedHash()) {
			return;
		}

		$node = $this->root->getById($file->getNodeId());
		if (empty($node)) {
			throw new LibresignException($this->l10n->t('File not found'));
		}

		$firstNode = current($node);
		if (!$firstNode instanceof \OCP\Files\File) {
			throw new LibresignException($this->l10n->t('Invalid file type'));
		}

		$content = $firstNode->getContent();
		$fileName = $firstNode->getName();

		$this->pdfValidator->validate($content, $fileName);
	}

	/**
	 * A frozen document certifies with its recorded level. A draft which has
	 * never been sent still uses the current setting, as does a legacy row
	 * with an ambiguous (unrecorded) level 0.
	 */
	private function getDocMdpLevel(array $data, ?File &$file): int {
		try {
			if (!empty($data['uuid'])) {
				$file = $this->fileMapper->getByUuid($data['uuid']);
			} elseif (!empty($data['file']['nodeId'])) {
				$file = $this->fileMapper->getByNodeId((int)$data['file']['nodeId']);
			}
		} catch (DoesNotExistException) {
			// A new request has no stored DocMDP policy yet.
		}

		if ($file === null) {
			return $this->configService->getLevel()->value;
		}

		$documents = $file->getNodeType() === 'envelope'
			? $this->fileMapper->getChildrenFiles($file->getId())
			: [$file];
		$firstLevel = null;
		$strict = null;
		foreach ($documents as $document) {
			$level = (StoredLevel::forSigning($document)
				?? $this->configService->getLevel())->value;
			$firstLevel ??= $level;
			if ($level === 1 && ($strict === null || $document->getSignedHash())) {
				$strict = $document;
			}
		}
		if ($strict !== null) {
			$file = $strict;
			return 1;
		}

		return $firstLevel ?? $this->configService->getLevel()->value;
	}
}
