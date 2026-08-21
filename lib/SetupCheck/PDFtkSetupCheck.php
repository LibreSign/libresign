<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\SetupCheck;

use OCA\Libresign\AppInfo\Application;
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

class PDFtkSetupCheck implements ISetupCheck {
	use SetupCheckUtils;

	private IL10N $l10n;
	private IAppConfig $appConfig;
	private IConfig $systemConfig;
	private JavaHelper $javaHelper;

	private SignSetupService $signSetupService;
	private IURLGenerator $urlGenerator;
	private IAppManager $appManager;
	private LoggerInterface $logger;

	public function __construct(
		IL10N $l10n,
		IAppConfig $appConfig,
		SignSetupService $signSetupService,
		IURLGenerator $urlGenerator,
		IAppManager $appManager,
		LoggerInterface $logger,
		IConfig $systemConfig,
		JavaHelper $javaHelper,
	) {
		$this->l10n = $l10n;
		$this->appConfig = $appConfig;
		$this->signSetupService = $signSetupService;
		$this->urlGenerator = $urlGenerator;
		$this->appManager = $appManager;
		$this->logger = $logger;
		$this->systemConfig = $systemConfig;
		$this->javaHelper = $javaHelper;
	}

	#[\Override]
	public function getName(): string {
		return 'PDFtk';
	}

	#[\Override]
	public function getCategory(): string {
		return 'system';
	}

	#[\Override]
	public function run(): SetupResult {
		$debugEnabled = $this->systemConfig->getSystemValueBool('debug', false);
		$pdftkPath = $this->appConfig->getValueString(Application::APP_ID, 'pdftk_path');

		if (!$pdftkPath) {
			return SetupResult::error(
				// TRANSLATORS Nextcloud administration overview warning. PDFtk is the Java tool LibreSign uses to stamp the signature footer onto PDFs; the configured path is empty.
				$this->l10n->t('PDFtk not found'),
				// TRANSLATORS Tip under a Nextcloud administration overview error. Instructs the server administrator to install PDFtk for LibreSign via the Nextcloud occ CLI.
				$this->l10n->t('Run occ libresign:install --pdftk')
			);
		}

		$verifyResult = $this->verifyResourceIntegrity('pdftk', $debugEnabled);
		if (!empty($verifyResult)) {
			[$errorMsg, $tip] = $this->getErrorAndTipFromVerify($verifyResult, 'pdftk', $debugEnabled, $this->l10n);
			return SetupResult::error($errorMsg, $tip);
		}

		if (!file_exists($pdftkPath)) {
			return SetupResult::error(
				// TRANSLATORS Nextcloud administration overview warning. PDFtk stamps LibreSign's PDF footer during signing; %s is the configured filesystem path that does not exist.
				$this->l10n->t('PDFtk binary not found: %s', [$pdftkPath]),
				// TRANSLATORS Tip under a Nextcloud administration overview error. Instructs the server administrator to install PDFtk for LibreSign via the Nextcloud occ CLI.
				$this->l10n->t('Run occ libresign:install --pdftk')
			);
		}

		$javaPath = $this->javaHelper->getJavaPath();
		if (!$javaPath || !file_exists($javaPath)) {
			return SetupResult::error(
				// TRANSLATORS Nextcloud administration overview warning. LibreSign runs PDFtk as a Java JAR to write the signed PDF footer, so a working Java runtime is required.
				$this->l10n->t('Necessary Java to run PDFtk'),
				// TRANSLATORS Tip under a Nextcloud administration overview error. Instructs the server administrator to install the Java runtime LibreSign needs via the Nextcloud occ CLI.
				$this->l10n->t('Run occ libresign:install --java')
			);
		}

		/** @var list<string> $versionOutput */
		exec($javaPath . ' -jar ' . $pdftkPath . ' --version 2>&1', $versionOutput, $resultCode);
		if (empty($versionOutput) || $resultCode !== 0) {
			return SetupResult::error(
				// TRANSLATORS Nextcloud administration overview warning. LibreSign could not read the PDFtk version string used to stamp signed PDF footers.
				$this->l10n->t('Failure to check PDFtk version.'),
				// TRANSLATORS Tip under a Nextcloud administration overview error. Instructs the server administrator to reinstall PDFtk for LibreSign via the Nextcloud occ CLI.
				$this->l10n->t('Run occ libresign:install --pdftk')
			);
		}

		$versionLine = $versionOutput[0] ?? '';
		preg_match('/pdftk port to java (?<version>.*) a Handy Tool/', $versionLine, $matches);
		$version = $matches['version'] ?? null;

		if (!$version) {
			return SetupResult::error(
				// TRANSLATORS Nextcloud administration overview warning. The file at %s does not look like the PDFtk JAR LibreSign expects for PDF footer stamping.
				$this->l10n->t('PDFtk binary is invalid: %s', [$pdftkPath]),
				// TRANSLATORS Tip under a Nextcloud administration overview error. Instructs the server administrator to reinstall PDFtk for LibreSign via the Nextcloud occ CLI.
				$this->l10n->t('Run occ libresign:install --pdftk')
			);
		}

		if ($version !== InstallService::PDFTK_VERSION) {
			return SetupResult::error(
				// TRANSLATORS Nextcloud administration overview warning. LibreSign only supports a specific PDFtk release for reliable PDF footer stamping; %s is that required version.
				$this->l10n->t('Necessary install the version %s', [InstallService::PDFTK_VERSION]),
				// TRANSLATORS Tip under a Nextcloud administration overview error. Instructs the server administrator to install the supported PDFtk version for LibreSign via the Nextcloud occ CLI.
				$this->l10n->t('Run occ libresign:install --pdftk')
			);
		}

		$messages = [
			// TRANSLATORS Success line in Nextcloud administration overview for LibreSign. %s is the detected PDFtk version used to stamp signed PDF footers.
			$this->l10n->t('PDFtk version: %s', [$version]),
			// TRANSLATORS Success line in Nextcloud administration overview for LibreSign. %s is the filesystem path of the PDFtk JAR used to stamp signed PDF footers.
			$this->l10n->t('PDFtk path: %s', [$pdftkPath]),
		];
		return SetupResult::success(implode("\n", $messages));
	}
}
