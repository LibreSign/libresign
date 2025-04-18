<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\lib;

use OC\AppConfig;
use OCP\IDBConnection;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

class AppConfigOverwrite extends AppConfig {
	/** @var string|bool|array|float|int[][] */
	private $overWrite = [];

	public function __construct(
		IDBConnection $conn,
		LoggerInterface $logger,
		ICrypto $crypto,
	) {
		parent::__construct($conn, $logger, $crypto);
	}

	public function getValueMixed(
		string $app,
		string $key,
		string $default = '',
		?bool $lazy = false,
	): string {
		if (isset($this->overWrite[$app]) && isset($this->overWrite[$app][$key])) {
			return $this->overWrite[$app][$key];
		}

		return parent::getValue($app, $key, $default);
	}

	public function setValueMixed(
		string $app,
		string $key,
		string $value,
		bool $lazy = false,
		bool $sensitive = false,
	): bool {
		$this->overWrite[$app][$key] = $value;
		return true;
	}

	public function hasKey(string $app, string $key, ?bool $lazy = false): bool {
		if (isset($this->overWrite[$app]) && isset($this->overWrite[$app][$key])) {
			return true;
		}
		return parent::hasKey($app, $key, $lazy);
	}

	public function getValueArray(string $app, string $key, array $default = [], bool $lazy = false): array {
		if (isset($this->overWrite[$app]) && isset($this->overWrite[$app][$key])) {
			return $this->overWrite[$app][$key];
		}

		return parent::getValueArray($app, $key, $default);
	}

	public function setValueArray(string $app, string $key, array $value, bool $lazy = false, bool $sensitive = false): bool {
		$this->overWrite[$app][$key] = $value;
		return true;
	}

	public function setValueBool(string $app, string $key, bool $value, bool $lazy = false): bool {
		$this->overWrite[$app][$key] = $value;
		return true;
	}

	public function getValueBool(string $app, string $key, bool $default = false, bool $lazy = false): bool {
		if (isset($this->overWrite[$app]) && isset($this->overWrite[$app][$key])) {
			return $this->overWrite[$app][$key];
		}

		return parent::getValueBool($app, $key, $default);
	}

	public function getValueString(string $app, string $key, string $default = '', bool $lazy = false): string {
		if (isset($this->overWrite[$app]) && isset($this->overWrite[$app][$key])) {
			return $this->overWrite[$app][$key];
		}

		return parent::getValueString($app, $key, $default);
	}

	public function setValueString(string $app, string $key, string $value, bool $lazy = false, bool $sensitive = false): bool {
		$this->overWrite[$app][$key] = $value;
		return true;
	}

	public function deleteKey(string $app, string $key): void {
		unset($this->overWrite[$app][$key]);
	}
}
