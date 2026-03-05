<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\SetupCheck;

use OCA\Libresign\Service\Install\SignSetupService;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

trait SetupCheckUtils {
	private SignSetupService $signSetupService;
	private IURLGenerator $urlGenerator;
	private IAppManager $appManager;
	private LoggerInterface $logger;

	private function verifyResourceIntegrity(string $resource, bool $debugEnabled): array {
		$this->signSetupService->willUseLocalCert($debugEnabled);
		$result = $this->signSetupService->verify(php_uname('m'), $resource);
		if (count($result) === 1 && $debugEnabled) {
			if (isset($result['SIGNATURE_DATA_NOT_FOUND']) || isset($result['EMPTY_SIGNATURE_DATA'])) {
				return [];
			}
		}
		return $result;
	}

	private function getErrorAndTipFromVerify(array $result, string $resource, bool $debugEnabled, IL10N $l10n): array {
		if (count($result) === 1 && !$debugEnabled) {
			if (isset($result['SIGNATURE_DATA_NOT_FOUND'])) {
				return [
					$l10n->t('Signature data not found.'),
					$l10n->t("Sounds that you are running from source code of LibreSign.\nEnable debug mode by: occ config:system:set debug --value true --type boolean"),
				];
			}
			if (isset($result['EMPTY_SIGNATURE_DATA'])) {
				return [
					$l10n->t('Your signature data is empty.'),
					$l10n->t("Sounds that you are running from source code of LibreSign.\nEnable debug mode by: occ config:system:set debug --value true --type boolean"),
				];
			}
		}
		if (isset($result['HASH_FILE_ERROR'])) {
			if ($debugEnabled) {
				return [
					$l10n->t('Invalid hash of binaries files.'),
					$l10n->t('Debug mode is enabled at your config.php and your LibreSign app was signed using a production signature. If you are not working at development of LibreSign, disable your debug mode or run the command: occ libresign install --%s --use-local-cert', [$resource]),
				];
			}
		}
		$this->logger->error('Invalid hash of binaries files', ['result' => $result]);
		if ($this->appManager->isEnabledForUser('logreader')) {
			return [
				$l10n->t('Invalid hash of binaries files.'),
				$l10n->t('Check your nextcloud.log file on %s and run occ libresign:install --all', [
					$this->urlGenerator->linkToRouteAbsolute('settings.adminsettings.form', ['section' => 'logging'])
				]),
			];
		}
		return [
			$l10n->t('Invalid hash of binaries files.'),
			$l10n->t('Check your nextcloud.log file and run occ libresign:install --all'),
		];
	}
}
