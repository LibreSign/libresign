<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
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
