<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\SetupCheck;

use OCA\Libresign\Helper\JavaHelper;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Install\SignSetupService;
use OCA\Libresign\Service\Install\InstallService;
use OCP\IL10N;
use OCP\IAppConfig;
use OCP\IURLGenerator;
use OCP\App\IAppManager;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;
use OCP\IConfig;
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
		JavaHelper $javaHelper
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

	public function getName(): string {
		return $this->l10n->t('PDFtk');
	}

	public function getCategory(): string {
		return 'system';
	}

	public function run(): SetupResult {
		$debugEnabled = $this->systemConfig->getSystemValueBool('debug', false);
		$pdftkPath = $this->appConfig->getValueString(Application::APP_ID, 'pdftk_path');

		if (!$pdftkPath) {
			return SetupResult::error(
				$this->l10n->t('PDFtk not found'),
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
				$this->l10n->t('PDFtk binary not found: %s', [$pdftkPath]),
				$this->l10n->t('Run occ libresign:install --pdftk')
			);
		}

		$javaPath = $this->javaHelper->getJavaPath();
		if (!$javaPath || !file_exists($javaPath)) {
			return SetupResult::error(
				$this->l10n->t('Necessary Java to run PDFtk'),
				$this->l10n->t('Run occ libresign:install --java')
			);
		}

		exec($javaPath . ' -jar ' . $pdftkPath . ' --version 2>&1', $versionOutput, $resultCode);
		if ($resultCode !== 0) {
			return SetupResult::error(
				$this->l10n->t('Failure to check PDFtk version.'),
				$this->l10n->t('Run occ libresign:install --pdftk')
			);
		}

		$versionLine = $versionOutput[0] ?? '';
		preg_match('/pdftk port to java (?<version>.*) a Handy Tool/', $versionLine, $matches);
		$version = $matches['version'] ?? null;

		if (!$version) {
			return SetupResult::error(
				$this->l10n->t('PDFtk binary is invalid: %s', [$pdftkPath]),
				$this->l10n->t('Run occ libresign:install --pdftk')
			);
		}

		if ($version !== InstallService::PDFTK_VERSION) {
			return SetupResult::error(
				$this->l10n->t('Necessary install the version %s', [InstallService::PDFTK_VERSION]),
				$this->l10n->t('Run occ libresign:install --pdftk')
			);
		}

		$messages = [
			$this->l10n->t('PDFtk version: %s', [$version]),
			$this->l10n->t('PDFtk path: %s', [$pdftkPath]),
		];
		return SetupResult::success(implode("\n", $messages));
	}
}
