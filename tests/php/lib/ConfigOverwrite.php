<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\lib;

use OC\Config;

class ConfigOverwrite extends Config {
	/** @var string[] */
	private array $overWrite = [];

	public function __construct(
		string $configDir,
	) {
		parent::__construct($configDir);
	}

	public function getValue($key, $default = null) {
		if (isset($this->overWrite) && isset($this->overWrite[$key])) {
			return $this->overWrite[$key];
		}

		return parent::getValue($key, $default);
	}

	public function setValue($key, $value) {
		$this->overWrite[$key] = $value;
	}
}
