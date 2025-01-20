<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\CertificateEngine;

trait OrderCertificatesTrait {
	public function orderCertificates(array $certificates): array {
		$ordered = [];
		$map = [];

		$tree = current($certificates);
		foreach ($certificates as $cert) {
			if ($tree['subject'] === $cert['issuer']) {
				$tree = $cert;
			}
			$map[$cert['name']] = $cert;
		}

		if (!$tree) {
			return $certificates;
		}
		unset($map[$tree['name']]);
		$ordered[] = $tree;

		$current = $tree;
		while (!empty($map) && $current) {
			if ($current['subject'] === $tree['issuer']) {
				$ordered[] = $current;
				$tree = $current;
				unset($map[$current['name']]);
				$current = reset($map);
				continue;
			}
			$current = next($map);
		}

		return $ordered;
	}
}
