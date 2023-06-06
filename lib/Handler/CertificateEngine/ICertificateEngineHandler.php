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

/**
 * @method ICertificateEngineHandler setPassword(string $password)
 * @method string getPassword()
 * @method ICertificateEngineHandler setCommonName(string $commonName)
 * @method string getCommonName()
 * @method ICertificateEngineHandler setHosts(array $hosts)
 * @method array getHosts()
 * @method ICertificateEngineHandler setFriendlyName(string $friendlyName)
 * @method string getFriendlyName()
 * @method ICertificateEngineHandler setCountry(string $country)
 * @method string getCountry()
 * @method ICertificateEngineHandler setState(string $state)
 * @method string getState()
 * @method ICertificateEngineHandler setLocality(string $locality)
 * @method string getLocality()
 * @method ICertificateEngineHandler setOrganization(string $organization)
 * @method string getOrganization()
 * @method ICertificateEngineHandler setOrganizationUnit(string $organizationUnit)
 * @method string getOrganizationUnit()
 * @method string getConfigPath()
 */
interface ICertificateEngineHandler {
	public function generateRootCert(
		string $commonName,
		array $names = [],
		string $configPath = '',
	): string;

	public function generateCertificate(): string;

	public function isOk(): bool;

	public function getInstance(): ICertificateEngineHandler;
}
