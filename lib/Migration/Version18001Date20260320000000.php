<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Enum\SignatureFlow;
use OCA\Libresign\Service\Policy\Provider\CollectMetadata\CollectMetadataPolicy;
use OCA\Libresign\Service\Policy\Provider\DocMdp\DocMdpPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicyValue;
use OCA\Libresign\Service\Policy\Provider\IdentificationDocuments\IdentificationDocumentsPolicy;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicy;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicyValue;
use OCA\Libresign\Service\Policy\Provider\Signature\SignatureFlowPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureText\SignatureTextPolicy;
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
		$this->migrateCollectMetadataType();
		$this->migrateIdentificationDocumentsType();
		$this->migrateDocMdpLevelType();
		$this->migrateGroupsRequestSignType();
		$this->migrateSignatureFlowSettings();
		$this->migrateSignatureTextSettingsType();
		$this->migrateIdentifyMethodsType();
	}

	private function migrateCollectMetadataType(): void {
		$this->migrateBoolType(CollectMetadataPolicy::SYSTEM_APP_CONFIG_KEY, false);
	}

	private function migrateIdentificationDocumentsType(): void {
		$this->migrateBoolType(IdentificationDocumentsPolicy::SYSTEM_APP_CONFIG_KEY, false);
	}

	private function migrateBoolType(string $key, bool $default): void {
		$legacyValue = $this->readLegacyString($key);
		if ($legacyValue === null || trim($legacyValue) === '') {
			return;
		}

		$normalized = filter_var($legacyValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		if ($normalized === null) {
			return;
		}

		$this->appConfig->deleteKey(Application::APP_ID, $key);
		$this->appConfig->setValueBool(Application::APP_ID, $key, $normalized ?? $default);
	}

	private function migrateSignatureFlowSettings(): void {
		$currentSystemValue = $this->readLegacyString(SignatureFlowPolicy::SYSTEM_APP_CONFIG_KEY);
		if ($currentSystemValue !== null && trim($currentSystemValue) !== '') {
			$normalizedSystemValue = $this->normalizeSignatureFlowValue($currentSystemValue);
			if ($normalizedSystemValue !== $currentSystemValue) {
				$this->appConfig->deleteKey(Application::APP_ID, SignatureFlowPolicy::SYSTEM_APP_CONFIG_KEY);
				$this->appConfig->setValueString(Application::APP_ID, SignatureFlowPolicy::SYSTEM_APP_CONFIG_KEY, $normalizedSystemValue);
			}

			return;
		}

		$legacyValue = $this->readLegacyString(SignatureFlowPolicy::KEY);
		if ($legacyValue === null || trim($legacyValue) === '') {
			return;
		}

		$this->appConfig->setValueString(
			Application::APP_ID,
			SignatureFlowPolicy::SYSTEM_APP_CONFIG_KEY,
			$this->normalizeSignatureFlowValue($legacyValue),
		);
		$this->appConfig->deleteKey(Application::APP_ID, SignatureFlowPolicy::KEY);
	}

	private function normalizeSignatureFlowValue(string $value): string {
		$normalized = strtolower(trim($value));

		return match ($normalized) {
			SignatureFlow::NONE->value,
			'0' => SignatureFlow::NONE->value,
			SignatureFlow::PARALLEL->value,
			'1' => SignatureFlow::PARALLEL->value,
			SignatureFlow::ORDERED_NUMERIC->value,
			'2' => SignatureFlow::ORDERED_NUMERIC->value,
			default => SignatureFlow::NONE->value,
		};
	}

	private function migrateSignatureTextSettingsType(): void {
		// First, consolidate individual keys into a JSON payload
		$consolidatedValue = [
			'template' => $this->readLegacyString(SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_TEMPLATE) ?? '',
			'template_font_size' => (float)($this->readLegacyString(SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_TEMPLATE_FONT_SIZE) ?? 9.0),
			'signature_font_size' => (float)($this->readLegacyString(SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_FONT_SIZE) ?? 9.0),
			'signature_width' => (float)($this->readLegacyString(SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_WIDTH) ?? 90.0),
			'signature_height' => (float)($this->readLegacyString(SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_HEIGHT) ?? 60.0),
			'render_mode' => $this->readLegacyString(SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_RENDER_MODE) ?? 'default',
		];

		// Normalize and encode the consolidated value
		$encodedValue = \OCA\Libresign\Service\Policy\Provider\SignatureText\SignatureTextPolicyValue::encode($consolidatedValue);

		// Check if there's an existing consolidated value
		$existingValue = $this->appConfig->getValueString(
			Application::APP_ID,
			SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY,
			'',
		);

		// Only update if we have legacy values or no existing consolidated value
		if (!empty($existingValue) && $existingValue !== '') {
			// Already consolidated, just clean up legacy keys
			$this->deleteLegacySignatureTextKeys();
			return;
		}

		// Delete all individual legacy keys
		$this->deleteLegacySignatureTextKeys();

		// Save the consolidated JSON value
		$this->appConfig->setValueString(
			Application::APP_ID,
			SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY,
			$encodedValue,
		);
	}

	private function deleteLegacySignatureTextKeys(): void {
		$legacyKeys = [
			SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_TEMPLATE,
			SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_TEMPLATE_FONT_SIZE,
			SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_WIDTH,
			SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_HEIGHT,
			SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_FONT_SIZE,
			SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_RENDER_MODE,
		];

		foreach ($legacyKeys as $key) {
			$this->appConfig->deleteKey(Application::APP_ID, $key);
		}
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
