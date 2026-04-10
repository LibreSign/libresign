<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Policy\Provider\Footer\SignatureFooterPolicyValue;
use OCP\DB\ISchemaWrapper;
use OCP\Exceptions\AppConfigTypeConflictException;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version18002Date20260410000000 extends SimpleMigrationStep {
	private const APP_ID = Application::APP_ID;

	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	#[\Override]
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->migrateLegacyFooterSettings();
	}

	private function migrateLegacyFooterSettings(): void {
		$legacyAddFooter = $this->readLegacyValue('add_footer');
		$legacyWriteQrCodeOnFooter = $this->readLegacyBool('write_qrcode_on_footer', true);
		$legacyValidationSite = $this->readLegacyString('validation_site', '');
		$legacyFooterTemplateIsDefault = $this->readLegacyBool('footer_template_is_default', true);

		$rawFooterPolicyValue = $legacyAddFooter;
		if (!$this->isStructuredFooterPayload($legacyAddFooter)) {
			$rawFooterPolicyValue = [
				'enabled' => $this->toBool($legacyAddFooter, true),
				'writeQrcodeOnFooter' => $legacyWriteQrCodeOnFooter,
				'validationSite' => $legacyValidationSite,
				'customizeFooterTemplate' => !$legacyFooterTemplateIsDefault,
			];
		}

		$encodedFooterPolicyValue = SignatureFooterPolicyValue::encode(
			SignatureFooterPolicyValue::normalize($rawFooterPolicyValue),
		);

		$this->appConfig->deleteKey(self::APP_ID, 'add_footer');
		$this->appConfig->setValueString(self::APP_ID, 'add_footer', $encodedFooterPolicyValue);
	}

	private function isStructuredFooterPayload(mixed $value): bool {
		if (!is_string($value)) {
			return false;
		}

		$decoded = json_decode($value, true);
		if (!is_array($decoded)) {
			return false;
		}

		return array_key_exists('enabled', $decoded)
			|| array_key_exists('writeQrcodeOnFooter', $decoded)
			|| array_key_exists('validationSite', $decoded)
			|| array_key_exists('customizeFooterTemplate', $decoded);
	}

	private function readLegacyValue(string $key): mixed {
		try {
			return $this->appConfig->getValueString(self::APP_ID, $key, '');
		} catch (AppConfigTypeConflictException) {
			return $this->appConfig->getValueBool(self::APP_ID, $key, true);
		}
	}

	private function readLegacyBool(string $key, bool $default): bool {
		try {
			$rawValue = $this->appConfig->getValueString(self::APP_ID, $key, '');
			if ($rawValue === '') {
				return $default;
			}

			return in_array(strtolower(trim($rawValue)), ['1', 'true', 'yes', 'on'], true);
		} catch (AppConfigTypeConflictException) {
			return $this->appConfig->getValueBool(self::APP_ID, $key, $default);
		}
	}

	private function readLegacyString(string $key, string $default): string {
		try {
			return trim($this->appConfig->getValueString(self::APP_ID, $key, $default));
		} catch (AppConfigTypeConflictException) {
			return $default;
		}
	}

	private function toBool(mixed $value, bool $default): bool {
		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value)) {
			return $value === 1;
		}

		if (is_string($value)) {
			$trimmed = trim($value);
			if ($trimmed === '') {
				return $default;
			}

			return in_array(strtolower($trimmed), ['1', 'true', 'yes', 'on'], true);
		}

		return $default;
	}

	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		return null;
	}
}
