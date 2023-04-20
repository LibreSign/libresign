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
use OCP\IConfig;

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

	public function __construct(
		private IConfig $config,
		private IdentifyMethodMapper $identifyMethodMapper
	) {
	}

	public function getUserIdentifyMethods(array $user): array {
		if (array_key_exists('identifyMethod', $user)) {
			return $user['identifyMethod'];
		}
		return json_decode($this->config->getAppValue(Application::APP_ID, 'identify_method', '["nextcloud"]') ?? '["nextcloud"]', true);
	}

	/**
	 * @param array<IdentifyMethod> $identifyMethods
	 * @return IdentifyMethod
	 */
	public function getDefaultIdentiyMethod(int $fileUserId): IdentifyMethod {
		$identifyMethods = $this->identifyMethodMapper->getIdentifyMethodsFromFileUserId($fileUserId);
		$default = array_filter($identifyMethods, function(IdentifyMethod $current): bool {
			return $current->getDefault() === 1;
		});
		if (!$default) {
			return $this->config->getAppValue(Application::APP_ID, 'identify_method', self::IDENTIFTY_NEXTCLOUD) ?? self::IDENTIFTY_NEXTCLOUD;
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
