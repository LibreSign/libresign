<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH <https://github.com/nextcloud/server>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Helper;

use OCA\Libresign\AppInfo\Application;
use OCP\IAppConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class JavaHelper {
	private bool $isInitialized = false;
	public function __construct(
		protected IAppConfig $appConfig,
		protected IL10N $l10n,
		protected LoggerInterface $logger,
	) {
	}

	public function init(): void {
		if ($this->isInitialized) {
			return;
		}
		if ($this->isNonUTF8Locale()) {
			$this->initializeUtf8Locale();
		}
		$this->isInitialized = true;
	}

	private function initializeUtf8Locale(): void {
		$originalLang = getenv('LANG');
		$originalLocale = setlocale(LC_CTYPE, '0');
		$attemptedLocales = $this->getUtf8LocaleCandidates();

		foreach ($attemptedLocales as $locale) {
			putenv('LANG=' . $locale);
			setlocale(LC_CTYPE, $locale, 'UTF-8');
			if (!$this->isNonUTF8Locale()) {
				return;
			}
		}

		$this->restoreOriginalLocaleEnvironment($originalLang, $originalLocale);
		$this->logger->warning(sprintf(
			'JavaHelper: setlocale did not work properly after attempting locales: %s',
			implode(', ', $attemptedLocales)
		));
	}

	/**
	 * @return list<string>
	 */
	private function getUtf8LocaleCandidates(): array {
		$localeCode = trim(str_replace('-', '_', $this->l10n->getLocaleCode()));
		$candidates = [];

		if ($localeCode !== '') {
			if (preg_match('/^[A-Za-z]{2,3}_[A-Za-z]{2}$/', $localeCode) === 1) {
				[$language, $region] = explode('_', $localeCode, 2);
				$candidates[] = sprintf('%s_%s.UTF-8', strtolower($language), strtoupper($region));
				$candidates[] = sprintf('%s_%s.utf8', strtolower($language), strtoupper($region));
			} elseif (preg_match('/^[A-Za-z]{2,3}$/', $localeCode) === 1) {
				$language = strtolower($localeCode);
				$candidates[] = sprintf('%s_%s.UTF-8', $language, strtoupper($language));
				$candidates[] = $language . '.UTF-8';
			}
		}

		$candidates[] = 'C.UTF-8';
		$candidates[] = 'UTF-8';

		return array_values(array_unique($candidates));
	}

	private function restoreOriginalLocaleEnvironment(string|false $originalLang, string|false $originalLocale): void {
		if ($originalLang === false) {
			putenv('LANG');
		} else {
			putenv('LANG=' . $originalLang);
		}

		if (is_string($originalLocale) && $originalLocale !== '') {
			setlocale(LC_CTYPE, $originalLocale);
		}
	}

	/**
	 * Check if current locale is non-UTF8
	 *
	 * Based on: https://github.com/nextcloud/server/blob/cf1eed2769d928f4a7fe4543d51994331701f2d9/lib/private/legacy/OC_Util.php#L692-L705
	 *
	 * @return bool
	 */
	protected function isNonUTF8Locale(): bool {
		if (function_exists('escapeshellcmd')) {
			return escapeshellcmd('§') === '';
		}
		if (function_exists('escapeshellarg')) {
			return escapeshellarg('§') === '\'\'';
		}
		return preg_match('/utf-?8/i', setlocale(LC_CTYPE, '0')) === 0;
	}

	/**
	 * Returns the configured Java binary path.
	 * Initializes the environment if needed.
	 */
	public function getJavaPath(): string {
		if (!$this->isInitialized) {
			$this->init();
		}
		return $this->appConfig->getValueString(Application::APP_ID, 'java_path');
	}
}
