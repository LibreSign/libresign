<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Install;

use OC\Memcache\NullCache;
use OCA\Libresign\AppInfo\Application;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\ICache;
use OCP\ICacheFactory;
use Psr\Log\LoggerInterface;

class InstallProgressStore {
	private ICache $cache;

	public function __construct(
		ICacheFactory $cacheFactory,
		private DependencyStorage $dependencyStorage,
		private LoggerInterface $logger,
	) {
		$this->cache = $cacheFactory->createDistributed('libresign-setup');
	}

	public function get(InstallTarget $target, string $resource): array {
		$key = $target->cacheKey($resource);
		if (!$this->cache instanceof NullCache) {
			$value = $this->cache->get(Application::APP_ID . '-asyncDownloadProgress-' . $key);
			return is_array($value) ? $value : [];
		}

		$file = $this->getFallbackFile($target, false);
		if ($file === null) {
			return [];
		}

		try {
			$content = $file->getContent();
			$json = $content !== '' ? json_decode($content, true) : [];
			$value = is_array($json) ? ($json[$key] ?? []) : [];
			return is_array($value) ? $value : [];
		} catch (\Exception $e) {
			$this->logger->error('Could not read installer progress fallback', [
				'app' => Application::APP_ID,
				'exception' => $e,
			]);
			return [];
		}
	}

	public function set(InstallTarget $target, string $resource, array $value): void {
		$key = $target->cacheKey($resource);
		if (!$this->cache instanceof NullCache) {
			$this->cache->set(Application::APP_ID . '-asyncDownloadProgress-' . $key, $value);
			return;
		}

		$file = $this->getFallbackFile($target, true);
		if ($file === null) {
			return;
		}
		$content = $file->getContent();
		$json = $content !== '' ? json_decode($content, true) : [];
		$json = is_array($json) ? $json : [];
		$json[$key] = $value;
		$file->putContent(json_encode($json));
	}

	public function remove(InstallTarget $target, string $resource): void {
		$key = $target->cacheKey($resource);
		if (!$this->cache instanceof NullCache) {
			$this->cache->remove(Application::APP_ID . '-asyncDownloadProgress-' . $key);
			return;
		}

		$file = $this->getFallbackFile($target, false);
		if ($file === null) {
			return;
		}

		try {
			$content = $file->getContent();
			$json = $content !== '' ? json_decode($content, true) : [];
			$json = is_array($json) ? $json : [];
			unset($json[$key]);
			if ($json === []) {
				$file->delete();
			} else {
				$file->putContent(json_encode($json));
			}
		} catch (\Exception $e) {
			$this->logger->warning('Could not update installer progress fallback', [
				'app' => Application::APP_ID,
				'exception' => $e,
			]);
		}
	}

	private function getFallbackFile(InstallTarget $target, bool $create): ?ISimpleFile {
		$folder = $this->dependencyStorage->resourceFolder($target);
		try {
			return $folder->getFile('setup-cache.json');
		} catch (NotFoundException) {
			if (!$create) {
				return null;
			}
			return $folder->newFile('setup-cache.json', '[]');
		}
	}
}
