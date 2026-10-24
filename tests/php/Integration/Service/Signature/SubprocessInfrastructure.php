<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 rodrigodev
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Signature;

use OCP\IAppConfig;
use OCP\IL10N;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;

/**
 * Minimal infrastructure implementations so a bare PHP subprocess can
 * construct LibreSign's real ExternalPdfSigningService with its real
 * validation dependency outside a booted Nextcloud instance.
 *
 * These are dependency-injection plumbing only: the validation service,
 * the CMS engine, the DocMDP handler and the complete() lifecycle are the
 * real production code paths. Empty configuration simply means no trusted
 * root is preloaded, which matches the synthetic test CA setup.
 */
final class SubprocessAppConfig implements IAppConfig {
	public function getApps(): array {
		return [];
	}

	public function getKeys(string $app): array {
		return [];
	}

	public function searchKeys(string $app, string $prefix = '', bool $lazy = false): array {
		return [];
	}

	public function hasKey(string $app, string $key, ?bool $lazy = false): bool {
		return false;
	}

	public function isSensitive(string $app, string $key, ?bool $lazy = false): bool {
		return false;
	}

	public function isLazy(string $app, string $key): bool {
		return false;
	}

	public function getAllValues(string $app, string $prefix = '', bool $filtered = false): array {
		return [];
	}

	public function searchValues(string $key, bool $lazy = false, ?int $typedAs = null): array {
		return [];
	}

	public function getValueString(string $app, string $key, string $default = '', bool $lazy = false): string {
		return $default;
	}

	public function getValueInt(string $app, string $key, int $default = 0, bool $lazy = false): int {
		return $default;
	}

	public function getValueFloat(string $app, string $key, float $default = 0, bool $lazy = false): float {
		return $default;
	}

	public function getValueBool(string $app, string $key, bool $default = false, bool $lazy = false): bool {
		return $default;
	}

	public function getValueArray(string $app, string $key, array $default = [], bool $lazy = false): array {
		return $default;
	}

	public function getValueType(string $app, string $key, ?bool $lazy = null): int {
		return 0;
	}

	public function setValueString(string $app, string $key, string $value, bool $lazy = false, bool $sensitive = false): bool {
		return true;
	}

	public function setValueInt(string $app, string $key, int $value, bool $lazy = false, bool $sensitive = false): bool {
		return true;
	}

	public function setValueFloat(string $app, string $key, float $value, bool $lazy = false, bool $sensitive = false): bool {
		return true;
	}

	public function setValueBool(string $app, string $key, bool $value, bool $lazy = false): bool {
		return true;
	}

	public function setValueArray(string $app, string $key, array $value, bool $lazy = false, bool $sensitive = false): bool {
		return true;
	}

	public function updateSensitive(string $app, string $key, bool $sensitive): bool {
		return true;
	}

	public function updateLazy(string $app, string $key, bool $lazy): bool {
		return true;
	}

	public function getDetails(string $app, string $key): array {
		return [];
	}

	public function getKeyDetails(string $app, string $key): array {
		return [];
	}

	public function convertTypeToInt(string $type): int {
		return 0;
	}

	public function convertTypeToString(int $type): string {
		return 'string';
	}

	public function deleteKey(string $app, string $key): void {
	}

	public function deleteApp(string $app): void {
	}

	public function clearCache(bool $reload = false): void {
	}

	public function getValues($app, $key) {
		return [];
	}

	public function getFilteredValues($app) {
		return [];
	}

	public function getAppInstalledVersions(bool $onlyEnabled = false): array {
		return [];
	}
}

final class SubprocessL10n implements IL10N {
	public function t(string $text, $parameters = []): string {
		return $text;
	}

	public function n(string $text_singular, string $text_plural, int $count, array $parameters = []): string {
		return $count === 1 ? $text_singular : $text_plural;
	}

	public function l(string $type, $data, array $options = []) {
		return $data;
	}

	public function getLanguageCode(): string {
		return 'en';
	}

	public function getLocaleCode(): string {
		return 'en';
	}
}

final class SubprocessLogger extends AbstractLogger implements LoggerInterface {
	public function log($level, Stringable|string $message, array $context = []): void {
	}
}
