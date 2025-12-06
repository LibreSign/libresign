<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Enum\DocMdpLevel;
use OCP\IAppConfig;
use OCP\IL10N;

class DocMdpConfigService {
	private const CONFIG_KEY_LEVEL = 'docmdp_level';

	public function __construct(
		private IAppConfig $appConfig,
		private IL10N $l10n,
	) {
	}

	public function isEnabled(): bool {
		return $this->appConfig->hasKey(Application::APP_ID, self::CONFIG_KEY_LEVEL);
	}

	public function setEnabled(bool $enabled): void {
		if (!$enabled) {
			$this->appConfig->deleteKey(Application::APP_ID, self::CONFIG_KEY_LEVEL);
		}
	}

	public function getLevel(): DocMdpLevel {
		$level = $this->appConfig->getValueInt(Application::APP_ID, self::CONFIG_KEY_LEVEL, DocMdpLevel::NOT_CERTIFIED->value);
		return DocMdpLevel::from($level);
	}

	public function setLevel(DocMdpLevel $level): void {
		$this->appConfig->setValueInt(Application::APP_ID, self::CONFIG_KEY_LEVEL, $level->value);
	}

	public function getConfig(): array {
		return [
			'enabled' => $this->isEnabled(),
			'defaultLevel' => $this->getLevel()->value,
			'availableLevels' => $this->getAvailableLevels(),
		];
	}

	private function getAvailableLevels(): array {
		return array_map(
			fn (DocMdpLevel $level) => [
				'value' => $level->value,
				'label' => $this->getLabel($level),
				'description' => $this->getDescription($level),
			],
			DocMdpLevel::cases()
		);
	}

	private function getLabel(DocMdpLevel $level): string {
		return match ($level) {
			DocMdpLevel::NOT_CERTIFIED => $this->l10n->t('No certification'),
			DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED => $this->l10n->t('No changes allowed'),
			DocMdpLevel::CERTIFIED_FORM_FILLING => $this->l10n->t('Form filling and additional signatures'),
			DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS => $this->l10n->t('Form filling, annotations and additional signatures'),
		};
	}

	private function getDescription(DocMdpLevel $level): string {
		return match ($level) {
			DocMdpLevel::NOT_CERTIFIED => $this->l10n->t('Approval signature - allows all modifications'),
			DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED => $this->l10n->t('Certifying signature - no modifications or additional signatures allowed'),
			DocMdpLevel::CERTIFIED_FORM_FILLING => $this->l10n->t('Certifying signature - allows form filling and additional approval signatures'),
			DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS => $this->l10n->t('Certifying signature - allows form filling, comments and additional approval signatures'),
		};
	}
}
