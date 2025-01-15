<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequest;
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
	public const IDENTIFY_CLICK_TO_SIGN = 'clickToSign';
	public const IDENTIFY_METHODS = [
		self::IDENTIFY_ACCOUNT,
		self::IDENTIFY_EMAIL,
		self::IDENTIFY_SIGNAL,
		self::IDENTIFY_TELEGRAM,
		self::IDENTIFY_SMS,
		self::IDENTIFY_PASSWORD,
		self::IDENTIFY_CLICK_TO_SIGN,
	];
	private bool $isRequest = true;
	private ?IdentifyMethod $currentIdentifyMethod = null;
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
		private Email $email,
	) {
	}

	public function setIsRequest(bool $isRequest): self {
		$this->isRequest = $isRequest;
		return $this;
	}

	public function getInstanceOfIdentifyMethod(string $name, ?string $identifyValue = null): IIdentifyMethod {
		if ($identifyValue && isset($this->identifyMethods[$name])) {
			foreach ($this->identifyMethods[$name] as $identifyMethod) {
				if ($identifyMethod->getEntity()->getIdentifierValue() === $identifyValue) {
					return $identifyMethod;
				}
			}
		}
		$identifyMethod = $this->getNewInstanceOfMethod($name);

		$entity = $identifyMethod->getEntity();
		if (!$entity->getId()) {
			$entity->setIdentifierKey($name);
			$entity->setIdentifierValue($identifyValue);
			$entity->setMandatory($this->isMandatoryMethod($name) ? 1 : 0);
		}
		if ($identifyValue && $this->isRequest) {
			$identifyMethod->validateToRequest();
		}

		$this->identifyMethods[$name][] = $identifyMethod;
		return $identifyMethod;
	}

	private function getNewInstanceOfMethod(string $name): IIdentifyMethod {
		$className = 'OCA\Libresign\Service\IdentifyMethod\\' . ucfirst($name);
		if (!class_exists($className)) {
			$className = 'OCA\Libresign\Service\IdentifyMethod\\SignatureMethod\\' . ucfirst($name);
		}
		/** @var IIdentifyMethod */
		$identifyMethod = clone \OCP\Server::get($className);
		if (empty($this->currentIdentifyMethod)) {
			$identifyMethod->cleanEntity();
		} else {
			$identifyMethod->setEntity($this->currentIdentifyMethod);
		}
		$identifyMethod->getSettings();
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
			$this->setCurrentIdentifyMethod();
			$return[] = $this->getInstanceOfIdentifyMethod($method, $identifyValue);
		}
		return $return;
	}

	public function setCurrentIdentifyMethod(?IdentifyMethod $entity = null): self {
		$this->currentIdentifyMethod = $entity;
		return $this;
	}

	/**
	 * @return array<string,array<IIdentifyMethod>>
	 */
	public function getIdentifyMethodsFromSignRequestId(int $signRequestId): array {
		$entities = $this->identifyMethodMapper->getIdentifyMethodsFromSignRequestId($signRequestId);
		foreach ($entities as $entity) {
			$this->setCurrentIdentifyMethod($entity);
			$this->getInstanceOfIdentifyMethod(
				$entity->getIdentifierKey(),
				$entity->getIdentifierValue(),
			);
		}
		$return = [];
		foreach ($this->identifyMethods as $methodName => $list) {
			foreach ($list as $method) {
				if ($method->getEntity()->getSignRequestId() === $signRequestId) {
					$return[$methodName][] = $method;
				}
			}
		}
		return $return;
	}

	public function getIdentifiedMethod(int $signRequestId): IIdentifyMethod {
		$matrix = $this->getIdentifyMethodsFromSignRequestId($signRequestId);
		foreach ($matrix as $identifyMethods) {
			foreach ($identifyMethods as $identifyMethod) {
				if ($identifyMethod->getEntity()->getIdentifiedAtDate()) {
					return $identifyMethod;
				}
			}
		}
		throw new LibresignException($this->l10n->t('Invalid identification method'), 1);
	}

	public function getSignMethodsOfIdentifiedFactors(int $signRequestId): array {
		$matrix = $this->getIdentifyMethodsFromSignRequestId($signRequestId);
		$return = [];
		foreach ($matrix as $identifyMethods) {
			foreach ($identifyMethods as $identifyMethod) {
				$signatureMethods = $identifyMethod->getSignatureMethods();
				foreach ($signatureMethods as $signatureMethod) {
					if (!$signatureMethod->isEnabled()) {
						continue;
					}
					$signatureMethod->setEntity($identifyMethod->getEntity());
					$return[$signatureMethod->getName()] = $signatureMethod->toArray();
				}
			}
		}
		return $return;
	}

	public function save(SignRequest $signRequest, bool $notify = true): void {
		foreach ($this->identifyMethods as $methods) {
			foreach ($methods as $identifyMethod) {
				$entity = $identifyMethod->getEntity();
				$entity->setSignRequestId($signRequest->getId());
				if ($entity->getId()) {
					$entity = $this->identifyMethodMapper->update($entity);
					if ($notify) {
						$identifyMethod->willNotifyUser(false);
						$identifyMethod->notify();
					}
				} else {
					$entity = $this->identifyMethodMapper->insert($entity);
					if ($notify) {
						$identifyMethod->willNotifyUser(true);
						$identifyMethod->notify();
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
