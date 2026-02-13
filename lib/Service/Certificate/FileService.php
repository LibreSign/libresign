<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Certificate;

use OCA\Libresign\Enum\CertificateEngineType;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use Psr\Log\LoggerInterface;

class FileService {
	public function __construct(
		private CertificateEngineFactory $certificateEngineFactory,
		private LoggerInterface $logger,
	) {
	}

	public function getRootCertificateByGeneration(string $instanceId, int $generation, CertificateEngineType $engineType): string {
		return $this->loadCertificateFileByGeneration($instanceId, $generation, $engineType, 'ca.pem');
	}

	public function getPrivateKeyByGeneration(string $instanceId, int $generation, CertificateEngineType $engineType): string {
		return $this->loadCertificateFileByGeneration($instanceId, $generation, $engineType, 'ca-key.pem');
	}

	private function loadCertificateFileByGeneration(string $instanceId, int $generation, CertificateEngineType $engineType, string $filename): string {
		try {
			$engine = $this->certificateEngineFactory->getEngine($engineType->getEngineName());
			if (!method_exists($engine, 'getConfigPathByParams')) {
				return '';
			}

			$configPath = $engine->getConfigPathByParams($instanceId, $generation);
			return $this->readCertificateFile($configPath . DIRECTORY_SEPARATOR . $filename);
		} catch (\Exception $e) {
			$this->logger->debug('Failed to load certificate file', [
				'instanceId' => $instanceId,
				'generation' => $generation,
				'engineType' => $engineType->value,
				'filename' => $filename,
				'error' => $e->getMessage(),
			]);
			return '';
		}
	}

	private function readCertificateFile(string $filePath): string {
		if (!file_exists($filePath) || !is_readable($filePath)) {
			return '';
		}

		$content = file_get_contents($filePath);
		return $content !== false ? $content : '';
	}
}
