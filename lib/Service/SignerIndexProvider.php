<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\SignRequest;

class SignerIndexProvider {
	public function __construct(
		private IdentifyMethodService $identifyMethodService,
	) {
	}

	/**
	 * @param SignRequest[] $signRequests
	 * @return array<string, SignRequest[]>
	 */
	public function build(array $signRequests): array {
		if (empty($signRequests)) {
			return [];
		}
		$signRequestIds = array_column(array_map(fn ($sr) => ['id' => $sr->getId()], $signRequests), 'id');
		$identifyMethodsBatch = $this->identifyMethodService->getIdentifyMethodsFromSignRequestIds($signRequestIds);

		$index = [];
		foreach ($signRequests as $signRequest) {
			$identifyMethods = $identifyMethodsBatch[$signRequest->getId()] ?? [];
			foreach ($identifyMethods as $methodInstances) {
				foreach ($methodInstances as $identifyMethod) {
					$entity = $identifyMethod->getEntity();
					$indexKey = $entity->getIdentifierKey() . ':' . $entity->getIdentifierValue();
					$index[$indexKey][] = $signRequest;
				}
			}
		}
		return $index;
	}
}
