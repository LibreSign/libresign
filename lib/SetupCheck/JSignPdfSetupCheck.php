<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\SetupCheck;

use OCA\Libresign\Helper\JavaHelper;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\SignEngine\JSignPdfHandler;
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

class JSignPdfSetupCheck implements ISetupCheck
{
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
    JavaHelper $javaHelper
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

  public function getName(): string
  {
    return $this->l10n->t('JSignPdf');
  }

  public function getCategory(): string
  {
    return 'system';
  }

  public function run(): SetupResult
  {
    $debugEnabled = $this->systemConfig->getSystemValueBool('debug', false);
    $jsignpdfJarPath = $this->appConfig->getValueString(Application::APP_ID, 'jsignpdf_jar_path');

    if (!$jsignpdfJarPath) {
      return SetupResult::error(
        $this->l10n->t('JSignPdf not found'),
        $this->l10n->t('Run occ libresign:install --jsignpdf')
      );
    }

    $verifyResult = $this->verifyResourceIntegrity('jsignpdf', $debugEnabled);
    if (!empty($verifyResult)) {
      [$errorMsg, $tip] = $this->getErrorAndTipFromVerify($verifyResult, 'jsignpdf', $debugEnabled, $this->l10n);
      return SetupResult::error($errorMsg, $tip);
    }

    if (!file_exists($jsignpdfJarPath)) {
      return SetupResult::error(
        $this->l10n->t('JSignPdf binary not found: %s', [$jsignpdfJarPath]),
        $this->l10n->t('Run occ libresign:install --jsignpdf')
      );
    }

    $javaPath = $this->javaHelper->getJavaPath();
    if (!$javaPath || !file_exists($javaPath)) {
      return SetupResult::error(
        $this->l10n->t('Necessary Java to run JSignPdf'),
        $this->l10n->t('Run occ libresign:install --java')
      );
    }

    $jsignPdf = $this->jSignPdfHandler->getJSignPdf();
    $jsignPdf->setParam($this->jSignPdfHandler->getJSignParam());
    $currentVersion = $jsignPdf->getVersion();

    if (!$currentVersion) {
      $msg = $this->l10n->t('Necessary install the version %s', [InstallService::JSIGNPDF_VERSION]);
      return SetupResult::error($msg, $this->l10n->t('Run occ libresign:install --jsignpdf'));
    }

    if (version_compare($currentVersion, InstallService::JSIGNPDF_VERSION, '<')) {
      $msg = $this->l10n->t('Necessary bump JSignPdf version from %s to %s', [$currentVersion, InstallService::JSIGNPDF_VERSION]);
      return SetupResult::error($msg, $this->l10n->t('Run occ libresign:install --jsignpdf'));
    }

    if (version_compare($currentVersion, InstallService::JSIGNPDF_VERSION, '>')) {
      return SetupResult::error(
        $this->l10n->t('Necessary downgrade JSignPdf version from %s to %s', [$currentVersion, InstallService::JSIGNPDF_VERSION]),
        $this->l10n->t('Run occ libresign:install --jsignpdf')
      );
    }

    $messages = [
      $this->l10n->t('JSignPdf version: %s', [$currentVersion]),
      $this->l10n->t('JSignPdf path: %s', [$jsignpdfJarPath])
    ];

    return SetupResult::success(implode("\n", $messages));
  }
}
