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
 * @method IEngineHandler setOrganizationalUnit(array $organizationalUnit)
 * @method array getOrganizationalUnit()
 * @method IEngineHandler setUID(string $UID)
 * @method string getUID()
 * @method string getName()
 */
interface IEngineHandler {
	public function generateRootCert(
		string $commonName,
		array $names = [],
	): void;

	public function populateInstance(array $rootCert): IEngineHandler;

	public function generateCertificate(): string;

	public function readCertificate(string $certificate, string $privateKey): array;

	public function updatePassword(string $certificate, string $currentPrivateKey, string $newPrivateKey): string;

	public function getEngine(): string;

	public function isSetupOk(): bool;

	public function getCurrentConfigPath(): string;

	public function getConfigPathByParams(string $instanceId, int $generation): string;

	public function setConfigPath(string $configPath): IEngineHandler;

	public function getLeafExpiryInDays(): int;

	public function getCaExpiryInDays(): int;

	public function configureCheck(): array;

	public function toArray(): array;

	/**
	 * Generate Certificate Revocation List in DER format
	 * @param array $revokedCertificates Array of revoked certificate entities
	 * @param string $instanceId Instance identifier
	 * @param int $generation Generation identifier
	 * @param int $crlNumber Sequential CRL number for X.509 CRL versioning
	 * @return string DER-encoded CRL data
	 * @throws \RuntimeException If CRL generation is not supported or fails
	 */
	public function generateCrlDer(array $revokedCertificates, string $instanceId, int $generation, int $crlNumber): string;

	/**
	 * Parse an X.509 certificate and return its details with CRL validation
	 * @param string $certificate PEM-encoded certificate
	 * @return array Parsed certificate data including CRL validation information
	 */
	public function parseCertificate(string $certificate): array;

	/**
	 * Validates the root certificate checking for expiration and revocation
	 * Also checks if renewal is needed to maintain CRL signing capability
	 * @throws \OCA\Libresign\Exception\LibresignException If certificate is expired or revoked
	 */
	public function validateRootCertificate(): void;
}
