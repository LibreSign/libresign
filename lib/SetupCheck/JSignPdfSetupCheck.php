<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\SetupCheck;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\SignEngine\JSignPdfHandler;
use OCA\Libresign\Helper\JavaHelper;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\Service\Install\SignSetupService;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;
use Psr\Log\LoggerInterface;

class JSignPdfSetupCheck implements ISetupCheck {
	use SetupCheckUtils;

	private IL10N $l10n;
	private IAppConfig $appConfig;
	private JSignPdfHandler $jSignPdfHandler;
	private IConfig $systemConfig;
	private JavaHelper $javaHelper;

	private SignSetupService $signSetupService;
	private IURLGenerator $urlGenerator;
	private IAppManager $appManager;
	private LoggerInterface $logger;

	public function __construct(
		IL10N $l10n,
		IAppConfig $appConfig,
		JSignPdfHandler $jSignPdfHandler,
		SignSetupService $signSetupService,
		IURLGenerator $urlGenerator,
		IAppManager $appManager,
		LoggerInterface $logger,
		IConfig $systemConfig,
		JavaHelper $javaHelper,
	) {
		$this->l10n = $l10n;
		$this->appConfig = $appConfig;
		$this->jSignPdfHandler = $jSignPdfHandler;
		$this->signSetupService = $signSetupService;
		$this->urlGenerator = $urlGenerator;
		$this->appManager = $appManager;
		$this->logger = $logger;
		$this->systemConfig = $systemConfig;
		$this->javaHelper = $javaHelper;
	}

	#[\Override]
	public function getName(): string {
		return 'JSignPdf';
	}

	#[\Override]
	public function getCategory(): string {
		return 'system';
	}

	#[\Override]
	public function run(): SetupResult {
		$debugEnabled = $this->systemConfig->getSystemValueBool('debug', false);
		$jsignpdfJarPath = $this->appConfig->getValueString(Application::APP_ID, 'jsignpdf_jar_path');

		if (!$jsignpdfJarPath) {
			return SetupResult::error(
				$this->l10n->t('JSignPdf not found'),
				// TRANSLATORS Command to run into terminal using Nextcloud occ to configure LibreSign using CLI when the sysadmin want to do this by CLI.
				$this->l10n->t('Run %s', ['occ libresign:install --jsignpdf'])
			);
		}

		$verifyResult = $this->verifyResourceIntegrity('jsignpdf', $debugEnabled);
		if (!empty($verifyResult)) {
			[$errorMsg, $tip] = $this->getErrorAndTipFromVerify($verifyResult, 'jsignpdf', $debugEnabled, $this->l10n);
			return SetupResult::error($errorMsg, $tip);
		}

		if (!file_exists($jsignpdfJarPath)) {
			return SetupResult::error(
				// TRANSLATORS JSignPdf is an optional external signing backend used by LibreSign.
				// LibreSign also supports other signing methods, including its native PHP signer.
				// %s is the configured JSignPdf path that could not be found.
				$this->l10n->t('JSignPdf file not found: %s', [$jsignpdfJarPath]),
				// TRANSLATORS Command to run into terminal using Nextcloud occ to configure LibreSign using CLI when the sysadmin want to do this by CLI.
				$this->l10n->t('Run %s', ['occ libresign:install --jsignpdf'])
			);
		}

		$javaPath = $this->javaHelper->getJavaPath();
		if (!$javaPath || !file_exists($javaPath)) {
			return SetupResult::error(
				// TRANSLATORS JSignPdf is an optional external signing backend that requires Java to run.
				// LibreSign also supports other signing methods, including its native PHP signer.
				$this->l10n->t('Necessary Java to run JSignPdf'),
				// TRANSLATORS Command to run into terminal using Nextcloud occ to configure LibreSign using CLI when the sysadmin want to do this by CLI.
				$this->l10n->t('Run %s', ['occ libresign:install --java'])
			);
		}

		$jsignPdf = $this->jSignPdfHandler->getJSignPdf();
		$jsignPdf->setParam($this->jSignPdfHandler->getJSignParam());
		$currentVersion = $jsignPdf->getVersion();

		if (!$currentVersion) {
			// TRANSLATORS JSignPdf is an optional external signing backend.
			// LibreSign is tested/validated with a specific JSignPdf version. %s is the supported JSignPdf version.
			$msg = $this->l10n->t('Necessary install the version %s', [InstallService::JSIGNPDF_VERSION]);
			// TRANSLATORS Command to run into terminal using Nextcloud occ to configure LibreSign using CLI when the sysadmin want to do this by CLI.
			return SetupResult::error($msg, $this->l10n->t('Run %s', ['occ libresign:install --jsignpdf']));
		}

		if (version_compare($currentVersion, InstallService::JSIGNPDF_VERSION, '<')) {
			// TRANSLATORS JSignPdf is an optional external signing backend.
			// LibreSign is tested/validated with a specific JSignPdf version.
			// The first %s is the currently installed JSignPdf version; the second %s is the required supported version.
			$msg = $this->l10n->t('JSignPdf must be updated from version %s to %s', [$currentVersion, InstallService::JSIGNPDF_VERSION]);
			// TRANSLATORS Command to run into terminal using Nextcloud occ to configure LibreSign using CLI when the sysadmin want to do this by CLI.
			return SetupResult::error($msg, $this->l10n->t('Run %s', ['occ libresign:install --jsignpdf']));
		}

		if (version_compare($currentVersion, InstallService::JSIGNPDF_VERSION, '>')) {
			return SetupResult::error(
				// TRANSLATORS JSignPdf is an optional external signing backend.
				// LibreSign is tested/validated with a specific JSignPdf version.
				// The first %s is the currently installed JSignPdf version; the second %s is the required supported version.
				$this->l10n->t(
					'JSignPdf must be downgraded from version %s to %s',
					[$currentVersion, InstallService::JSIGNPDF_VERSION],
				),
				// TRANSLATORS Command to run into terminal using Nextcloud occ to configure LibreSign using CLI when the sysadmin want to do this by CLI.
				$this->l10n->t('Run %s', ['occ libresign:install --jsignpdf'])
			);
		}

		$messages = [
			// TRANSLATORS JSignPdf is an optional external signing backend. %s is the detected JSignPdf version.
			$this->l10n->t('JSignPdf version: %s', [$currentVersion]),

			// TRANSLATORS JSignPdf is an optional external signing backend. %s is the configured or detected path to the JSignPdf executable/JAR file.
			$this->l10n->t('JSignPdf path: %s', [$jsignpdfJarPath]),
		];

		return SetupResult::success(implode("\n", $messages));
	}
}
