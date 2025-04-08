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
		self::$engine = match ($engineName) {
			'openssl' => \OCP\Server::get(OpenSslHandler::class),
			'cfssl' => \OCP\Server::get(CfsslHandler::class),
			'none' => \OCP\Server::get(NoneHandler::class),
			default => throw new LibresignException("Certificate engine not found: $engineName"),
		};
		self::$engine->populateInstance($rootCert);
		return self::$engine;
	}
}
