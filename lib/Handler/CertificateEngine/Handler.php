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

class Handler {
	public function __construct(
		private CfsslHandler $cfsslHandler,
		private OpenSslHandler $openSslHandler,
		private NoneHandler $noneHandler,
		private IAppConfig $appConfig,
	) {
	}

	/**
	 * @return CfsslHandler|OpenSslHandler|AEngineHandler
	 */
	public function getEngine(string $engineName = '', array $rootCert = []): AEngineHandler {
		if (!$engineName) {
			$engineName = $this->appConfig->getValueString(Application::APP_ID, 'certificate_engine', 'openssl');
		}
		if ($engineName === 'openssl') {
			$engine = $this->openSslHandler;
		} elseif ($engineName === 'cfssl') {
			$engine = $this->cfsslHandler;
		} elseif ($engineName === 'none') {
			$engine = $this->noneHandler;
		} else {
			throw new LibresignException('Certificate engine not found: ' . $engineName);
		}
		$engine->populateInstance($rootCert);
		return $engine;
	}
}
