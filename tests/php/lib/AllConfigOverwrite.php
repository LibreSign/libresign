<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\lib;

use OC\AllConfig;
use OC\SystemConfig;

class AllConfigOverwrite extends AllConfig {
	/** @var string[] */
	private array $overWrite = [];

	public function __construct(
		SystemConfig $systemConfig,
	) {
		parent::__construct($systemConfig);
	}

	public function getAppValue($appName, $key, $default = '') {
		if (isset($this->overWrite[$appName]) && isset($this->overWrite[$appName][$key])) {
			return $this->overWrite[$appName][$key];
		}

		return parent::getAppValue($appName, $key, $default);
	}

	public function setAppValue($appName, $key, $value) {
		$this->overWrite[$appName][$key] = $value;
	}
}
