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
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Exception\LibresignException;
use OCP\IConfig;
use OCP\IL10N;

class IdentifyMethodService {
	public const IDENTIFTY_NEXTCLOUD = 'nextcloud';
	public const IDENTIFY_EMAIL = 'email';
	public const IDENTIFY_SIGNAL = 'signal';
	public const IDENTIFY_TELEGRAM = 'telegram';
	public const IDENTIFY_SMS = 'sms';
	public const IDENTIFY_PASSWORD = 'password';
	public const IDENTIFY_METHODS = [
		self::IDENTIFTY_NEXTCLOUD,
		self::IDENTIFY_EMAIL,
		self::IDENTIFY_SIGNAL,
		self::IDENTIFY_TELEGRAM,
		self::IDENTIFY_SMS,
		self::IDENTIFY_PASSWORD,
	];
	/**
	 * @var array<IdentifyMethod>
	 */
	private array $methods = [];

	public function __construct(
		private IConfig $config,
		private IdentifyMethodMapper $identifyMethodMapper,
		private IL10N $l10n
	) {
	}

	/**
	 * @param array $user
	 * @return array<string, self>
	 */
	public function getUserIdentifyMethods(array $user): array {
		$defaultMethod = $this->getDefaultIdentifyMethod();
		if (!array_key_exists('identifyMethods', $user)) {
			$user = [
				'identifyMethods' => [
					$defaultMethod => $user,
				],
			];
		}
		foreach ($user['identifyMethods'] as $method => $identifyData) {
			if (!in_array($method, IdentifyMethodService::IDENTIFY_METHODS)) {
				// TRANSLATORS When is requested to a person to sign a file, is
				// necessary identify what is the identification method. The
				// identification method is used to define how will be the sign
				// flow.
				throw new LibresignException($this->l10n->t('Invalid identification method'));
			}
			$this->setIdentifyMethod($identifyData, $method, $defaultMethod);
		}
		return $this->methods;
	}

	public function getDefaultIdentifyMethod(): string {
		return $this->config->getAppValue(Application::APP_ID, 'identify_method', IdentifyMethodService::IDENTIFTY_NEXTCLOUD)
			?? IdentifyMethodService::IDENTIFTY_NEXTCLOUD;
	}

	private function setIdentifyMethod(array $identifyData, string $currentMethod, string $defaultMethod): IdentifyMethod {
		if (!array_key_exists($currentMethod, $this->methods)) {
			$this->methods[$currentMethod] = new IdentifyMethod();
		}
		if ($identifyData) {
			$this->methods[$currentMethod]->setIdentifierKey(key($identifyData));
			$this->methods[$currentMethod]->setIdentifierValue(current($identifyData));
		}
		$this->methods[$currentMethod]->setDefault($currentMethod === $defaultMethod ? 1 : 0);
		$this->methods[$currentMethod]->setMethod($currentMethod);
		return $this->methods[$currentMethod];
	}

	public function getDefaultIdentiyMethod(int $fileUserId): IdentifyMethod {
		$identifyMethods = $this->identifyMethodMapper->getIdentifyMethodsFromFileUserId($fileUserId);
		$default = array_filter($identifyMethods, function (IdentifyMethod $current): bool {
			return $current->getDefault() === 1;
		});
		if (!$default) {
			$identifyMethod = new IdentifyMethod();
			$identifyMethod->setMethod(
				$this->config->getAppValue(Application::APP_ID, 'identify_method', self::IDENTIFTY_NEXTCLOUD) ?? self::IDENTIFTY_NEXTCLOUD
			);
			return $identifyMethod;
		}
		return current($default);
	}

	/**
	 * @param integer $fileUserId
	 * @return array<IdentifyMethod>
	 */
	public function getIdentifyMethodsFromFileUserId(int $fileUserId): array {
		$identifyMethods = $this->identifyMethodMapper->getIdentifyMethodsFromFileUserId($fileUserId);
		return $identifyMethods;
	}
}
