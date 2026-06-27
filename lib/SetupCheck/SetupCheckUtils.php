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
					// TRANSLATORS This refers to LibreSign binary integrity verification metadata, not to a user's document signature. LibreSign validates approved signing binaries using maintainer-signed metadata shipped with the app.
					$l10n->t('Binary integrity signature data not found.'),
					// TRANSLATORS This tip is shown when LibreSign seems to be running from source code instead of an official packaged release. %s is the occ command to enable debug mode.
					$l10n->t("It looks like this LibreSign instance is running from source code.\nEnable debug mode by running %s", ['occ config:system:set debug --value true --type boolean']),
				];
			}
			if (isset($result['EMPTY_SIGNATURE_DATA'])) {
				return [
					// TRANSLATORS This refers to LibreSign binary integrity verification metadata, not to a user's document signature. LibreSign validates approved signing binaries using maintainer-signed metadata shipped with the app.
					$l10n->t('Binary integrity signature data is empty.'),
					// TRANSLATORS This tip is shown when LibreSign seems to be running from source code instead of an official packaged release. %s is the occ command to enable debug mode.
					$l10n->t("It looks like this LibreSign instance is running from source code.\nEnable debug mode by running %s", ['occ config:system:set debug --value true --type boolean']),
				];
			}
		}
		if (isset($result['HASH_FILE_ERROR'])) {
			if ($debugEnabled) {
				return [
					// TRANSLATORS This is a security/integrity check failure. LibreSign only accepts approved signing binaries whose hashes match maintainer-signed metadata shipped with the app. Even a one-bit change makes the binary invalid.
					$l10n->t('Invalid hash of binaries files.'),
					$l10n->t('Debug mode is enabled at your config.php and your LibreSign app was signed using a production signature. If you are not working at development of LibreSign, disable your debug mode or run the command: occ libresign install --%s --use-local-cert', [$resource]),
				];
			}
		}
		$this->logger->error('Invalid hash of binaries files', ['result' => $result]);
		if ($this->appManager->isEnabledForUser('logreader')) {
			return [
				// TRANSLATORS This is a security/integrity check failure. LibreSign only accepts approved signing binaries whose hashes match maintainer-signed metadata shipped with the app. Even a one-bit change makes the binary invalid.
				$l10n->t('Invalid hash of binaries files.'),
				// TRANSLATORS %s is the occ command that downloads/reinstalls the approved LibreSign binaries.
				$l10n->t('Check your nextcloud.log file on %s and run occ libresign:install --all', [
					$this->urlGenerator->linkToRouteAbsolute('settings.adminsettings.form', ['section' => 'logging'])
				]),
			];
		}
		return [
			// TRANSLATORS This is a security/integrity check failure. LibreSign only accepts approved signing binaries whose hashes match maintainer-signed metadata shipped with the app. Even a one-bit change makes the binary invalid.
			$l10n->t('Invalid hash of binaries files.'),
			// TRANSLATORS %s is the occ command that downloads/reinstalls the approved LibreSign binaries.
			$l10n->t('Check your nextcloud.log file and run occ libresign:install --all'),
		];
	}
}
