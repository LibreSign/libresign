<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\CertificateEngine;

use InvalidArgumentException;

trait OrderCertificatesTrait {
	public function orderCertificates(array $certificates): array {
		$this->validateCertificateStructure($certificates);
		$remainingCerts = [];

		// Add the root cert at ordered list and collect the remaining certs
		foreach ($certificates as $cert) {
			if (!$this->arrayDiffCanonicalized($cert['subject'], $cert['issuer'])) {
				$ordered = [$cert];
				continue;
			}
			$remainingCerts[$cert['name']] = $cert;
		}

		if (!isset($ordered)) {
			return $certificates;
		}


		while (!empty($remainingCerts)) {
			$found = false;
			foreach ($remainingCerts as $name => $cert) {
				$first = reset($ordered);
				if (!$this->arrayDiffCanonicalized($first['subject'], $cert['issuer'])) {
					array_unshift($ordered, $cert);
					unset($remainingCerts[$name]);
					$found = true;
					break;
				}
			}

			if (!$found) {
				throw new InvalidArgumentException('Certificate chain is incomplete or invalid.');
			}
		}

		return $ordered;
	}

	private function validateCertificateStructure(array $certificates): void {
		if (empty($certificates)) {
			throw new InvalidArgumentException('Certificate list cannot be empty');
		}

		foreach ($certificates as $cert) {
			if (!isset($cert['subject'], $cert['issuer'], $cert['name'])) {
				throw new InvalidArgumentException(
					'Invalid certificate structure. Certificate must have "subject", "issuer", and "name".'
				);
			}
		}

		$names = array_column($certificates, 'name');
		if (count($names) !== count(array_unique($names))) {
			throw new InvalidArgumentException('Duplicate certificate names detected');
		}
	}

	private function arrayDiffCanonicalized(array $array1, array $array2): array {
		sort($array1);
		sort($array2);

		return array_diff($array1, $array2);
	}
}
