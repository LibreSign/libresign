<?php

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

declare(strict_types=1);

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileUser;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCP\IConfig;
use OCP\IL10N;

class IdentifyMethodService {
	public const IDENTIFY_NEXTCLOUD = 'nextcloud';
	public const IDENTIFY_EMAIL = 'email';
	public const IDENTIFY_SIGNAL = 'signal';
	public const IDENTIFY_TELEGRAM = 'telegram';
	public const IDENTIFY_SMS = 'sms';
	public const IDENTIFY_PASSWORD = 'password';
	public const IDENTIFY_METHODS = [
		self::IDENTIFY_NEXTCLOUD,
		self::IDENTIFY_EMAIL,
		self::IDENTIFY_SIGNAL,
		self::IDENTIFY_TELEGRAM,
		self::IDENTIFY_SMS,
		self::IDENTIFY_PASSWORD,
	];
	/**
	 * @var array<IIdentifyMethod>
	 */
	private array $identifyMethod = [];

	public function __construct(
		private IConfig $config,
		private IdentifyMethodMapper $identifyMethodMapper,
		private IL10N $l10n
	) {
	}

	private function getIdentifyMethod($name): IIdentifyMethod {
		if (!array_key_exists($name, $this->identifyMethod)) {
			$className = 'OCA\Libresign\Service\IdentifyMethod\\' . ucfirst($name);
			$this->identifyMethod[$name] = \OC::$server->get($className);
		}
		return $this->identifyMethod[$name];
	}

	private function setEntityData(string $method, $identifyData, bool $isDefault): void {
		if (!in_array($method, IdentifyMethodService::IDENTIFY_METHODS)) {
			// TRANSLATORS When is requested to a person to sign a file, is
			// necessary identify what is the identification method. The
			// identification method is used to define how will be the sign
			// flow.
			throw new LibresignException($this->l10n->t('Invalid identification method'));
		}
		$entity = $this->getIdentifyMethod($method)->getEntity();
		if (is_array($identifyData)) {
			$entity->setIdentifierKey(key($identifyData));
			$entity->setIdentifierValue(current($identifyData));
		} elseif (is_string($identifyData)) {
			$entity->setIdentifierKey($method);
			$entity->setIdentifierValue($identifyData);
		}
		$entity->setDefault($isDefault ? 1 : 0);
		$entity->setMethod($method);
	}

	public function setAllEntityData(array $user): void {
		$defaultMethod = $this->getDefaultIdentifyMethodName();
		if (!array_key_exists('identifyMethods', $user)) {
			$user = [
				'identifyMethods' => [
					$defaultMethod => $user,
				],
			];
		}
		foreach ($user['identifyMethods'] as $method => $identifyData) {
			$this->setEntityData($method, $identifyData, $method === $defaultMethod);
		}
	}

	public function getDefaultIdentifyMethodName(): string {
		return $this->config->getAppValue(Application::APP_ID, 'identify_method', IdentifyMethodService::IDENTIFY_NEXTCLOUD)
			?? IdentifyMethodService::IDENTIFY_NEXTCLOUD;
	}

	public function validateToRequestToSign(): void {
		if (!array_key_exists($this->getDefaultIdentifyMethodName(), $this->identifyMethod)) {
			/**
			 * @todo check if is necessary to return a more specific message. i.e.: the identification method xpto wasn't found
			 */
			throw new LibresignException($this->l10n->t('Invalid identification method'));
		}
		foreach ($this->identifyMethod as $identifyMethod) {
			$identifyMethod->validate();
		}
	}

	public function getDefaultEntity(): IdentifyMethod {
		$identifyMethodName = $this->getDefaultIdentifyMethodName();
		return $this->getIdentifyMethod($identifyMethodName)->getEntity();
	}

	public function getDefaultIdentiyMethod(int $fileUserId): IdentifyMethod {
		$identifyMethods = $this->identifyMethodMapper->getIdentifyMethodsFromFileUserId($fileUserId);
		$default = array_filter($identifyMethods, function (IdentifyMethod $current): bool {
			return $current->getDefault() === 1;
		});
		if (!$default) {
			$identifyMethod = new IdentifyMethod();
			$identifyMethod->setMethod(
				$this->config->getAppValue(Application::APP_ID, 'identify_method', self::IDENTIFY_NEXTCLOUD) ?? self::IDENTIFY_NEXTCLOUD
			);
			return $identifyMethod;
		}
		return current($default);
	}

	/**
	 * @param integer $fileUserId
	 * @return array<IIdentifyMethod>
	 */
	public function getIdentifyMethodsFromFileUserId(int $fileUserId): array {
		$entities = $this->identifyMethodMapper->getIdentifyMethodsFromFileUserId($fileUserId);
		foreach ($entities as $entity) {
			$identifyMethod = $this->getIdentifyMethod($entity->getMethod());
			$identifyMethod->setEntity($entity);
		}
		return $this->identifyMethod;
	}

	/**
	 * @param FileUser $fileUser
	 * @return void
	 */
	public function save(FileUser $fileUser, bool $notify = true): void {
		foreach ($this->identifyMethod as $identifyMethod) {
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
