<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace LibreSign\Release;

interface CommandRunner {
	/**
	 * @param list<string> $command
	 */
	public function run(array $command, ?string $cwd = null): string;
}
