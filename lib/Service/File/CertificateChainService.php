<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use OCA\Libresign\Db\File;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use Psr\Log\LoggerInterface;

class CertificateChainService {
	public function __construct(
		private Pkcs12Handler $pkcs12Handler,
		private LoggerInterface $logger,
	) {
	}

	public function getCertificateChain($fileNode, File $libreSignFile, $options): array {
		if (!$options->isValidateFile() || !$libreSignFile->getSignedNodeId()) {
			return [];
		}

		try {
			$resource = $fileNode->fopen('rb');
			$sha256 = $this->getSha256FromResource($resource);
			rewind($resource);
			if ($sha256 === $libreSignFile->getSignedHash()) {
				$this->pkcs12Handler->setIsLibreSignFile();
			}
			$certData = $this->pkcs12Handler->getCertificateChain($resource);
			fclose($resource);
			return $certData;
		} catch (\Exception $e) {
			$this->logger->warning('Failed to load certificate chain: ' . $e->getMessage());
			return [];
		}
	}

	private function getSha256FromResource($resource): string {
		$hashContext = hash_init('sha256');
		while (!feof($resource)) {
			$buffer = fread($resource, 8192);
			hash_update($hashContext, $buffer);
		}
		return hash_final($hashContext);
	}
}
