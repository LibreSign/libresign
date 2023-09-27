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

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\FileUser;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdentifyMethod\Account;
use OCA\Libresign\Service\IdentifyMethod\Email;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCP\IConfig;
use OCP\IL10N;

class IdentifyMethodService {
	public const IDENTIFY_ACCOUNT = 'account';
	public const IDENTIFY_EMAIL = 'email';
	public const IDENTIFY_SIGNAL = 'signal';
	public const IDENTIFY_TELEGRAM = 'telegram';
	public const IDENTIFY_SMS = 'sms';
	public const IDENTIFY_PASSWORD = 'password';
	public const IDENTIFY_METHODS = [
		self::IDENTIFY_ACCOUNT,
		self::IDENTIFY_EMAIL,
		self::IDENTIFY_SIGNAL,
		self::IDENTIFY_TELEGRAM,
		self::IDENTIFY_SMS,
		self::IDENTIFY_PASSWORD,
	];
	private array $identifyMethodsSettings = [];
	/**
	 * @var array<string,array<IIdentifyMethod>>
	 */
	private array $identifyMethods = [];

	public function __construct(
		private IConfig $config,
		private IdentifyMethodMapper $identifyMethodMapper,
		private IL10N $l10n,
		private Account $account,
		private Email $email
	) {
	}

	public function getInstanceOfIdentifyMethod(string $name, ?string $identifyValue = null): IIdentifyMethod {
		if ($identifyValue && isset($this->identifyMethods[$name])) {
			foreach ($this->identifyMethods[$name] as $identifyMethod) {
				if ($identifyMethod->getEntity()->getIdentifierValue() === $identifyValue) {
					return $identifyMethod;
				}
			}
		}
		$className = 'OCA\Libresign\Service\IdentifyMethod\\' . ucfirst($name);
		$identifyMethod = \OC::$server->get($className);

		$entity = $identifyMethod->getEntity();
		$entity->setIdentifierKey($name);
		$entity->setIdentifierValue($identifyValue);
		$entity->setMandatory($this->isMandatoryMethod($name) ? 1 : 0);
		$entity->setMethod($name);
		if ($identifyValue) {
			$identifyMethod->validateToRequest();
		}

		$this->identifyMethods[$name][] = $identifyMethod;
		return $identifyMethod;
	}

	private function setEntityData(string $method, string $identifyValue): void {
		// @todo Replace by enum when PHP 8.1 is the minimum version acceptable
		// at server. Check file lib/versioncheck.php of server repository
		if (!in_array($method, IdentifyMethodService::IDENTIFY_METHODS)) {
			// TRANSLATORS When is requested to a person to sign a file, is
			// necessary identify what is the identification method. The
			// identification method is used to define how will be the sign
			// flow.
			throw new LibresignException($this->l10n->t('Invalid identification method'));
		}
		$identifyMethod = $this->getInstanceOfIdentifyMethod($method, $identifyValue);
		$identifyMethod->validateToRequest();
	}

	public function setAllEntityData(array $user): void {
		foreach ($user['identify'] as $method => $identifyValue) {
			$this->setEntityData($method, $identifyValue);
		}
	}

	private function isMandatoryMethod(string $methodName): bool {
		$settings = $this->getIdentifyMethodsSettings();
		foreach ($settings as $setting) {
			if ($setting['name'] === $methodName) {
				return $setting['mandatory'];
			}
		}
		return false;
	}

	/**
	 * @return array<IIdentifyMethod>
	 */
	public function getByUserData(array $data) {
		$return = [];
		foreach ($data as $method => $identifyValue) {
			$return[] = $this->getInstanceOfIdentifyMethod($method, $identifyValue);
		}
		return $return;
	}

	/**
	 * @return array<string,array<IIdentifyMethod>>
	 */
	public function getIdentifyMethodsFromFileUserId(int $fileUserId): array {
		$entities = $this->identifyMethodMapper->getIdentifyMethodsFromFileUserId($fileUserId);
		foreach ($entities as $entity) {
			$identifyMethod = $this->getInstanceOfIdentifyMethod($entity->getMethod());
			$identifyMethod->setEntity($entity);
		}
		return $this->identifyMethods;
	}

	public function save(FileUser $fileUser, bool $notify = true): void {
		foreach ($this->identifyMethods as $methods) {
			foreach ($methods as $identifyMethod) {
				$entity = $identifyMethod->getEntity();
				$entity->setFileUserId($fileUser->getId());
				if ($entity->getId()) {
					$entity = $this->identifyMethodMapper->update($entity);
					if ($notify) {
						$identifyMethod->notify(false, $fileUser);
					}
				} else {
					$entity = $this->identifyMethodMapper->insert($entity);
					if ($notify) {
						$identifyMethod->notify(true, $fileUser);
					}
				}
			}
		}
	}

	public function getIdentifyMethodsSettings(): array {
		if ($this->identifyMethodsSettings) {
			return $this->identifyMethodsSettings;
		}
		$this->identifyMethodsSettings = [
			$this->account->getSettings(),
			$this->email->getSettings(),
		];
		return $this->identifyMethodsSettings;
	}
}
