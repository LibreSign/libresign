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
	private const APP_CONFIG_HINTS_KEY = 'process_registry_hints';

	private ProcessSignaler $signaler;
	private ListeningPidResolver $pidResolver;

	public function __construct(
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
		?ProcessSignaler $signaler = null,
		?ListeningPidResolver $pidResolver = null,
	) {
		$this->signaler = $signaler ?? new ProcessSignaler($logger);
		$this->pidResolver = $pidResolver ?? new ListeningPidResolver();
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
		$this->setSourceHint($source, $context);
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
		$entries = $this->listRunning($source);
		foreach ($entries as $entry) {
			if ($filter !== null && !$filter($entry)) {
				continue;
			}
			return $entry['pid'];
		}

		$this->hydrateFromFallback($source);

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
			if ($this->stopPid($entry['pid'], $signal)) {
				$stopped++;
			}
			$this->unregister($source, $entry['pid']);
		}

		return $stopped;
	}

	public function stopPid(int $pid, int $signal = SIGTERM): bool {
		return $this->signaler->stopPid($pid, $signal);
	}

	/**
	 * @param array<string, scalar> $context
	 */
	public function setSourceHint(string $source, array $context): void {
		if ($source === '' || $context === []) {
			return;
		}

		$hints = $this->getHints();
		$hints[$source] = $context;
		$this->saveHints($hints);
	}

	/**
	 * @return int[]
	 */
	public function findListeningPids(int $port): array {
		return $this->pidResolver->findListeningPids($port);
	}

	public function isRunning(int $pid): bool {
		return $this->signaler->isRunning($pid);
	}

	private function hydrateFromFallback(string $source): void {
		$hint = $this->getSourceHint($source);
		if (!is_array($hint) || $hint === []) {
			return;
		}

		$port = $this->getPortFromHint($hint);
		if ($port <= 0) {
			return;
		}

		try {
			$pids = $this->findListeningPids($port);
		} catch (\RuntimeException $e) {
			$this->logger->debug('Unable to hydrate process registry from fallback', [
				'source' => $source,
				'port' => $port,
				'error' => $e->getMessage(),
			]);
			return;
		}

		foreach ($pids as $pid) {
			if ($pid <= 0) {
				continue;
			}
			$this->register($source, $pid, $hint);
		}
	}

	/**
	 * @param array<string, scalar> $hint
	 */
	private function getPortFromHint(array $hint): int {
		if (isset($hint['port']) && is_numeric((string)$hint['port'])) {
			$port = (int)$hint['port'];
			if ($port > 0) {
				return $port;
			}
		}

		if (isset($hint['uri']) && is_string($hint['uri'])) {
			return (int)(parse_url($hint['uri'], PHP_URL_PORT) ?? 0);
		}

		return 0;
	}

	/**
	 * @return array<string, scalar>
	 */
	private function getSourceHint(string $source): array {
		$hints = $this->getHints();
		$hint = $hints[$source] ?? [];
		return is_array($hint) ? $hint : [];
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

	/**
	 * @return array<string, array<string, scalar>>
	 */
	private function getHints(): array {
		$raw = $this->appConfig->getValueString(Application::APP_ID, self::APP_CONFIG_HINTS_KEY, '{}');
		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return [];
		}

		return $decoded;
	}

	/**
	 * @param array<string, array<string, scalar>> $hints
	 */
	private function saveHints(array $hints): void {
		$this->appConfig->setValueString(Application::APP_ID, self::APP_CONFIG_HINTS_KEY, json_encode($hints));
	}

}
