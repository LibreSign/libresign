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
		$index = [];
		foreach ($signRequests as $signRequest) {
			$identifyMethods = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequest->getId());
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
