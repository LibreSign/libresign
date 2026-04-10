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
use OCA\Libresign\Service\Policy\Provider\Signature\SignatureFlowPolicy;
use OCP\DB\ISchemaWrapper;
use OCP\Exceptions\AppConfigTypeConflictException;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version18001Date20260320000000 extends SimpleMigrationStep {
	private const APP_ID = Application::APP_ID;
	private const EMPTY_STRING = '';
	private const IDENTIFY_METHODS_KEY = 'identify_methods';
	private const LEGACY_SYSTEM_KEY = SignatureFlowPolicy::KEY;

	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	#[\Override]
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->migrateSignatureFlowKeys();
		$this->migrateDocMdpLevelType();
		$this->migrateIdentifyMethodsType();
	}

	private function migrateSignatureFlowKeys(): void {
		$newSystemKey = SignatureFlowPolicy::SYSTEM_APP_CONFIG_KEY;

		$this->copyStringValueWhenDestinationEmpty(self::LEGACY_SYSTEM_KEY, $newSystemKey);

		$this->appConfig->deleteKey(self::APP_ID, self::LEGACY_SYSTEM_KEY);
	}

	private function migrateDocMdpLevelType(): void {
		$legacyValue = $this->readLegacyString(DocMdpPolicy::SYSTEM_APP_CONFIG_KEY);
		if ($legacyValue === null || $legacyValue === self::EMPTY_STRING || !is_numeric($legacyValue)) {
			return;
		}

		$this->appConfig->deleteKey(self::APP_ID, DocMdpPolicy::SYSTEM_APP_CONFIG_KEY);
		$this->appConfig->setValueInt(self::APP_ID, DocMdpPolicy::SYSTEM_APP_CONFIG_KEY, (int)$legacyValue);
	}

	private function migrateIdentifyMethodsType(): void {
		$legacyValue = $this->readLegacyString(self::IDENTIFY_METHODS_KEY);
		if ($legacyValue === null || $legacyValue === self::EMPTY_STRING) {
			return;
		}

		$this->appConfig->deleteKey(self::APP_ID, self::IDENTIFY_METHODS_KEY);
		$decoded = json_decode($legacyValue, true);
		if (!is_array($decoded)) {
			return;
		}

		$this->appConfig->setValueArray(self::APP_ID, self::IDENTIFY_METHODS_KEY, $decoded);
	}

	private function copyStringValueWhenDestinationEmpty(string $sourceKey, string $destinationKey): void {
		$sourceValue = $this->appConfig->getValueString(self::APP_ID, $sourceKey, self::EMPTY_STRING);
		$destinationValue = $this->appConfig->getValueString(self::APP_ID, $destinationKey, self::EMPTY_STRING);
		if ($sourceValue === self::EMPTY_STRING || $destinationValue !== self::EMPTY_STRING) {
			return;
		}

		$this->appConfig->setValueString(self::APP_ID, $destinationKey, $sourceValue);
	}

	private function readLegacyString(string $key): ?string {
		try {
			return $this->appConfig->getValueString(self::APP_ID, $key, self::EMPTY_STRING);
		} catch (AppConfigTypeConflictException) {
			// The key is already stored in the target typed format
			return null;
		}
	}

	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		return null;
	}
}
