<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Policy\Provider\Signature\SignatureFlowPolicy;
use OCP\DB\ISchemaWrapper;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version18001Date20260320000000 extends SimpleMigrationStep {
	private const LEGACY_SYSTEM_KEY = SignatureFlowPolicy::KEY;
	private const LEGACY_ALLOW_CHILD_OVERRIDE_KEY = SignatureFlowPolicy::KEY . '.allow_child_override';
	private const SYSTEM_ALLOW_CHILD_OVERRIDE_SUFFIX = '.allow_child_override';

	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	#[\Override]
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$newSystemKey = SignatureFlowPolicy::SYSTEM_APP_CONFIG_KEY;
		$newAllowChildOverrideKey = $newSystemKey . self::SYSTEM_ALLOW_CHILD_OVERRIDE_SUFFIX;

		$legacySystemValue = $this->appConfig->getValueString(Application::APP_ID, self::LEGACY_SYSTEM_KEY, '');
		$newSystemValue = $this->appConfig->getValueString(Application::APP_ID, $newSystemKey, '');
		if ($legacySystemValue !== '' && $newSystemValue === '') {
			$this->appConfig->setValueString(Application::APP_ID, $newSystemKey, $legacySystemValue);
		}

		$legacyAllowOverrideValue = $this->appConfig->getValueString(Application::APP_ID, self::LEGACY_ALLOW_CHILD_OVERRIDE_KEY, '');
		$newAllowOverrideValue = $this->appConfig->getValueString(Application::APP_ID, $newAllowChildOverrideKey, '');
		if ($legacyAllowOverrideValue !== '' && $newAllowOverrideValue === '') {
			$this->appConfig->setValueString(Application::APP_ID, $newAllowChildOverrideKey, $legacyAllowOverrideValue);
		}

		$this->appConfig->deleteKey(Application::APP_ID, self::LEGACY_SYSTEM_KEY);
		$this->appConfig->deleteKey(Application::APP_ID, self::LEGACY_ALLOW_CHILD_OVERRIDE_KEY);
	}

	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		return null;
	}
}
