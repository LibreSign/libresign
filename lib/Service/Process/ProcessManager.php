<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Process;

use OCA\Libresign\AppInfo\Application;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

class ProcessManager {
	private const APP_CONFIG_KEY = 'process_registry';

	public function __construct(
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @param array<string, scalar> $context
	 */
	public function register(string $source, int $pid, array $context = []): void {
		if ($pid <= 0) {
			return;
		}

		$registry = $this->getRegistry();
		$registry[$source][(string)$pid] = [
			'pid' => $pid,
			'context' => $context,
			'createdAt' => time(),
		];
		$this->saveRegistry($registry);
	}

	public function unregister(string $source, int $pid): void {
		$registry = $this->getRegistry();
		unset($registry[$source][(string)$pid]);
		$this->saveRegistry($registry);
	}

	/**
	 * @return array<int, array{pid: int, context: array<string, scalar>, createdAt: int}>
	 */
	public function listRunning(string $source): array {
		$registry = $this->getRegistry();
		$entries = $registry[$source] ?? [];
		$running = [];
		$changed = false;

		foreach ($entries as $pidKey => $entry) {
			$pid = (int)($entry['pid'] ?? 0);
			if ($pid <= 0 || !$this->isRunning($pid)) {
				unset($registry[$source][$pidKey]);
				$changed = true;
				continue;
			}

			$running[] = [
				'pid' => $pid,
				'context' => is_array($entry['context'] ?? null) ? $entry['context'] : [],
				'createdAt' => (int)($entry['createdAt'] ?? 0),
			];
		}

		if ($changed) {
			$this->saveRegistry($registry);
		}

		return $running;
	}

	public function countRunning(string $source): int {
		return count($this->listRunning($source));
	}

	/**
	 * @param null|callable(array{pid: int, context: array<string, scalar>, createdAt: int}): bool $filter
	 */
	public function findRunningPid(string $source, ?callable $filter = null): int {
		foreach ($this->listRunning($source) as $entry) {
			if ($filter !== null && !$filter($entry)) {
				continue;
			}
			return $entry['pid'];
		}

		return 0;
	}

	public function stopAll(string $source, int $signal = SIGTERM): int {
		$stopped = 0;
		foreach ($this->listRunning($source) as $entry) {
			if ($this->terminate($entry['pid'], $signal)) {
				$stopped++;
			}
			$this->unregister($source, $entry['pid']);
		}

		return $stopped;
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

	protected function terminate(int $pid, int $signal): bool {
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

	/**
	 * @return array<string, array<string, array{pid: int, context: array<string, scalar>, createdAt: int}>>
	 */
	private function getRegistry(): array {
		$raw = $this->appConfig->getValueString(Application::APP_ID, self::APP_CONFIG_KEY, '{}');
		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return [];
		}

		return $decoded;
	}

	/**
	 * @param array<string, array<string, array{pid: int, context: array<string, scalar>, createdAt: int}>> $registry
	 */
	private function saveRegistry(array $registry): void {
		$this->appConfig->setValueString(Application::APP_ID, self::APP_CONFIG_KEY, json_encode($registry));
	}
}
