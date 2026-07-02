<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\CertificateEngine;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCP\IAppConfig;

class CertificateEngineFactory {
	private static ?IEngineHandler $engine = null;

	public function __construct(
		private IAppConfig $appConfig,
		private OpenSslHandler $openSslHandler,
		private CfsslHandler $cfsslHandler,
		private NoneHandler $noneHandler,
	) {
	}

	public function getEngine(string $engineName = '', array $rootCert = []): IEngineHandler {
		if (self::$engine && !empty($engineName) && self::$engine->getName() === $engineName) {
			self::$engine->populateInstance($rootCert);
			return self::$engine;
		}

		if (!$engineName) {
			$configuredEngineName = $this->getConfiguredEngineName();

			if (self::$engine && self::$engine->getName() === $configuredEngineName) {
				self::$engine->populateInstance($rootCert);
				return self::$engine;
			}

			$engineName = $configuredEngineName;
		}

		self::$engine = $this->resolveHandler($engineName);
		self::$engine->populateInstance($rootCert);
		return self::$engine;
	}

	public function setEngine(string $engineName): IEngineHandler {
		$handler = $this->resolveHandler($engineName);
		$handler->setEngine($engineName);
		self::$engine = $handler;
		return self::$engine;
	}

	private function getConfiguredEngineName(): string {
		$configValues = $this->appConfig->getAllValues(Application::APP_ID);
		$configuredEngineName = $configValues['certificate_engine'] ?? '';

		if (is_string($configuredEngineName) && $configuredEngineName !== '') {
			return $configuredEngineName;
		}

		if (is_scalar($configuredEngineName) && (string)$configuredEngineName !== '') {
			return (string)$configuredEngineName;
		}

		return $this->appConfig->getValueString(Application::APP_ID, 'certificate_engine', 'openssl');
	}

	private function resolveHandler(string $engineName): IEngineHandler {
		return match ($engineName) {
			'openssl' => $this->openSslHandler,
			'cfssl' => $this->cfsslHandler,
			'none' => $this->noneHandler,
			default => throw new LibresignException("Certificate engine not found: $engineName"),
		};
	}
}
