<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit;

use OCP\Exceptions\AppConfigTypeConflictException;
use OCP\IAppConfig;

final class InMemoryAppConfig implements IAppConfig {
	/** @var array<string, array<string, string|int|float|bool|array>> */
	private array $values = [];

	public function reset(): void {
		$this->values = [];
	}

	public function getApps(): array {
		return array_keys($this->values);
	}

	public function getKeys(string $app): array {
		return array_keys($this->values[$app] ?? []);
	}

	public function searchKeys(string $app, string $prefix = '', bool $lazy = false): array {
		return array_values(array_filter(
			$this->getKeys($app),
			static fn (string $key): bool => str_starts_with($key, $prefix),
		));
	}

	public function hasKey(string $app, string $key, ?bool $lazy = false): bool {
		return array_key_exists($key, $this->values[$app] ?? []);
	}

	public function isSensitive(string $app, string $key, ?bool $lazy = false): bool {
		return false;
	}

	public function isLazy(string $app, string $key): bool {
		return false;
	}

	public function getAllValues(string $app, string $prefix = '', bool $filtered = false): array {
		$return = [];
		foreach ($this->values[$app] ?? [] as $key => $value) {
			if (str_starts_with($key, $prefix)) {
				$return[$key] = $value;
			}
		}
		return $return;
	}

	public function searchValues(string $key, bool $lazy = false, ?int $typedAs = null): array {
		$return = [];
		foreach ($this->values as $app => $values) {
			if (array_key_exists($key, $values)) {
				$return[$app] = $values[$key];
			}
		}
		return $return;
	}

	public function getValueString(string $app, string $key, string $default = '', bool $lazy = false): string {
		$value = $this->getTypedValue($app, $key, $default, 'string');
		return $value;
	}

	public function getValueInt(string $app, string $key, int $default = 0, bool $lazy = false): int {
		$value = $this->getTypedValue($app, $key, $default, 'integer');
		return $value;
	}

	public function getValueFloat(string $app, string $key, float $default = 0, bool $lazy = false): float {
		$value = $this->getTypedValue($app, $key, $default, 'double');
		return $value;
	}

	public function getValueBool(string $app, string $key, bool $default = false, bool $lazy = false): bool {
		$value = $this->getTypedValue($app, $key, $default, 'boolean');
		return $value;
	}

	public function getValueArray(string $app, string $key, array $default = [], bool $lazy = false): array {
		$value = $this->getTypedValue($app, $key, $default, 'array');
		return $value;
	}

	public function getValueType(string $app, string $key, ?bool $lazy = null): int {
		$value = $this->values[$app][$key] ?? null;
		return match (true) {
			is_string($value) => self::VALUE_STRING,
			is_int($value) => self::VALUE_INT,
			is_float($value) => self::VALUE_FLOAT,
			is_bool($value) => self::VALUE_BOOL,
			is_array($value) => self::VALUE_ARRAY,
			default => self::VALUE_MIXED,
		};
	}

	public function setValueString(string $app, string $key, string $value, bool $lazy = false, bool $sensitive = false): bool {
		return $this->setValue($app, $key, $value);
	}

	public function setValueInt(string $app, string $key, int $value, bool $lazy = false, bool $sensitive = false): bool {
		return $this->setValue($app, $key, $value);
	}

	public function setValueFloat(string $app, string $key, float $value, bool $lazy = false, bool $sensitive = false): bool {
		return $this->setValue($app, $key, $value);
	}

	public function setValueBool(string $app, string $key, bool $value, bool $lazy = false): bool {
		return $this->setValue($app, $key, $value);
	}

	public function setValueArray(string $app, string $key, array $value, bool $lazy = false, bool $sensitive = false): bool {
		return $this->setValue($app, $key, $value);
	}

	public function updateSensitive(string $app, string $key, bool $sensitive): bool {
		return $this->hasKey($app, $key);
	}

	public function updateLazy(string $app, string $key, bool $lazy): bool {
		return $this->hasKey($app, $key);
	}

	public function getDetails(string $app, string $key): array {
		return [
			'app' => $app,
			'key' => $key,
			'value' => $this->values[$app][$key] ?? null,
			'lazy' => false,
			'type' => $this->getValueType($app, $key),
			'typeString' => $this->convertTypeToString($this->getValueType($app, $key)),
			'sensitive' => false,
		];
	}

	public function getKeyDetails(string $app, string $key): array {
		return [
			'app' => $app,
			'key' => $key,
			'lazy' => false,
			'valueType' => null,
			'valueTypeName' => $this->hasKey($app, $key) ? $this->convertTypeToString($this->getValueType($app, $key)) : null,
			'sensitive' => false,
			'internal' => false,
		];
	}

	public function convertTypeToInt(string $type): int {
		return match ($type) {
			'string' => self::VALUE_STRING,
			'integer', 'int' => self::VALUE_INT,
			'float' => self::VALUE_FLOAT,
			'boolean', 'bool' => self::VALUE_BOOL,
			'array' => self::VALUE_ARRAY,
			default => self::VALUE_MIXED,
		};
	}

	public function convertTypeToString(int $type): string {
		return match ($type) {
			self::VALUE_STRING => 'string',
			self::VALUE_INT => 'integer',
			self::VALUE_FLOAT => 'float',
			self::VALUE_BOOL => 'bool',
			self::VALUE_ARRAY => 'array',
			default => 'mixed',
		};
	}

	public function deleteKey(string $app, string $key): void {
		unset($this->values[$app][$key]);
		if (($this->values[$app] ?? []) === []) {
			unset($this->values[$app]);
		}
	}

	public function deleteApp(string $app): void {
		unset($this->values[$app]);
	}

	public function clearCache(bool $reload = false): void {
	}

	public function getValues($app, $key) {
		if ($app === false) {
			return $this->searchValues((string)$key);
		}
		if ($key === false) {
			return $this->getAllValues((string)$app);
		}
		return $this->values[(string)$app][(string)$key] ?? false;
	}

	public function getFilteredValues($app) {
		return $this->getAllValues((string)$app);
	}

	public function getAppInstalledVersions(bool $onlyEnabled = false): array {
		return [];
	}

	/**
	 * @template T of string|int|float|bool|array
	 * @param T $default
	 * @return T
	 */
	private function getTypedValue(string $app, string $key, mixed $default, string $expectedType): mixed {
		if (!array_key_exists($key, $this->values[$app] ?? [])) {
			return $default;
		}

		$value = $this->values[$app][$key];
		if (gettype($value) !== $expectedType) {
			throw new AppConfigTypeConflictException(
				sprintf('App config value %s/%s is not of type %s', $app, $key, $expectedType),
			);
		}

		return $value;
	}

	private function setValue(string $app, string $key, string|int|float|bool|array $value): bool {
		$changed = !array_key_exists($key, $this->values[$app] ?? [])
			|| $this->values[$app][$key] !== $value;
		$this->values[$app][$key] = $value;
		return $changed;
	}
}
