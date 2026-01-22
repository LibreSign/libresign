<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\SignRequest;

class SignerNotificationPolicy {
	/**
	 * @param array $signer Signer data with email or uid key
	 * @param array<string, SignRequest[]> $signRequestIndex Indexed by identifierKey:identifierValue
	 * @return array{code:string, params:array}|null
	 */
	public function getValidationError(array $signer, array $signRequestIndex): ?array {
		$signerKey = key($signer);
		$signerValue = current($signer);
		$indexKey = $signerKey . ':' . $signerValue;

		$matchingSignRequests = $signRequestIndex[$indexKey] ?? [];
		if (empty($matchingSignRequests)) {
			return [
				'code' => 'not_requested',
				'params' => [$signer['email'] ?? $signerValue],
			];
		}

		foreach ($matchingSignRequests as $signRequest) {
			if ($signRequest->getSigned() !== null) {
				return [
					'code' => 'already_signed',
					'params' => [$signRequest->getDisplayName()],
				];
			}
		}

		return null;
	}
}
