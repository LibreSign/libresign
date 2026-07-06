<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Files;

use OCP\Util;

class TemplateLoaderAssets {
	public function addInitScript(string $appId, string $script): void {
		Util::addInitScript($appId, $script);
	}

	public function addScript(string $appId, string $script): void {
		Util::addScript($appId, $script);
	}

	public function addStyle(string $appId, string $style): void {
		Util::addStyle($appId, $style);
	}
}