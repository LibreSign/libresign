<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Service\Font;

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Psr\Log\AbstractLogger;

final class InMemoryLogger extends AbstractLogger {
	/**
	 * @var list<array{level: string, message: string, context: array<mixed>}>
	 */
	private array $records = [];

	public function log($level, string|\Stringable $message, array $context = []): void {
		$this->records[] = [
			'level' => (string)$level,
			'message' => (string)$message,
			'context' => $context,
		];
	}

	/**
	 * @return list<array{level: string, message: string, context: array<mixed>}>
	 */
	public function all(): array {
		return $this->records;
	}

	/**
	 * @return list<array{level: string, message: string, context: array<mixed>}>
	 */
	public function warnings(): array {
		return array_values(array_filter(
			$this->records,
			static fn (array $record): bool => $record['level'] === 'warning',
		));
	}
}
