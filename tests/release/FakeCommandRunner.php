<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace LibreSign\Release\Tests;

use LibreSign\Release\CommandRunner;

final class FakeCommandRunner implements CommandRunner {
	/** @var list<array{command:list<string>,output:string}> */
	private array $responses = [];

	/** @var list<list<string>> */
	public array $commands = [];

	/**
	 * @param list<string> $command
	 */
	public function expect(array $command, string $output = ''): void {
		$this->responses[] = [
			'command' => $command,
			'output' => $output,
		];
	}

	public function run(array $command, ?string $cwd = null): string {
		$this->commands[] = $command;
		$expected = array_shift($this->responses);
		if ($expected === null) {
			throw new \RuntimeException('Unexpected command: ' . implode(' ', $command));
		}
		if ($expected['command'] !== $command) {
			throw new \RuntimeException(sprintf(
				"Command mismatch.\nExpected: %s\nActual: %s",
				implode(' ', $expected['command']),
				implode(' ', $command),
			));
		}
		return $expected['output'];
	}

	public function assertComplete(): void {
		if ($this->responses !== []) {
			throw new \RuntimeException('Not all expected commands were executed');
		}
	}
}
