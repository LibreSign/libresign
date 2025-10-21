<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\CertificateEngine;

class NoneHandler extends AEngineHandler implements IEngineHandler {
	#[\Override]
	protected function getConfigureCheckResourceName(): string {
		return 'none-configure';
	}

	#[\Override]
	protected function getCertificateRegenerationTip(): string {
		return 'Switch to a proper certificate engine: occ libresign:configure:openssl or occ libresign:configure:cfssl';
	}

	#[\Override]
	protected function getEngineSpecificChecks(): array {
		return [];
	}

	#[\Override]
	protected function getSetupSuccessMessage(): string {
		return 'None handler is active (no certificates required).';
	}

	#[\Override]
	protected function getSetupErrorMessage(): string {
		return 'None handler configuration error.';
	}

	#[\Override]
	protected function getSetupErrorTip(): string {
		return 'Switch to a proper certificate engine: occ libresign:configure:openssl or occ libresign:configure:cfssl';
	}

	#[\Override]
	public function generateRootCert(
		string $commonName,
		array $names = [],
	): string {
		return '';
	}

	#[\Override]
	public function generateCertificate(string $certificate = '', string $privateKey = ''): string {
		return '';
	}

	#[\Override]
	public function isSetupOk(): bool {
		return true;
	}

	#[\Override]
	public function generateCrlDer(array $revokedCertificates): string {
		throw new \RuntimeException('CRL generation is not supported by None handler');
	}
}
