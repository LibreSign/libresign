<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Worker;

/** Linux process identity is local to a PID namespace, never a cluster lock. */
class LocalWorkerProcess {
	public function isSupported(): bool {
		return PHP_OS_FAMILY === 'Linux' && function_exists('flock') && is_dir('/proc');
	}

	public function startTime(int $pid): ?string {
		if ($pid <= 0) {
			return null;
		}
		$stat = @file_get_contents('/proc/' . $pid . '/stat');
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
			|| (function_exists('posix_geteuid') && @fileowner('/proc/' . $pid) !== posix_geteuid())) {
			return false;
		}
		$cmd = @file_get_contents('/proc/' . $pid . '/cmdline');
		if (!is_string($cmd) || $cmd === '') {
			return false;
		}
		$argv = explode("\0", trim($cmd, "\0"));
		return in_array('background-job:worker', $argv, true)
			&& count(array_filter($argv, static fn (string $arg): bool => $arg === 'occ' || str_ends_with($arg, '/occ'))) > 0;
	}
}
