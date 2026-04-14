<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Policy\Provider\DocMdp\DocMdpPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicyValue;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicy;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicyValue;
use OCP\DB\ISchemaWrapper;
use OCP\Exceptions\AppConfigTypeConflictException;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version18001Date20260320000000 extends SimpleMigrationStep {
	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	#[\Override]
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->migrateLegacyFooterSettings();
		$this->migrateDocMdpLevelType();
		$this->migrateGroupsRequestSignType();
		$this->migrateIdentifyMethodsType();
	}

	private function migrateGroupsRequestSignType(): void {
		$legacyValue = $this->readLegacyString(RequestSignGroupsPolicy::SYSTEM_APP_CONFIG_KEY);
		if ($legacyValue !== null) {
			if ($legacyValue === '') {
				return;
			}

			$this->appConfig->deleteKey(Application::APP_ID, RequestSignGroupsPolicy::SYSTEM_APP_CONFIG_KEY);
			$this->appConfig->setValueString(
				Application::APP_ID,
				RequestSignGroupsPolicy::SYSTEM_APP_CONFIG_KEY,
				RequestSignGroupsPolicyValue::encode($legacyValue),
			);
			return;
		}

		$typedValue = $this->appConfig->getValueArray(
			Application::APP_ID,
			RequestSignGroupsPolicy::SYSTEM_APP_CONFIG_KEY,
			RequestSignGroupsPolicyValue::DEFAULT_GROUPS,
		);

		$this->appConfig->deleteKey(Application::APP_ID, RequestSignGroupsPolicy::SYSTEM_APP_CONFIG_KEY);
		$this->appConfig->setValueString(
			Application::APP_ID,
			RequestSignGroupsPolicy::SYSTEM_APP_CONFIG_KEY,
			RequestSignGroupsPolicyValue::encode($typedValue),
		);
	}

	private function migrateLegacyFooterSettings(): void {
		$legacyAddFooter = $this->readLegacyValue(FooterPolicy::KEY);
		$legacyWriteQrCodeOnFooter = $this->readLegacyBool('write_qrcode_on_footer', true);
		$legacyValidationSite = $this->readLegacyString('validation_site') ?? '';
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

		$encodedFooterPolicyValue = FooterPolicyValue::encode(
			FooterPolicyValue::normalize($rawFooterPolicyValue),
		);

		$this->appConfig->deleteKey(Application::APP_ID, FooterPolicy::KEY);
		$this->appConfig->setValueString(Application::APP_ID, FooterPolicy::KEY, $encodedFooterPolicyValue);
	}

	private function migrateDocMdpLevelType(): void {
		$legacyValue = $this->readLegacyString(DocMdpPolicy::SYSTEM_APP_CONFIG_KEY);
		if ($legacyValue === null || $legacyValue === '' || !is_numeric($legacyValue)) {
			return;
		}

		$this->appConfig->deleteKey(Application::APP_ID, DocMdpPolicy::SYSTEM_APP_CONFIG_KEY);
		$this->appConfig->setValueInt(Application::APP_ID, DocMdpPolicy::SYSTEM_APP_CONFIG_KEY, (int)$legacyValue);
	}

	private function migrateIdentifyMethodsType(): void {
		$legacyValue = $this->readLegacyString('identify_methods');
		if ($legacyValue === null || $legacyValue === '') {
			return;
		}

		$this->appConfig->deleteKey(Application::APP_ID, 'identify_methods');
		$decoded = json_decode($legacyValue, true);
		if (!is_array($decoded)) {
			return;
		}

		$this->appConfig->setValueArray(Application::APP_ID, 'identify_methods', $decoded);
	}

	private function readLegacyString(string $key): ?string {
		try {
			return $this->appConfig->getValueString(Application::APP_ID, $key, '');
		} catch (AppConfigTypeConflictException) {
			// The key is already stored in the target typed format
			return null;
		}
	}

	private function readLegacyValue(string $key): mixed {
		try {
			return $this->appConfig->getValueString(Application::APP_ID, $key, '');
		} catch (AppConfigTypeConflictException) {
			return $this->appConfig->getValueBool(Application::APP_ID, $key, true);
		}
	}

	private function readLegacyBool(string $key, bool $default): bool {
		try {
			$rawValue = $this->appConfig->getValueString(Application::APP_ID, $key, '');
			if ($rawValue === '') {
				return $default;
			}

			return in_array(strtolower(trim($rawValue)), ['1', 'true', 'yes', 'on'], true);
		} catch (AppConfigTypeConflictException) {
			return $this->appConfig->getValueBool(Application::APP_ID, $key, $default);
		}
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
