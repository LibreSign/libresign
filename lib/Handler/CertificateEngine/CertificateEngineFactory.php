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
	public function getEngine(string $engineName = '', array $rootCert = []): IEngineHandler {
		if (self::$engine) {
			return self::$engine;
		}
		if (!$engineName) {
			$appConfig = \OCP\Server::get(IAppConfig::class);
			$engineName = $appConfig->getValueString(Application::APP_ID, 'certificate_engine', 'openssl');
		}
		if ($engineName === 'openssl') {
			self::$engine = \OCP\Server::get(OpenSslHandler::class);
		} elseif ($engineName === 'cfssl') {
			self::$engine = \OCP\Server::get(CfsslHandler::class);
		} elseif ($engineName === 'none') {
			self::$engine = \OCP\Server::get(NoneHandler::class);
		} else {
			throw new LibresignException('Certificate engine not found: ' . $engineName);
		}
		self::$engine->populateInstance($rootCert);
		return self::$engine;
	}
}
