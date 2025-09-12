<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\lib;

use OC\AppConfig;
use OC\Config\ConfigManager;
use OC\Config\PresetManager;
use OC\Memcache\Factory as CacheFactory;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

class AppConfigOverwrite extends AppConfig {
	/** @var string|bool|array|float|int[][] */
	private array $overWrite = [];
	private array $deleted = [];

	public function __construct(
		IDBConnection $connection,
		IConfig $config,
		ConfigManager $configManager,
		PresetManager $presetManager,
		LoggerInterface $logger,
		ICrypto $crypto,
		CacheFactory $cacheFactory,
	) {
		parent::__construct(
			$connection,
			$config,
			$configManager,
			$presetManager,
			$logger,
			$crypto,
			$cacheFactory,
		);
	}

	public function getValueMixed(
		string $app,
		string $key,
		string $default = '',
		?bool $lazy = false,
	): string {
		return $this->getOverwrite(
			$app,
			$key,
			$default,
			fn () => parent::getValue($app, $key, (string)$default) // AppConfig::getValue retorna string
		);
	}

	public function setValueMixed(
		string $app,
		string $key,
		string $value,
		bool $lazy = false,
		bool $sensitive = false,
	): bool {
		return $this->setOverwrite($app, $key, $value);
	}

	public function hasKey(string $app, string $key, ?bool $lazy = false): bool {
		if ($this->isDeleted($app, $key)) {
			return false;
		}
		if (isset($this->overWrite[$app]) && array_key_exists($key, $this->overWrite[$app])) {
			return true;
		}
		return parent::hasKey($app, $key, $lazy);
	}

	public function getValueArray(string $app, string $key, array $default = [], bool $lazy = false): array {
		return $this->getOverwrite(
			$app,
			$key,
			$default,
			fn () => parent::getValueArray($app, $key, $default),
		);
	}

	public function setValueArray(string $app, string $key, array $value, bool $lazy = false, bool $sensitive = false): bool {
		return $this->setOverwrite($app, $key, $value);
	}

	public function getValueBool(string $app, string $key, bool $default = false, bool $lazy = false): bool {
		return $this->getOverwrite(
			$app,
			$key,
			$default,
			fn () => parent::getValueBool($app, $key, $default)
		);
	}

	public function setValueBool(string $app, string $key, bool $value, bool $lazy = false): bool {
		return $this->setOverwrite($app, $key, $value);
	}

	public function getValueString(string $app, string $key, string $default = '', bool $lazy = false): string {
		return $this->getOverwrite(
			$app,
			$key,
			$default,
			fn () => parent::getValueString($app, $key, $default)
		);
	}

	public function setValueString(string $app, string $key, string $value, bool $lazy = false, bool $sensitive = false): bool {
		return $this->setOverwrite($app, $key, $value);
	}

	public function getValueInt(string $app, string $key, int $default = 0, bool $lazy = false): int {
		return $this->getOverwrite(
			$app,
			$key,
			$default,
			fn () => parent::getValueInt($app, $key, $default)
		);
	}

	public function setValueInt(string $app, string $key, int $value, bool $lazy = false, bool $sensitive = false): bool {
		return $this->setOverwrite($app, $key, $value);
	}

	public function deleteKey(string $app, string $key): void {
		if (isset($this->overWrite[$app])) {
			unset($this->overWrite[$app][$key]);
			if (empty($this->overWrite[$app])) {
				unset($this->overWrite[$app]);
			}
		}
		$this->markDeleted($app, $key);
	}

	private function isDeleted(string $app, string $key): bool {
		return isset($this->deleted[$app][$key]);
	}

	private function markDeleted(string $app, string $key): void {
		$this->deleted[$app][$key] = true;
	}

	private function clearDeleted(string $app, string $key): void {
		if (isset($this->deleted[$app][$key])) {
			unset($this->deleted[$app][$key]);
			if (empty($this->deleted[$app])) {
				unset($this->deleted[$app]);
			}
		}
	}

	private function setOverwrite(string $app, string $key, mixed $value): bool {
		$this->overWrite[$app][$key] = $value;
		$this->clearDeleted($app, $key);
		return true;
	}

	private function getOverwrite(string $app, string $key, mixed $default, callable $parentGetter): mixed {
		if ($this->isDeleted($app, $key)) {
			return $default;
		}
		if (isset($this->overWrite[$app]) && array_key_exists($key, $this->overWrite[$app])) {
			return $this->overWrite[$app][$key];
		}
		return $parentGetter();
	}
}
