<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\lib;

use Psr\Log\AbstractLogger;

/**
 * Records every log call so tests can assert on what AppConfig actually
 * logged, instead of only on the exceptions it throws.
 */
final class RecordingLogger extends AbstractLogger {
	/** @var list<array{level: mixed, message: string, context: array}> */
	private array $records = [];

	public function log($level, $message, array $context = []): void {
		$this->records[] = [
			'level' => $level,
			'message' => (string)$message,
			'context' => $context,
		];
	}

	public function hasRecordMatching(string $needle, ?string $level = null): bool {
		foreach ($this->records as $record) {
			if ($level !== null && $record['level'] !== $level) {
				continue;
			}
			if (str_contains($record['message'], $needle)) {
				return true;
			}
		}

		return false;
	}

	/** @return list<array{level: mixed, message: string, context: array}> */
	public function getRecords(): array {
		return $this->records;
	}

	public function reset(): void {
		$this->records = [];
	}
}
