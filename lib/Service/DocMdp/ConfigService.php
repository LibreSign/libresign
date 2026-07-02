<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\DocMdp;

use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\DocMdp\DocMdpPolicy;
use OCP\IL10N;

/**
 * @psalm-import-type LibresignDocMdpConfig from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignDocMdpLevelOption from \OCA\Libresign\ResponseDefinitions
 */
class ConfigService {
	private const DEFAULT_LEVEL = DocMdpLevel::NOT_CERTIFIED;

	public function __construct(
		private PolicyService $policyService,
		private IL10N $l10n,
	) {
	}

	public function isEnabled(): bool {
		return $this->getLevel()->isCertifying();
	}

	public function setEnabled(bool $enabled): void {
		if ($enabled) {
			if (!$this->getLevel()->isCertifying()) {
				$this->setLevel(DocMdpLevel::CERTIFIED_FORM_FILLING);
			}
			return;
		}

		$this->setLevel(DocMdpLevel::NOT_CERTIFIED);
	}

	public function getLevel(): DocMdpLevel {
		$storedValue = $this->policyService->getSystemPolicy(DocMdpPolicy::KEY)?->getValue();

		if ($storedValue instanceof DocMdpLevel) {
			return $storedValue;
		}

		if (is_string($storedValue) && preg_match('/^\d+$/', $storedValue) === 1) {
			$storedValue = (int)$storedValue;
		}

		if (is_int($storedValue)) {
			return DocMdpLevel::tryFrom($storedValue) ?? self::DEFAULT_LEVEL;
		}

		return self::DEFAULT_LEVEL;
	}

	public function setLevel(DocMdpLevel $level): void {
		$allowChildOverride = $this->policyService->getSystemPolicy(DocMdpPolicy::KEY)?->isAllowChildOverride() ?? false;
		$this->policyService->saveSystem(DocMdpPolicy::KEY, $level->value, $allowChildOverride);
	}

	/** @return LibresignDocMdpConfig */
	public function getConfig(): array {
		return [
			'enabled' => $this->isEnabled(),
			'defaultLevel' => $this->getLevel()->value,
			'availableLevels' => $this->getAvailableLevels(),
		];
	}

	/** @return list<LibresignDocMdpLevelOption> */
	private function getAvailableLevels(): array {
		return array_map(
			fn (DocMdpLevel $level) => [
				'value' => $level->value,
				'label' => $level->getLabel($this->l10n),
				'description' => $level->getDescription($this->l10n),
			],
			DocMdpLevel::cases()
		);
	}

}
