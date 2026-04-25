<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use DateTime;
use DateTimeInterface;

class CertificateSignersMergeService {
	/**
	 * @param callable(array, string): (?string) $resolveUid
	 * @param callable(string, string): string $buildIdentifier
	 * @param callable(string): (?string) $lookupAccountDisplayName
	 */
	public function merge(
		\stdClass $fileData,
		array $certData,
		string $host,
		string $signedStatusText,
		callable $resolveUid,
		callable $buildIdentifier,
		callable $lookupAccountDisplayName,
	): void {
		$existingSigners = $fileData->signers ?? [];
		$hasContractSigners = $this->hasContractSigners($existingSigners);
		$indexMap = $this->buildSignerIndexMap($existingSigners, $buildIdentifier);
		$usedIndexes = [];
		$lastMatchedSignerIndex = null;
		$singleContractSignerIndex = $this->getSingleContractSignerIndex($existingSigners);

		foreach ($certData as $index => $signer) {
			$resolvedUid = $this->resolveCertSignerUid($signer, $existingSigners, $host, $resolveUid);
			$matchedIndex = $this->findMatchingSignerIndex($indexMap, $resolvedUid, $signer);
			$targetIndex = $this->resolveTargetIndex(
				$matchedIndex,
				$existingSigners,
				$usedIndexes,
				$hasContractSigners,
				$index,
			);
			if ($targetIndex === null) {
				$timestampTargetIndex = $lastMatchedSignerIndex ?? $singleContractSignerIndex;
				if ($timestampTargetIndex !== null
					&& isset($fileData->signers[$timestampTargetIndex])
					&& $this->isTechnicalTimestampEntry($signer)
				) {
					$this->hydrateTimestampOnly($fileData->signers[$timestampTargetIndex], $signer);
				}
				continue;
			}

			$isLibreSignMatch = $matchedIndex !== null && isset($existingSigners[$matchedIndex]->signRequestId);
			$usedIndexes[$targetIndex] = true;

			$this->ensureSignerSlotExists($fileData, $targetIndex);
			$this->hydrateSignerFromCertData(
				$fileData->signers[$targetIndex],
				$signer,
				$resolvedUid,
				$isLibreSignMatch,
				$host,
				$signedStatusText,
				$resolveUid,
				$lookupAccountDisplayName,
			);

			if (isset($fileData->signers[$targetIndex]->uid)) {
				$indexMap[strtolower((string)$fileData->signers[$targetIndex]->uid)] = $targetIndex;
			}

			$lastMatchedSignerIndex = $targetIndex;
		}
	}

	private function isTechnicalTimestampEntry(array $signer): bool {
		if (!isset($signer['timestamp']) || !is_array($signer['timestamp'])) {
			return false;
		}

		if (isset($signer['uid']) && is_string($signer['uid']) && $signer['uid'] !== '') {
			return false;
		}

		$subjectUid = $signer['chain'][0]['subject']['UID'] ?? null;
		if (is_string($subjectUid) && $subjectUid !== '') {
			return false;
		}

		return true;
	}

	private function getSingleContractSignerIndex(array $signers): ?int {
		$contractIndexes = [];
		foreach ($signers as $index => $signer) {
			if (is_object($signer) && isset($signer->signRequestId) && is_numeric($signer->signRequestId)) {
				$contractIndexes[] = $index;
			}
		}

		if (count($contractIndexes) === 1) {
			return $contractIndexes[0];
		}

		return null;
	}

	private function hydrateTimestampOnly(\stdClass $targetSigner, array $signer): void {
		$targetSigner->timestamp = $signer['timestamp'];
		if (isset($signer['timestamp']['genTime']) && $signer['timestamp']['genTime'] instanceof DateTimeInterface) {
			$targetSigner->timestamp['genTime'] = $signer['timestamp']['genTime']->format(DateTimeInterface::ATOM);
		}
	}

