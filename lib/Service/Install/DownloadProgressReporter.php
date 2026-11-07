<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Install;

/**
 * Coalesce frequent transfer callbacks without hiding progress from other PHP processes.
 * The progress store remains the cross-process source of truth (cache or AppData).
 */
final class DownloadProgressReporter {
	private const UPDATE_INTERVAL_SECONDS = 0.5;
	private readonly \Closure $clock;
	/** @var array{download_size: int, downloaded: int}|null */
	private ?array $pending = null;
	/** @var array{download_size: int, downloaded: int}|null */
	private ?array $persisted = null;
	private ?float $lastPublishedAt = null;

	public function __construct(
		private readonly InstallProgressStore $store,
		private readonly InstallTarget $target,
		private readonly string $resource,
		?\Closure $clock = null,
	) {
		$this->clock = $clock ?? static fn (): float => microtime(true);
	}

	public function report(int $downloadSize, int $downloaded): void {
		$this->pending = ['download_size' => $downloadSize, 'downloaded' => $downloaded];
		$now = ($this->clock)();
		if ($this->lastPublishedAt === null
			|| $now - $this->lastPublishedAt >= self::UPDATE_INTERVAL_SECONDS
			|| ($downloadSize > 0 && $downloaded >= $downloadSize)) {
			$this->flushAt($now);
		}
	}

	public function flush(): void {
		$this->flushAt(($this->clock)());
	}

	private function flushAt(float $now): void {
		if ($this->pending === null || $this->pending === $this->persisted) {
			return;
		}
		// Retain metadata such as the async process PID or a previously stored error.
		$data = $this->store->get($this->target, $this->resource);
		$this->store->set($this->target, $this->resource, array_merge($data, $this->pending));
		$this->persisted = $this->pending;
		$this->lastPublishedAt = $now;
	}
}
