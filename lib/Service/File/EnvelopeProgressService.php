<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use stdClass;

class EnvelopeProgressService {
	/**
	 * Compute progress.
	 *
	 * $signRequestsByFileId is a map: fileId => SignRequest[]
	 * $identifyMethodsBySignRequest is a map: signRequestId => array of identify-method wrappers
	 */
	public function computeProgress(stdClass $fileData, $envelope, array $childrenFiles, array $signRequestsByFileId, array $identifyMethodsBySignRequest): void {
		if (!$envelope || $envelope->getParentFileId()) {
			return;
		}

		if (empty($fileData->signers)) {
			return;
		}

		if (empty($childrenFiles)) {
			$this->resetSignerCounts($fileData);
			return;
		}

		$signerProgress = $this->aggregateSignerProgress($childrenFiles, $signRequestsByFileId, $identifyMethodsBySignRequest);
		$this->applyProgressToFileData($fileData, $signerProgress);
	}

	private function resetSignerCounts(stdClass $fileData): void {
		foreach ($fileData->signers as $idx => $_) {
			$fileData->signers[$idx]->totalDocuments = 0;
			$fileData->signers[$idx]->documentsSignedCount = 0;
		}
	}

	/**
	 * Aggregate signer progress across all children files.
	 * Returns map signerKey => ['total' => int, 'signed' => int]
	 *
	 * @return array<string,array{total:int,signed:int}>
	 */
	private function aggregateSignerProgress(array $childrenFiles, array $signRequestsByFileId, array $identifyMethodsBySignRequest): array {
		$signerProgress = [];
		foreach ($childrenFiles as $childFile) {
			$signRequests = $signRequestsByFileId[$childFile->getId()] ?? [];
			foreach ($signRequests as $signRequest) {
				$signRequestId = $signRequest->getId();
				$identifyMethods = $identifyMethodsBySignRequest[$signRequestId] ?? [];

				$signerKey = $this->buildSignerKey($identifyMethods);
				if (!isset($signerProgress[$signerKey])) {
					$signerProgress[$signerKey] = ['total' => 0, 'signed' => 0];
				}

				$signerProgress[$signerKey]['total']++;
				if ($signRequest->getSigned()) {
					$signerProgress[$signerKey]['signed']++;
				}
			}
		}
		return $signerProgress;
	}

	private function applyProgressToFileData(stdClass $fileData, array $signerProgress): void {
		foreach ($fileData->signers as $index => $signer) {
			$signerKey = $this->buildSignerKeyFromEnvelopeSigner($signer);
			if (isset($signerProgress[$signerKey])) {
				$fileData->signers[$index]->totalDocuments = $signerProgress[$signerKey]['total'];
				$fileData->signers[$index]->documentsSignedCount = $signerProgress[$signerKey]['signed'];
			} else {
				$fileData->signers[$index]->totalDocuments = 0;
				$fileData->signers[$index]->documentsSignedCount = 0;
			}
		}
	}

	private function buildSignerKey(array $identifyMethods): string {
		$keys = [];
		foreach ($identifyMethods as $methods) {
			foreach ($methods as $identifyMethod) {
				$entity = $identifyMethod->getEntity();
				$keys[] = $entity->getUniqueIdentifier();
			}
		}
		sort($keys);
		return implode('|', $keys);
	}

	private function buildSignerKeyFromEnvelopeSigner(stdClass $signer): string {
		if (empty($signer->identifyMethods)) {
			return '';
		}
		$keys = [];
		foreach ($signer->identifyMethods as $method) {
			$keys[] = $method['method'] . ':' . $method['value'];
		}
		sort($keys);
		return implode('|', $keys);
	}
}
