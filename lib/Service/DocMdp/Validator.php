<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\DocMdp;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\File\Pdf\PdfValidator;
use OCA\Libresign\Service\Policy\Provider\DocMdp\FilePolicy\DocMdpFilePolicyApplier;
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
		private DocMdpFilePolicyApplier $docMdpFilePolicyApplier,
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
					// TRANSLATORS Error shown when trying to add more signers to a document certified with DocMDP level that forbids further changes.
					$this->l10n->t('This document has been certified with no changes allowed. You cannot add more signers to this document.')
				);
			}

			if (count($data['signers']) > 1) {
				throw new LibresignException(
					// TRANSLATORS Error shown when trying to add more signers to a document certified with DocMDP level that forbids further changes.
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
			// TRANSLATORS Error shown when validating DocMDP certification and the document file cannot be found.
			throw new LibresignException($this->l10n->t('File not found'));
		}

		$firstNode = current($node);
		if (!$firstNode instanceof \OCP\Files\File) {
			// TRANSLATORS Error shown when validating DocMDP certification and the file is not a supported document type.
			throw new LibresignException($this->l10n->t('Invalid file type'));
		}

		$content = $firstNode->getContent();
		$fileName = $firstNode->getName();

		$this->pdfValidator->validate($content, $fileName);
	}

	private function getDocMdpLevel(array $data, ?File &$file): int {
		if (!empty($data['uuid'])) {
			try {
				$file = $this->fileMapper->getByUuid($data['uuid']);
			} catch (DoesNotExistException) {
				// File doesn't exist, resolve the level of a new request
			}
		}

		$documents = $file?->isEnvelope() ? $this->fileMapper->getChildrenFiles($file->getId()) : [];
		if ($documents === []) {
			$documents = [$file];
		}
		$levels = array_map(
			fn (?File $document): int => ($this->docMdpFilePolicyApplier->resolveLevel($document, $data)
				?? $this->configService->getLevel())->value,
			$documents,
		);

		return in_array(DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED->value, $levels, true)
			? DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED->value
			: $levels[0];
	}
}
