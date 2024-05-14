<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\CertificateEngine;

class NoneHandler extends AEngineHandler implements IEngineHandler {
	public function generateRootCert(
		string $commonName,
		array $names = [],
	): string {
		return '';
	}

	public function generateCertificate(string $certificate = '', string $privateKey = ''): string {
		return '';
	}

	public function isSetupOk(): bool {
		return true;
	}

	public function configureCheck(): array {
		return [];
	}
}
