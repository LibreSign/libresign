<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\SetupCheck;

use OCA\Libresign\Helper\JavaHelper;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\Service\Install\SignSetupService;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;
use Psr\Log\LoggerInterface;

class JavaSetupCheck implements ISetupCheck {
	use SetupCheckUtils;

	private IL10N $l10n;
	private JavaHelper $javaHelper;
	private IConfig $systemConfig;

	private SignSetupService $signSetupService;
	private IURLGenerator $urlGenerator;
	private IAppManager $appManager;
	private LoggerInterface $logger;

	public function __construct(
		IL10N $l10n,
		JavaHelper $javaHelper,
		SignSetupService $signSetupService,
		IURLGenerator $urlGenerator,
		IAppManager $appManager,
		LoggerInterface $logger,
		IConfig $systemConfig,
	) {
		$this->l10n = $l10n;
		$this->javaHelper = $javaHelper;
		$this->signSetupService = $signSetupService;
		$this->urlGenerator = $urlGenerator;
		$this->appManager = $appManager;
		$this->logger = $logger;
		$this->systemConfig = $systemConfig;
	}

	#[\Override]
	public function getName(): string {
		return 'Java';
	}

	#[\Override]
	public function getCategory(): string {
		return 'system';
	}

	#[\Override]
	public function run(): SetupResult {
		$debugEnabled = $this->systemConfig->getSystemValueBool('debug', false);
		$javaPath = $this->javaHelper->getJavaPath();

		if (!$javaPath) {
			return SetupResult::error(
				// TRANSLATORS Warning shown in Nextcloud administration overview when Java required by LibreSign is not installed.
				$this->l10n->t('Java not installed'),
				// TRANSLATORS Tip under a Nextcloud administration overview error. Instructs the administrator to install Java for LibreSign via occ.
				$this->l10n->t('Run occ libresign:install --java')
			);
		}

		$verifyResult = $this->verifyResourceIntegrity('java', $debugEnabled);
		if (!empty($verifyResult)) {
			[$errorMsg, $tip] = $this->getErrorAndTipFromVerify($verifyResult, 'java', $debugEnabled, $this->l10n);
			return SetupResult::error($errorMsg, $tip);
		}

		if (!file_exists($javaPath)) {
			return SetupResult::error(
				// TRANSLATORS Warning shown in Nextcloud administration overview when the configured Java path does not exist. %s is the path.
				$this->l10n->t('Java binary not found: %s', [$javaPath]),
				// TRANSLATORS Tip under a Nextcloud administration overview error. Instructs the administrator to install Java for LibreSign via occ.
				$this->l10n->t('Run occ libresign:install --java')
			);
		}

		/** @var list<string> $output */
		exec($javaPath . ' -version 2>&1', $output, $returnCode);
		if (empty($output)) {
			return SetupResult::error(
				// TRANSLATORS Warning shown in Nextcloud administration overview when Java cannot be executed, often because the operating system blocks it.
				$this->l10n->t('Failed to execute Java. Sounds that your operational system is blocking the JVM.'),
				'https://github.com/LibreSign/libresign/issues/2327#issuecomment-1961988790'
			);
		}
		if ($returnCode !== 0) {
			return SetupResult::error(
				// TRANSLATORS Warning shown in Nextcloud administration overview when LibreSign cannot read the installed Java version.
				$this->l10n->t('Failure to check Java version.'),
				// TRANSLATORS Tip under a Nextcloud administration overview error. Instructs the administrator to install Java for LibreSign via occ.
				$this->l10n->t('Run occ libresign:install --java')
			);
		}

		$javaVersion = trim($output[0] ?? '');
		if ($javaVersion !== InstallService::JAVA_VERSION) {
			return SetupResult::error(
				// TRANSLATORS Warning shown in Nextcloud administration overview when the installed Java version does not match the required one. First %s is found; second %s is expected.
				$this->l10n->t('Invalid java version. Found: %s expected: %s', [$javaVersion, InstallService::JAVA_VERSION]),
				// TRANSLATORS Tip under a Nextcloud administration overview error. Instructs the administrator to install Java for LibreSign via occ.
				$this->l10n->t('Run occ libresign:install --java')
			);
		}

		exec($javaPath . ' -XshowSettings:properties -version 2>&1', $encodingOutput);
		$fullOutput = implode("\n", $encodingOutput);
		preg_match('/native.encoding = (?<encoding>.*?)(\n|$)/', $fullOutput, $matches);
		$encoding = $matches['encoding'] ?? null;

		if (!$encoding) {
			return SetupResult::error(
				// TRANSLATORS Warning shown in Nextcloud administration overview when LibreSign cannot detect the Java character encoding.
				$this->l10n->t('Java encoding not found.'),
				sprintf('The command %s need to have native.encoding', $javaPath . ' -XshowSettings:properties -version')
			);
		}

		if (!str_contains($encoding, 'UTF-8')) {
			$detectedEncoding = trim($encoding);
			$phpLocale = setlocale(LC_CTYPE, '0') ?: 'not set';
			$phpLcAll = getenv('LC_ALL') ?: 'not set';
			$phpLang = getenv('LANG') ?: 'not set';

			$tip = sprintf(
				"Java detected encoding \"%s\" but UTF-8 is required.\n\n"
				  . "**Current PHP environment:**\n"
				  . "- LC_CTYPE: %s\n"
				  . "- LC_ALL: %s\n"
				  . "- LANG: %s\n\n"
				  . "**To fix this issue:**\n"
				  . "1. Set LC_ALL and LANG environment variables (e.g., LC_ALL=en_US.UTF-8) for your web server user\n"
				  . "2. Restart your web server after making changes\n"
				  . "3. Verify with command: `locale charmap` (should return UTF-8)\n\n"
				  . 'For more details, see: [Issue #4872](https://github.com/LibreSign/libresign/issues/4872)',
				$detectedEncoding,
				$phpLocale,
				$phpLcAll,
				$phpLang
			);
			return SetupResult::info(
				// TRANSLATORS Info shown in Nextcloud administration overview when Java is not using UTF-8, which can break accented characters. %s is the detected encoding.
				$this->l10n->t('Non-UTF-8 encoding detected: %s. This may cause issues with accented or special characters', [$detectedEncoding]),
				$tip
			);
		}

		// TRANSLATORS Success detail in Nextcloud administration overview showing the detected Java version. %s is the version string.
		$message = $this->l10n->t('Java version: %s', [$javaVersion]) . "\n"
		  // TRANSLATORS Success detail in Nextcloud administration overview showing the Java executable path. %s is the filesystem path.
		  . $this->l10n->t('Java binary: %s', [$javaPath]);
		return SetupResult::success($message);
	}
}
