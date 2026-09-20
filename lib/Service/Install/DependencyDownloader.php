<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Install;

use OCA\Libresign\Exception\LibresignException;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

class DependencyDownloader {
	public function __construct(
		private IClientService $clientService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @param callable(int, int): void|null $progress
	 */
	public function download(
		string $url,
		string $dependencyName,
		string $path,
		string $hash = '',
		string $hashAlgorithm = 'md5',
		?callable $progress = null,
	): void {
		if (file_exists($path) && $hash !== '' && hash_file($hashAlgorithm, $path) === $hash) {
			if ($progress !== null) {
				$size = (int)filesize($path);
				$progress($size, $size);
			}
			return;
		}

		try {
			$this->clientService->newClient()->get($url, [
				'sink' => $path,
				'timeout' => 0,
				'progress' => static function ($downloadSize, $downloaded) use ($progress): void {
					if ($progress !== null) {
						$progress((int)$downloadSize, (int)$downloaded);
					}
				},
			]);
		} catch (\Exception $e) {
			$this->logger->error('Dependency download failed', [
				'resource' => $dependencyName,
				'url' => $url,
				'exception' => $e,
			]);
			throw new LibresignException(
				'Could not download ' . $dependencyName . '. '
				. 'Please check the Nextcloud server network, DNS and proxy configuration, then retry. '
				. 'See the Nextcloud server log for the technical error.',
				previous: $e,
			);
		}

		$this->verifyDownloadedFile($url, $dependencyName, $path, $hash, $hashAlgorithm);
	}

	public function fetchChecksum(string $file, string $checksumUrl): string {
		try {
			$response = $this->clientService->newClient()->get($checksumUrl);
			$hashes = $response->getBody();
		} catch (\Exception $e) {
			$this->logger->error('Dependency checksum file download failed', [
				'url' => $checksumUrl,
				'file' => $file,
				'exception' => $e,
			]);
			throw new LibresignException(
				'Could not download the checksum information required to verify ' . $file . '. '
				. 'Please check the server network, DNS and proxy configuration, then retry. '
				. 'See the Nextcloud server log for the technical error.',
				previous: $e,
			);
		}

		if (!is_string($hashes) || $hashes === '') {
			$this->logger->error('Dependency checksum file is empty', [
				'url' => $checksumUrl,
				'file' => $file,
			]);
			throw new LibresignException(
				'The checksum information for ' . $file . ' was empty. '
				. 'Please retry later. If the problem persists, check the Nextcloud server log before reporting it.',
			);
		}

		$matched = preg_match(
			'/(?<hash>[A-Fa-f0-9]+) +' . preg_quote($file, '/') . '(?:\s|$)/',
			$hashes,
			$matches,
		);
		if ($matched !== 1 || empty($matches['hash'])) {
			$this->logger->error('Checksum entry not found for dependency artifact', [
				'url' => $checksumUrl,
				'file' => $file,
			]);
			throw new LibresignException(
				'The checksum list does not contain an entry for ' . $file . '. '
				. 'This usually indicates that the upstream release metadata changed or is temporarily incomplete. '
				. 'Please retry later and check the Nextcloud server log if it persists.',
			);
		}

		return $matches['hash'];
	}

	private function verifyDownloadedFile(
		string $url,
		string $dependencyName,
		string $path,
		string $hash,
		string $hashAlgorithm,
	): void {
		if (!file_exists($path)) {
			$this->logger->error('Dependency download completed without creating the expected file', [
				'resource' => $dependencyName,
				'url' => $url,
				'path' => $path,
			]);
			throw new LibresignException(
				'Download of ' . $dependencyName . ' did not produce the expected file. '
				. 'Please retry the installation. If the problem persists, check the Nextcloud server log for details.',
			);
		}

		if ($hash !== '' && hash_file($hashAlgorithm, $path) !== $hash) {
			$this->logger->error('Dependency checksum verification failed', [
				'resource' => $dependencyName,
				'url' => $url,
				'path' => $path,
				'algorithm' => $hashAlgorithm,
			]);
			throw new LibresignException(
				'Checksum verification failed for ' . $dependencyName . '. The downloaded file was not accepted. '
				. 'Please retry the installation. If it fails again, check whether a proxy or cache is modifying downloads and review the Nextcloud server log.',
			);
		}
	}
}
