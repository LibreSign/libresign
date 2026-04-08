<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Process;

use Psr\Log\LoggerInterface;

class ProcessSignaler {
	public function __construct(
		private LoggerInterface $logger,
	) {
	}

	public function isRunning(int $pid): bool {
		if ($pid <= 0) {
			return false;
		}

		if (function_exists('posix_kill')) {
			return @posix_kill($pid, 0);
		}

		if (!function_exists('exec')) {
			return false;
		}

		try {
			exec(sprintf('kill -0 %d 2>/dev/null', $pid), $output, $exitCode);
			return $exitCode === 0;
		} catch (\Throwable $e) {
			$this->logger->debug('Failed to probe process status', [
				'pid' => $pid,
				'error' => $e->getMessage(),
			]);
			return false;
		}
	}

	public function stopPid(int $pid, int $signal = SIGTERM): bool {
		if ($pid <= 0) {
			return false;
		}

		if (function_exists('posix_kill')) {
			return @posix_kill($pid, $signal);
		}

		if (!function_exists('exec')) {
			return false;
		}

		try {
			exec(sprintf('kill -%d %d 2>/dev/null', $signal, $pid), $output, $exitCode);
			return $exitCode === 0;
		} catch (\Throwable $e) {
			$this->logger->debug('Failed to terminate process', [
				'pid' => $pid,
				'signal' => $signal,
				'error' => $e->getMessage(),
			]);
			return false;
		}
	}

}