	/**
	 * @param callable(array, string): (?string) $resolveUid
	 */
	private function resolveCertSignerUid(array $signer, array $existingSigners, string $host, callable $resolveUid): ?string {
		if (!isset($signer['chain'][0]) || !is_array($signer['chain'][0])) {
			return is_string($signer['uid'] ?? null) ? $signer['uid'] : null;
		}

		$resolvedUid = $this->tryMatchWithExistingSigners($signer['chain'][0], $existingSigners, $host, $resolveUid);
		if ($resolvedUid) {
			return $resolvedUid;
		}

		$isLibreSignCert = isset($signer['chain'][0]['isLibreSignRootCA'])
			&& $signer['chain'][0]['isLibreSignRootCA'] === true;
		if ($isLibreSignCert) {
			$certUid = $signer['chain'][0]['subject']['UID'] ?? null;
			if (!is_string($certUid) || $certUid === '') {
				return null;
			}
			return str_contains($certUid, ':') ? $certUid : 'account:' . $certUid;
		}

		if (is_string($signer['uid'] ?? null) && $signer['uid'] !== '') {
			return $signer['uid'];
		}

		return $resolveUid($signer['chain'][0], $host);
	}

	private function resolveTargetIndex(
		?int $matchedIndex,
		array $existingSigners,
		array $usedIndexes,
		bool $hasContractSigners,
		int $defaultIndex,
	): ?int {
		if ($matchedIndex !== null) {
			return $matchedIndex;
		}

		if ($hasContractSigners) {
			return null;
		}

		if (empty($existingSigners)) {
			return $defaultIndex;
		}

		return $this->nextAvailableSignerIndex($existingSigners, $usedIndexes);
	}

	private function ensureSignerSlotExists(\stdClass $fileData, int $targetIndex): void {
		if (!isset($fileData->signers[$targetIndex])) {
			$fileData->signers[$targetIndex] = new \stdClass();
		}
	}

	/**
	 * @param callable(array, string): (?string) $resolveUid
	 * @param callable(string): (?string) $lookupAccountDisplayName
	 */
	private function hydrateSignerFromCertData(
		\stdClass $targetSigner,
		array $signer,
		?string $resolvedUid,
		bool $isLibreSignMatch,
		string $host,
		string $signedStatusText,
		callable $resolveUid,
		callable $lookupAccountDisplayName,
	): void {
		$preservedDisplayName = $isLibreSignMatch && isset($targetSigner->displayName)
			? $targetSigner->displayName
			: null;

		$targetSigner->status = 2;
		$targetSigner->statusText = $signedStatusText;

		if (isset($signer['timestamp'])) {
			$targetSigner->timestamp = $signer['timestamp'];
			if (isset($signer['timestamp']['genTime']) && $signer['timestamp']['genTime'] instanceof DateTimeInterface) {
				$targetSigner->timestamp['genTime'] = $signer['timestamp']['genTime']->format(DateTimeInterface::ATOM);
			}
		}
		if (isset($signer['signingTime']) && $signer['signingTime'] instanceof DateTimeInterface) {
			$targetSigner->signingTime = $signer['signingTime'];
			$targetSigner->signed = $signer['signingTime']->format(DateTimeInterface::ATOM);
		}
		if (isset($signer['docmdp'])) {
			$targetSigner->docmdp = $signer['docmdp'];
		}
		if (isset($signer['docmdp_validation'])) {
			$targetSigner->docmdp_validation = $signer['docmdp_validation'];
		}
		if (isset($signer['modifications'])) {
			$targetSigner->modifications = $signer['modifications'];
		}
		if (isset($signer['modification_validation'])) {
			$targetSigner->modification_validation = $signer['modification_validation'];
		}

		if (isset($signer['chain']) && is_array($signer['chain'])) {
			$this->processChainData($targetSigner, $signer['chain']);
		}

		$this->assignSignerUid($targetSigner, $signer, $resolvedUid, $host, $resolveUid);

		if (isset($signer['signDate'])) {
			$targetSigner->signDate = $signer['signDate'];
		}
		if (isset($signer['type'])) {
			$targetSigner->type = $signer['type'];
		}

		$this->assignSignerDisplayName($targetSigner, $signer, $preservedDisplayName, $lookupAccountDisplayName);
	}

