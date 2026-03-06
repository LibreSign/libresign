<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Mock;

class ExecMock {
	public static array $commands = [];

	public static function exec(string $command, &$output = null, &$result_code = null): string|false {
		if (array_key_exists($command, self::$commands)) {
			$mock = self::$commands[$command];
			$output = $mock['output'];
			$result_code = $mock['result_code'];
			return $output ? end($mock['output']) : '';
		}
		return false;
	}
}
