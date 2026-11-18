<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\OrganizationSeal;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCP\IAppConfig;
use OCP\Security\ISecureRandom;

/**
 * Organization seal certificates configured by an administrator.
 *
 * The list of seals (id, label and certificate summary) is kept in one app
 * config key. The PKCS#12 content and its password of every seal are kept in
 * a separate sensitive key, so they are encrypted at rest and never returned
 * by the list.
 */
class OrganizationSealStore {
	public const INDEX_APP_CONFIG_KEY = 'organization_seals';
	public const CREDENTIAL_APP_CONFIG_KEY_PREFIX = 'organization_seal_';
	public const MAX_LABEL_LENGTH = 64;
	public const MAX_PFX_SIZE = 65536;

	public function __construct(
		private IAppConfig $appConfig,
		private ISecureRandom $secureRandom,
	) {
	}

	/**
	 * @return list<array{id: string, label: string, subject: string, validTo: string}>
	 */
	public function list(): array {
		$decoded = json_decode($this->appConfig->getValueString(Application::APP_ID, self::INDEX_APP_CONFIG_KEY, '[]', lazy: true), true);
		if (!is_array($decoded)) {
			return [];
		}

		$seals = [];
		foreach ($decoded as $seal) {
			if (!is_array($seal) || !isset($seal['id'], $seal['label']) || !is_string($seal['id']) || !is_string($seal['label'])) {
				continue;
			}
			$seals[] = [
				'id' => $seal['id'],
				'label' => $seal['label'],
				'subject' => is_string($seal['subject'] ?? null) ? $seal['subject'] : '',
				'validTo' => is_string($seal['validTo'] ?? null) ? $seal['validTo'] : '',
			];
		}
		return $seals;
	}

	/**
	 * @return array{id: string, label: string, subject: string, validTo: string}|null
	 */
	public function get(string $id): ?array {
		foreach ($this->list() as $seal) {
			if ($seal['id'] === $id) {
				return $seal;
			}
		}
		return null;
	}

	public function exists(string $id): bool {
		return $this->get($id) !== null;
	}

	/**
	 * @return array{id: string, label: string, subject: string, validTo: string}
	 * @throws LibresignException when the label or the certificate is not usable
	 */
	public function add(string $label, string $pfx, string $password): array {
		$label = trim($label);
		if ($label === '' || mb_strlen($label) > self::MAX_LABEL_LENGTH) {
			throw new LibresignException(sprintf('The label must have between 1 and %d characters.', self::MAX_LABEL_LENGTH));
		}
		foreach ($this->list() as $seal) {
			if ($seal['label'] === $label) {
				throw new LibresignException('A seal with this label already exists.');
			}
		}

		$certificate = $this->readCertificate($pfx, $password);

		$seal = [
			'id' => $this->secureRandom->generate(16, ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_DIGITS),
			'label' => $label,
			'subject' => $certificate['subject'],
			'validTo' => $certificate['validTo'],
		];

		$this->appConfig->setValueString(
			Application::APP_ID,
			self::CREDENTIAL_APP_CONFIG_KEY_PREFIX . $seal['id'],
			json_encode(['pfx' => base64_encode($pfx), 'password' => $password], JSON_THROW_ON_ERROR),
			lazy: true,
			sensitive: true,
		);
		$this->saveIndex([...$this->list(), $seal]);

		return $seal;
	}

	public function delete(string $id): bool {
		$seals = $this->list();
		$remaining = array_values(array_filter($seals, static fn (array $seal): bool => $seal['id'] !== $id));
		if (count($remaining) === count($seals)) {
			return false;
		}

		$this->saveIndex($remaining);
		$this->appConfig->deleteKey(Application::APP_ID, self::CREDENTIAL_APP_CONFIG_KEY_PREFIX . $id);
		return true;
	}

	/**
	 * @return array{pfx: string, password: string, subject: string}|null null when the seal is unknown
	 */
	public function getCredentials(string $id): ?array {
		$seal = $this->get($id);
		if ($seal === null) {
			return null;
		}

		$decoded = json_decode($this->appConfig->getValueString(Application::APP_ID, self::CREDENTIAL_APP_CONFIG_KEY_PREFIX . $id, '', lazy: true), true);
		if (!is_array($decoded) || !is_string($decoded['pfx'] ?? null) || !is_string($decoded['password'] ?? null)) {
			return null;
		}

		$pfx = base64_decode($decoded['pfx'], true);
		if ($pfx === false) {
			return null;
		}

		return ['pfx' => $pfx, 'password' => $decoded['password'], 'subject' => $seal['subject']];
	}

	/**
	 * @return array{subject: string, validTo: string}
	 * @throws LibresignException
	 */
	private function readCertificate(string $pfx, string $password): array {
		if ($pfx === '' || strlen($pfx) > self::MAX_PFX_SIZE) {
			throw new LibresignException('The certificate file is empty or too large.');
		}

		$certificates = [];
		if (!openssl_pkcs12_read($pfx, $certificates, $password)) {
			throw new LibresignException('The certificate file cannot be read with this password.');
		}
		if (empty($certificates['cert']) || empty($certificates['pkey'])) {
			throw new LibresignException('The certificate file must contain a certificate and its private key.');
		}
		if (!openssl_x509_check_private_key($certificates['cert'], $certificates['pkey'])) {
			throw new LibresignException('The private key does not match the certificate.');
		}

		$parsed = openssl_x509_parse($certificates['cert']);
		if (!is_array($parsed) || !isset($parsed['validTo_time_t'])) {
			throw new LibresignException('The certificate cannot be parsed.');
		}
		if ((int)$parsed['validTo_time_t'] <= time()) {
			throw new LibresignException('The certificate has expired.');
		}

		$commonName = $parsed['subject']['CN'] ?? '';
		if (is_array($commonName)) {
			$commonName = (string)reset($commonName);
		}

		return [
			'subject' => (string)$commonName,
			'validTo' => (new \DateTimeImmutable('@' . (int)$parsed['validTo_time_t']))->format(\DateTimeInterface::ATOM),
		];
	}

	/**
	 * @param list<array{id: string, label: string, subject: string, validTo: string}> $seals
	 */
	private function saveIndex(array $seals): void {
		$this->appConfig->setValueString(
			Application::APP_ID,
			self::INDEX_APP_CONFIG_KEY,
			json_encode($seals, JSON_THROW_ON_ERROR),
			lazy: true,
		);
	}
}