	/**
	 * @param callable(array, string): (?string) $resolveUid
	 */
	private function assignSignerUid(\stdClass $targetSigner, array $signer, ?string $resolvedUid, string $host, callable $resolveUid): void {
		if (isset($signer['uid'])) {
			$targetSigner->uid = $signer['uid'];
			return;
		}

		if ($resolvedUid) {
			$targetSigner->uid = $resolvedUid;
			return;
		}

		if (isset($signer['chain'][0]) && is_array($signer['chain'][0])) {
			$targetSigner->uid = $resolveUid($signer['chain'][0], $host);
		}
	}

	/**
	 * @param callable(string): (?string) $lookupAccountDisplayName
	 */
	private function assignSignerDisplayName(\stdClass $targetSigner, array $signer, ?string $preservedDisplayName, callable $lookupAccountDisplayName): void {
		if ($preservedDisplayName) {
			$targetSigner->displayName = $preservedDisplayName;
			return;
		}

		if (isset($targetSigner->uid) && str_starts_with($targetSigner->uid, 'account:')) {
			$accountId = substr($targetSigner->uid, strlen('account:'));
			$displayName = $lookupAccountDisplayName($accountId);
			$targetSigner->displayName = $displayName ?: $accountId;
			return;
		}

		if (!isset($targetSigner->displayName) && isset($signer['chain'][0])) {
			$targetSigner->displayName = $signer['chain'][0]['name'] ?? ($signer['chain'][0]['subject']['CN'] ?? '');
		}
	}

