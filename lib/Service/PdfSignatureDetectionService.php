<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Handler\SignEngine\SignEngineFactory;
use Psr\Log\LoggerInterface;

class PdfSignatureDetectionService {
	public function __construct(
		private SignEngineFactory $signEngineFactory,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Check if a PDF has existing signatures
	 *
	 * @param string $pdfContent The PDF file content
	 * @return bool True if the file has signatures, false otherwise
	 */
	public function hasSignatures(string $pdfContent): bool {
		$resource = fopen('php://memory', 'r+');
		if ($resource === false) {
			$this->logger->warning('Failed to create resource for signature detection');
			return false;
		}

		fwrite($resource, $pdfContent);
		rewind($resource);

		try {
			$engine = $this->signEngineFactory->resolve('pdf');
			$certificates = $engine->getCertificateChain($resource);
			return !empty($certificates);
		} catch (\Throwable $e) {
			$this->logger->debug('Failed to detect signatures: ' . $e->getMessage());
			return false;
		} finally {
			fclose($resource);
		}
	}
}
