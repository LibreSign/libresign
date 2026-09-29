<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit;

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use PHPUnit\Framework\TestCase;

final class ReleasePackagingMakefileTest extends TestCase {
	public function testGithubActionsConditionUsesMakeVariable(): void {
		$makefile = file_get_contents(dirname(__DIR__, 3) . '/Makefile');

		self::assertNotFalse($makefile);
		self::assertStringContainsString('[ "$(GITHUB_ACTIONS)" = "true" ]', $makefile);
		self::assertStringNotContainsString('${GITHUB_ACTIONS:-}', $makefile);
	}
}
