<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\CertificateEngine;

class NoneHandler extends AEngineHandler implements IEngineHandler {
	protected function getConfigureCheckResourceName(): string {
		return 'none-configure';
	}

	protected function getCertificateRegenerationTip(): string {
		return 'Switch to a proper certificate engine: occ libresign:configure:openssl or occ libresign:configure:cfssl';
	}

	protected function getEngineSpecificChecks(): array {
		return [];
	}

	protected function getSetupSuccessMessage(): string {
		return 'None handler is active (no certificates required).';
	}

	protected function getSetupErrorMessage(): string {
		return 'None handler configuration error.';
	}

	protected function getSetupErrorTip(): string {
		return 'Switch to a proper certificate engine: occ libresign:configure:openssl or occ libresign:configure:cfssl';
	}

	public function generateRootCert(
		string $commonName,
		array $names = [],
	): void {
	}

	public function generateCertificate(string $certificate = '', string $privateKey = ''): string {
		return '';
	}

	public function isSetupOk(): bool {
		return true;
	}

	public function generateCrlDer(array $revokedCertificates): string {
		throw new \RuntimeException('CRL generation is not supported by None handler');
	}
}
