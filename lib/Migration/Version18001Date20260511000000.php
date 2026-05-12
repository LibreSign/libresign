<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Policy\Provider\IdentificationDocuments\IdentificationDocumentsPolicy;
use OCP\DB\ISchemaWrapper;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version18001Date20260511000000 extends SimpleMigrationStep {
	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		/**
		 * Consolidate legacy approval_group into unified identification_documents payload.
		 * Transforms from:
		 *   - identification_documents: bool
		 *   - approval_group: string[] (JSON)
		 * To:
		 *   - identification_documents: {enabled: bool, approvers: string[]}
		 */

		$legacyIdDocs = $this->appConfig->getValueBool(
			Application::APP_ID,
			IdentificationDocumentsPolicy::SYSTEM_APP_CONFIG_KEY,
			false
		);

		$legacyApprovalGroup = $this->appConfig->getValueArray(
			Application::APP_ID,
			'approval_group',
			['admin']
		);

		// Build unified payload
		$unifiedPayload = [
			'enabled' => $legacyIdDocs,
			'approvers' => !empty($legacyApprovalGroup) ? $legacyApprovalGroup : ['admin'],
		];

		// Save unified payload back to app_config (will be picked up by policy service)
		$this->appConfig->setValueArray(
			Application::APP_ID,
			IdentificationDocumentsPolicy::SYSTEM_APP_CONFIG_KEY,
			$unifiedPayload
		);

		// Note: We keep legacy 'approval_group' in app_config for backward compatibility
		// It will be ignored by the new policy system
	}
}
