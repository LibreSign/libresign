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

namespace OCA\Libresign\Tests\lib;

use OC\AllConfig;
use OC\SystemConfig;

class AllConfigOverwrite extends AllConfig {
	/** @var string[] */
	private array $overWrite = [];

	public function __construct(
		SystemConfig $systemConfig,
	) {
		parent::__construct($systemConfig);
	}

	public function getAppValue($appName, $key, $default = '') {
		if (isset($this->overWrite[$appName]) && isset($this->overWrite[$appName][$key])) {
			return $this->overWrite[$appName][$key];
		}

		return parent::getAppValue($appName, $key, $default);
	}

	public function setAppValue($appName, $key, $value) {
		$this->overWrite[$appName][$key] = $value;
	}
}
