<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Worker;

/** Linux process identity is local to a PID namespace, never a cluster lock. */
class LocalWorkerProcess {
	public function __construct(
		private string $procRoot = '/proc',
		private ?string $expectedOccPath = null,
	) {
	}

	public function isSupported(): bool {
		// Avoid spawning workers we cannot identify in later requests when
		// procfs is restricted (for example through PHP open_basedir).
		return PHP_OS_FAMILY === 'Linux' && function_exists('flock')
			&& is_dir($this->procRoot)
			&& @is_readable($this->procRoot . '/self/stat')
			&& @is_readable($this->procRoot . '/self/cmdline');
	}

	public function startTime(int $pid): ?string {
		if ($pid <= 0) {
			return null;
		}
		$stat = @file_get_contents($this->procRoot . '/' . $pid . '/stat');
		if (!is_string($stat)) {
			return null;
		}
		$close = strrpos($stat, ')');
		if ($close === false) {
			return null;
		}
		$fields = preg_split('/\s+/', trim(substr($stat, $close + 1)));
		// Linux proc stat field 22 is index 19 after state (field 3).
		return $fields[19] ?? null;
	}

	public function isRunning(int $pid, string $startTime): bool {
		if ($startTime === '' || $this->startTime($pid) !== $startTime
			|| (function_exists('posix_geteuid') && @fileowner($this->procRoot . '/' . $pid) !== posix_geteuid())) {
			return false;
		}
		$cmd = @file_get_contents($this->procRoot . '/' . $pid . '/cmdline');
		if (!is_string($cmd) || $cmd === '') {
			return false;
		}
		$argv = explode("\0", trim($cmd, "\0"));
		// A PID is not a worker identity: ensure it is the OCC command for this
		// Nextcloud instance, not another process named occ on the same host.
		$expectedOcc = $this->expectedOccPath ?? \OC::$SERVERROOT . '/occ';
		// WorkerStarter always passes [php, this Nextcloud's occ, command].
		// Arbitrary process arguments must not look like a running worker.
		return ($argv[1] ?? null) === $expectedOcc
			&& ($argv[2] ?? null) === 'background-job:worker';
	}
}
