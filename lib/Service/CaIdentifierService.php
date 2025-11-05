<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCP\IAppConfig;

class CaIdentifierService {
	private const ENGINE_TYPES = [
		'openssl' => 'o',
		'cfssl' => 'c',
	];

	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	public function generateCaId(string $instanceId, string $engineName): string {
		$generation = $this->getNextGeneration();

		$caId = sprintf(
			'libresign-ca-id:%s_g:%d_e:%s',
			$instanceId,
			$generation,
			self::ENGINE_TYPES[$engineName],
		);
		$this->appConfig->setValueString(Application::APP_ID, 'ca_id', $caId);
		return $caId;
	}

	public function getCaId(): string {
		return $this->appConfig->getValueString(Application::APP_ID, 'ca_id');
	}

	public function isValidCaId(string $caId, string $instanceId): bool {
		$enginePattern = '[' . implode('', array_values(self::ENGINE_TYPES)) . ']';

		$newPattern = '/^libresign-ca-id:' . preg_quote($instanceId, '/') . '_g:\d+_e:' . $enginePattern . '$/';
		if (preg_match($newPattern, $caId)) {
			return true;
		}
		return false;
	}

	public function generatePkiDirectoryName(string $caId): string {
		$parsed = $this->parseCaId($caId);
		return 'pki/' . $parsed['instanceId'] . '_' . $parsed['generation'] . '_' . $parsed['engineName'];
	}

	private function parseCaId(string $caId): array {
		$pattern = '/^libresign-ca-id:(?P<instanceId>[a-z0-9]+)_g:(?P<generation>\d+)_e:(?P<engineType>[' . implode('', array_values(self::ENGINE_TYPES)) . '])$/';
		preg_match($pattern, $caId, $matches);
		$parsed['engineName'] = array_search($matches['engineType'], self::ENGINE_TYPES, true);
		$parsed['instanceId'] = $matches['instanceId'];
		$parsed['generation'] = (int)$matches['generation'];
		$parsed['engineType'] = $matches['engineType'];
		return $parsed;
	}

	private function getNextGeneration(): int {
		$currentNumber = $this->appConfig->getValueInt(Application::APP_ID, 'ca_generation_counter', 0);
		$nextNumber = $currentNumber + 1;
		$this->appConfig->setValueInt(Application::APP_ID, 'ca_generation_counter', $nextNumber);

		return $nextNumber;
	}
}
