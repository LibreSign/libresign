<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignRequest;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCP\IL10N;
use OCP\L10N\IFactory;

/**
 * Language the requester picked for the e-mails sent to one signer.
 *
 * The choice is stored in the sign request metadata. Without a choice the
 * e-mails keep using the default language of the instance.
 */
class SignerLanguageService {
	public const METADATA_KEY = 'language';

	public function __construct(
		private IFactory $l10nFactory,
		private SignRequestMapper $signRequestMapper,
	) {
	}

	/**
	 * Language codes LibreSign ships translations for.
	 *
	 * @return list<string>
	 */
	public function getAvailableLanguages(): array {
		$languages = array_values(array_unique($this->l10nFactory->findAvailableLanguages(Application::APP_ID)));
		sort($languages);
		return $languages;
	}

	public function isAvailable(string $language): bool {
		return $this->l10nFactory->languageExists(Application::APP_ID, $language);
	}

	public function getLanguage(SignRequest $signRequest): ?string {
		$language = ($signRequest->getMetadata() ?? [])[self::METADATA_KEY] ?? null;
		if (!is_string($language) || $language === '' || !$this->isAvailable($language)) {
			return null;
		}
		return $language;
	}

	/**
	 * Translations for the e-mails sent to the signer, or null to keep the default.
	 */
	public function getL10n(SignRequest $signRequest): ?IL10N {
		$language = $this->getLanguage($signRequest);
		return $language === null ? null : $this->l10nFactory->get(Application::APP_ID, $language);
	}

	/**
	 * Stores the language of a persisted sign request. An empty value clears it.
	 */
	public function setLanguage(SignRequest $signRequest, string $language): void {
		$metadata = $signRequest->getMetadata() ?? [];
		$current = $metadata[self::METADATA_KEY] ?? null;
		if ($language === '') {
			if ($current === null) {
				return;
			}
			unset($metadata[self::METADATA_KEY]);
		} else {
			if ($current === $language) {
				return;
			}
			$metadata[self::METADATA_KEY] = $language;
		}
		$signRequest->setMetadata($metadata);
		$this->signRequestMapper->update($signRequest);
	}
}
