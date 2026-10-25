<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Worker;

/** Local, atomic state storage. It never supervises or signals PIDs. */
class LocalWorkerStateStore {
	public function __construct(
		private ?string $directory = null,
	) {
	}

	/** @param callable(): bool $operation */
	public function withExclusiveLock(callable $operation): bool {
		$handle = $this->openLock();
		try {
			$wouldBlock = 0;
			if (!flock($handle, LOCK_EX | LOCK_NB, $wouldBlock)) {
				if ($wouldBlock !== 0) {
					return true; // Another request is deciding whether to launch.
				}
				throw new \RuntimeException('Cannot acquire local worker launch lock');
			}
			return $operation();
		} finally {
			// Closing the descriptor releases the flock even on exceptions.
			fclose($handle);
		}
	}

	/** @return resource */
	private function openLock() {
		$this->ensurePrivateDirectory();
		$path = $this->directory() . '/lock';
		if (is_link($path)) {
			throw new \RuntimeException('Unsafe local worker lock');
		}
		$handle = @fopen($path, 'c+');
		if ($handle === false) {
			throw new \RuntimeException('Cannot open local worker lock');
		}
		$stat = fstat($handle);
		if (!is_file($path) || (function_exists('posix_geteuid') && ($stat['uid'] ?? null) !== posix_geteuid())) {
			fclose($handle);
			throw new \RuntimeException('Unsafe local worker lock ownership');
		}
		@chmod($path, 0600);
		return $handle;
	}

	private function ensurePrivateDirectory(): void {
		$directory = $this->directory();
		if (!is_dir($directory) && !@mkdir($directory, 0700) && !is_dir($directory)) {
			throw new \RuntimeException('Cannot create local worker registry');
		}
		if (is_link($directory) || ((int)fileperms($directory) & 0077) !== 0
			|| (function_exists('posix_geteuid') && fileowner($directory) !== posix_geteuid())) {
			throw new \RuntimeException('Unsafe local worker registry permissions');
		}
	}

	/** @return array<string, mixed> */
	public function readState(): array {
		$path = $this->directory() . '/workers.json';
		if (!is_file($path)) {
			return [];
		}
		try {
			$state = json_decode((string)file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			throw new \RuntimeException('Invalid local worker state', previous: $e);
		}
		if (!is_array($state) || !is_array($state['workers'] ?? null) || !array_is_list($state['workers'])) {
			throw new \RuntimeException('Invalid local worker state');
		}
		foreach ($state['workers'] as $worker) {
			if (!is_array($worker) || !is_int($worker['pid'] ?? null) || $worker['pid'] <= 0
				|| !is_string($worker['start'] ?? null) || $worker['start'] === '') {
				// Treat corruption as a launch failure, not as an empty pool that
				// might exceed the configured concurrency limit.
				throw new \RuntimeException('Invalid local worker process record');
			}
		}
		return $state;
	}

	/** @param array<string, mixed> $state */
	public function writeState(array $state): void {
		$file = $this->directory() . '/workers.json';
		$temp = tempnam($this->directory(), 'workers-');
		if ($temp === false) {
			throw new \RuntimeException('Cannot allocate local worker state');
		}
		try {
			if (file_put_contents($temp, json_encode($state, JSON_THROW_ON_ERROR)) === false || !rename($temp, $file)) {
				throw new \RuntimeException('Cannot write local worker state');
			}
		} finally {
			@unlink($temp);
		}
	}

	private function directory(): string {
		if ($this->directory !== null) {
			return $this->directory;
		}
		$scope = implode(':', [
			(string)\OC::$SERVERROOT,
			function_exists('posix_geteuid') ? posix_geteuid() : 'php',
			gethostname(),
			(string)@readlink('/proc/self/ns/pid'),
			(string)@file_get_contents('/proc/sys/kernel/random/boot_id'),
		]);
		return rtrim(sys_get_temp_dir(), '/') . '/libresign-workers-' . substr(hash('sha256', $scope), 0, 24);
	}
}
