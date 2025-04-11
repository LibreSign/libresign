<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\CertificateEngine;

/**
 * @method IEngineHandler setPassword(string $password)
 * @method string getPassword()
 * @method IEngineHandler setCommonName(string $commonName)
 * @method string getCommonName()
 * @method IEngineHandler setHosts(array $hosts)
 * @method array getHosts()
 * @method IEngineHandler setFriendlyName(string $friendlyName)
 * @method string getFriendlyName()
 * @method IEngineHandler setCountry(string $country)
 * @method string getCountry()
 * @method IEngineHandler setState(string $state)
 * @method string getState()
 * @method IEngineHandler setLocality(string $locality)
 * @method string getLocality()
 * @method IEngineHandler setOrganization(string $organization)
 * @method string getOrganization()
 * @method IEngineHandler setOrganizationalUnit(string $organizationalUnit)
 * @method string getOrganizationalUnit()
 * @method IEngineHandler setUID(string $UID)
 * @method string getUID()
 * @method string getName()
 */
interface IEngineHandler {
	public function generateRootCert(
		string $commonName,
		array $names = [],
	): string;

	public function populateInstance(array $rootCert): IEngineHandler;

	public function generateCertificate(): string;

	public function readCertificate(string $certificate, string $privateKey): array;

	public function updatePassword(string $certificate, string $currentPrivateKey, string $newPrivateKey): string;

	public function getEngine(): string;

	public function isSetupOk(): bool;

	public function setConfigPath(string $configPath): IEngineHandler;

	public function expirity(): int;

	public function configureCheck(): array;

	public function toArray(): array;
}
