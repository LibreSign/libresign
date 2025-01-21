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

		// Get the root certificate.
		$rootCert = null;
		foreach ($certificates as $cert) {
			if (!$this->arrayDiffCanonicalized($cert['subject'], $cert['issuer'])) {
				$rootCert = $cert;
			}
			$remainingCerts[$cert['name']] = $cert;
		}

		if ($rootCert) {
			unset($remainingCerts[$rootCert['name']]);
			$ordered = [$rootCert];
		} else {
			return $certificates;
		}


		while (!empty($remainingCerts)) {
			$found = false;
			foreach ($remainingCerts as $name => $cert) {
				$last = end($ordered);
				if (!$this->arrayDiffCanonicalized($last['subject'], $cert['issuer'])) {
					$ordered[] = $cert;
					unset($remainingCerts[$name]);
					$found = true;
					break;
				}
			}

			if (!$found) {
				throw new InvalidArgumentException('Certificate chain is incomplete or invalid. Certificates: ' . json_encode($certificates));
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
