<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace LibreSign\Release;

final class NativeCommandRunner implements CommandRunner {
	public function run(array $command, ?string $cwd = null): string {
		$process = proc_open(
			$command,
			[
				0 => ['file', '/dev/null', 'r'],
				1 => ['pipe', 'w'],
				2 => ['pipe', 'w'],
			],
			$pipes,
			$cwd,
		);

		if (!is_resource($process)) {
			throw new \RuntimeException('Unable to start command');
		}

		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		$status = proc_close($process);
		if ($status !== 0) {
			throw new \RuntimeException(sprintf(
				'Command failed (%d): %s%s',
				$status,
				implode(' ', $command),
				$stderr !== '' ? "\n" . trim($stderr) : '',
			));
		}

		return trim((string)$stdout);
	}
}
