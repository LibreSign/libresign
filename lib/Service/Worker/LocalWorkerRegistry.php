<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Worker;

use Psr\Log\LoggerInterface;

/**
 * A local POSIX lock and PID identity registry, scoped to the PHP process namespace.
 * Never store host PIDs in Nextcloud appconfig: it can be shared by other servers.
 * This is on-demand start coordination, not a guarantee of supervision.
 */
class LocalWorkerRegistry {
	private const int START_COOLDOWN_SECONDS = 2;

	public function __construct(
		private WorkerStarter $starter,
		private WorkerJobCounter $jobCounter,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Starts at most $desired local workers, never more than there are queued jobs.
	 * The queue is consulted only if the local process count is below capacity.
	 * The caller has already verified that local async mode is enabled.
	 */
	public function ensureCapacity(int $desired): bool {
		if ($desired <= 0) {
			return true;
		}
		if (PHP_OS_FAMILY !== 'Linux' || !function_exists('flock')) {
			$this->logger->warning('Local workers require Linux process identifiers and file locking; use Nextcloud cron or dedicated workers');
			return false;
		}
		$directory = $this->registryDirectory();
		if (!is_dir($directory) && !@mkdir($directory, 0700) && !is_dir($directory)) {
			throw new \RuntimeException('Cannot create local worker registry');
		}
		if (is_link($directory)
			|| (function_exists('posix_geteuid') && fileowner($directory) !== posix_geteuid())
			|| ((int)fileperms($directory) & 0077) !== 0) {
			throw new \RuntimeException('Unsafe local worker registry permissions');
		}
		$lockFile = $directory . '/lock';
		if (is_link($lockFile)) {
			throw new \RuntimeException('Unsafe local worker lock');
		}
		$handle = @fopen($lockFile, 'c+');
		if ($handle === false) {
			throw new \RuntimeException('Cannot open local worker lock');
		}
		$lockStat = fstat($handle);
		if (!is_file($lockFile) || (function_exists('posix_geteuid') && ($lockStat['uid'] ?? null) !== posix_geteuid())) {
			fclose($handle);
			throw new \RuntimeException('Unsafe local worker lock ownership');
		}
		@chmod($lockFile, 0600);
		if (!flock($handle, LOCK_EX | LOCK_NB)) {
			fclose($handle);
			// Another request is starting workers. Do not launch duplicates.
			return true;
		}
		try {
			$file = $directory . '/workers.json';
			$data = json_decode((string)@file_get_contents($file), true);
			if (!is_array($data)) {
				$data = [];
			}
			$running = [];
			foreach (($data['workers'] ?? []) as $entry) {
				if (is_array($entry) && $this->ownsRunningWorker((int)($entry['pid'] ?? 0), (string)($entry['start'] ?? ''))) {
					$running[] = $entry;
				}
			}
			// Avoid re-launching for future, reserved or broken jobs on every HTTP request.
			$cooldown = (float)($data['lastAttempt'] ?? 0);
			$vacancies = max(0, min(32, $desired) - count($running));
			$toStart = 0;
			if ($vacancies > 0 && microtime(true) - $cooldown >= self::START_COOLDOWN_SECONDS) {
				// Read demand only when there is room to start a worker. In
				// particular, progress polling does not query the whole queue.
				$pending = $this->jobCounter->countEligibleJobs($vacancies);
				$toStart = min($vacancies, $pending);
				if ($toStart > 0) {
					$data['lastAttempt'] = microtime(true);
				}
				for ($i = 0; $i < $toStart; $i++) {
					try {
						$pid = $this->starter->startWorker();
						$start = $this->pidStartTime($pid);
						if ($start === null) {
							$this->logger->warning('Worker PID identity could not be recorded', ['pid' => $pid]);
							continue;
						}
						$running[] = ['pid' => $pid, 'start' => $start];
					} catch (\Throwable $e) {
						$this->logger->warning('Could not start a local signing worker', ['exception' => $e]);
						break;
					}
				}
			}
			$data['workers'] = $running;
			// The file is local to this process namespace and protected by the lock.
			$encoded = json_encode($data, JSON_THROW_ON_ERROR);
			// An interrupted PHP request must never leave a truncated registry.
			$temporary = tempnam($directory, 'workers-');
			if ($temporary === false) {
				throw new \RuntimeException('Cannot allocate local worker registry update');
			}
			try {
				if (file_put_contents($temporary, $encoded) === false || !rename($temporary, $file)) {
					throw new \RuntimeException('Cannot write local worker registry');
				}
			} finally {
				@unlink($temporary);
			}
			return count($running) > 0 || $toStart === 0;
		} finally {
			flock($handle, LOCK_UN);
			fclose($handle);
		}
	}

	protected function registryDirectory(): string {
		// /tmp must remain node-local and visible to the PHP-FPM process namespace.
		$root = (string)\OC::$SERVERROOT;
		$uid = function_exists('posix_geteuid') ? (string)posix_geteuid() : 'php';
		$namespace = (string)@readlink('/proc/self/ns/pid');
		$boot = (string)@file_get_contents('/proc/sys/kernel/random/boot_id');
		$host = (string)gethostname();
		$scope = implode(':', [$root, $uid, $host, $namespace, $boot]);
		return rtrim(sys_get_temp_dir(), '/') . '/libresign-workers-' . substr(hash('sha256', $scope), 0, 24);
	}

	protected function ownsRunningWorker(int $pid, string $started): bool {
		if ($pid <= 0 || $started === '' || $this->pidStartTime($pid) !== $started) {
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

	protected function pidStartTime(int $pid): ?string {
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
		// /proc/[pid]/stat field 22 is index 19 after state (field 3).
		return $fields[19] ?? null;
	}
}