	private function hasContractSigners(array $signers): bool {
		foreach ($signers as $signer) {
			if (is_object($signer) && isset($signer->signRequestId) && is_numeric($signer->signRequestId)) {
				return true;
			}
			if (is_array($signer) && isset($signer['signRequestId']) && is_numeric($signer['signRequestId'])) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param callable(string, string): string $buildIdentifier
	 */
	private function buildSignerIndexMap(array $signers, callable $buildIdentifier): array {
		$map = [];
		foreach ($signers as $index => $signer) {
			if (isset($signer->uid)) {
				$map[strtolower((string)$signer->uid)] = $index;
			}
			if (!empty($signer->identifyMethods)) {
				foreach ($signer->identifyMethods as $identifyMethod) {
					if (isset($identifyMethod['method']) && isset($identifyMethod['value'])) {
						$identifier = $buildIdentifier($identifyMethod['method'], $identifyMethod['value']);
						$map[strtolower($identifier)] = $index;
					}
				}
			}
		}
		return $map;
	}

	private function findMatchingSignerIndex(array $indexMap, ?string $resolvedUid, array $certSigner): ?int {
		$identifiers = [];
		if ($resolvedUid) {
			$identifiers[] = strtolower($resolvedUid);
		}
		if (!empty($certSigner['uid'])) {
			$identifiers[] = strtolower((string)$certSigner['uid']);
		}
		foreach ($identifiers as $identifier) {
			if (isset($indexMap[$identifier])) {
				return $indexMap[$identifier];
			}
		}
		return null;
	}

	private function nextAvailableSignerIndex(array $existingSigners, array $usedIndexes): int {
		$index = count($existingSigners);
		while (isset($existingSigners[$index]) || isset($usedIndexes[$index])) {
			$index++;
		}
		return $index;
	}

	private function processChainData(\stdClass $signer, array $chain): void {
		$signer->chain = [];

		foreach ($chain as $chainIndex => $chainItem) {
			$chainArr = $chainItem;

			if (isset($chainItem['validFrom_time_t']) && is_numeric($chainItem['validFrom_time_t'])) {
				$chainArr['valid_from'] = (new DateTime('@' . $chainItem['validFrom_time_t'], new \DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
			}
			if (isset($chainItem['validTo_time_t']) && is_numeric($chainItem['validTo_time_t'])) {
				$chainArr['valid_to'] = (new DateTime('@' . $chainItem['validTo_time_t'], new \DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
			}

			$chainArr['displayName'] = $chainArr['name'] ?? ($chainArr['subject']['CN'] ?? '');
			$signer->chain[$chainIndex] = $chainArr;
		}

		if (isset($chain[0])) {
			$this->enrichSignerWithCertificateValidation($signer, $chain[0]);
		}
	}

	private function enrichSignerWithCertificateValidation(\stdClass $signer, array $endEntityCert): void {
		if (isset($endEntityCert['name']) && !isset($signer->name)) {
			$signer->name = $endEntityCert['name'];
		}
		if (isset($endEntityCert['hash']) && !isset($signer->hash)) {
			$signer->hash = $endEntityCert['hash'];
		}
		if (isset($endEntityCert['serialNumber']) && !isset($signer->serialNumber)) {
			$signer->serialNumber = $endEntityCert['serialNumber'];
		}
		if (isset($endEntityCert['serialNumberHex']) && !isset($signer->serialNumberHex)) {
			$signer->serialNumberHex = $endEntityCert['serialNumberHex'];
		}
		if (isset($endEntityCert['signatureTypeSN']) && !isset($signer->signatureTypeSN)) {
			$signer->signatureTypeSN = $endEntityCert['signatureTypeSN'];
		}

		if (isset($endEntityCert['subject']) && !isset($signer->subject)) {
			$signer->subject = $endEntityCert['subject'];
		}

		if (isset($endEntityCert['crl_urls']) && !isset($signer->crl_urls)) {
			$signer->crl_urls = $endEntityCert['crl_urls'];
		}
		if (isset($endEntityCert['crl_validation']) && !isset($signer->crl_validation)) {
			$signer->crl_validation = $endEntityCert['crl_validation'];
		}
		if (isset($endEntityCert['crl_revoked_at']) && !isset($signer->crl_revoked_at)) {
			$signer->crl_revoked_at = $endEntityCert['crl_revoked_at'];
		}

		if (isset($endEntityCert['signature_validation']) && !isset($signer->signature_validation)) {
			$signer->signature_validation = $endEntityCert['signature_validation'];
		}

		if (isset($endEntityCert['isLibreSignRootCA']) && !isset($signer->isLibreSignRootCA)) {
			$signer->isLibreSignRootCA = $endEntityCert['isLibreSignRootCA'];
		}
	}

	/**
	 * @param callable(array, string): (?string) $resolveUid
	 */
	private function tryMatchWithExistingSigners(array $certData, array $existingSigners, string $host, callable $resolveUid): ?string {
		if (empty($existingSigners)) {
			return null;
		}

		$certSerialNumber = $certData['serialNumber'] ?? null;
		$certSerialNumberHex = $certData['serialNumberHex'] ?? null;
		$certHash = $certData['hash'] ?? null;

		if (!$certSerialNumber && !$certSerialNumberHex && !$certHash) {
			return null;
		}

		foreach ($existingSigners as $signer) {
			if (!isset($signer->metadata) || !is_array($signer->metadata)) {
				continue;
			}

			$certInfo = $signer->metadata['certificate_info'] ?? null;
			if (!is_array($certInfo)) {
				continue;
			}

			if ($certSerialNumber && isset($certInfo['serialNumber']) && $certSerialNumber === $certInfo['serialNumber']) {
				return $signer->uid ?? $resolveUid($certData, $host);
			}

			if ($certSerialNumberHex && isset($certInfo['serialNumberHex']) && $certSerialNumberHex === $certInfo['serialNumberHex']) {
				return $signer->uid ?? $resolveUid($certData, $host);
			}

			if ($certHash && isset($certInfo['hash']) && $certHash === $certInfo['hash']) {
				return $signer->uid ?? $resolveUid($certData, $host);
			}
		}

		return null;
	}
}
