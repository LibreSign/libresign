<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Robin Appelman <robin@icewind.nl>
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
 *
 */

namespace OCA\Libresign\Tests\lib;

use OC\AppFramework\Services\AppConfig;
use OCP\IConfig;

class AppConfigOverwrite extends AppConfig {
	/** @var string[][] */
	private $overWrite = [];

	public function __construct(
		IConfig $config,
		private string $appName,
	) {
		parent::__construct($config, $appName);
	}

	public function getAppValue(string $key, string $default = ''): string {
		if (isset($this->overWrite[$this->appName]) && isset($this->overWrite[$this->appName][$key])) {
			return $this->overWrite[$this->appName][$key];
		}

		return parent::getAppValue($this->appName, $key, $default);
	}

	public function setAppValue(string $key, string $value): void {
		$this->overWrite[$this->appName][$key] = $value;
	}
}
