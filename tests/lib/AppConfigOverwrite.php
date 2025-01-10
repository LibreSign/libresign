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

use OC\AppConfig;
use OCP\IDBConnection;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

class AppConfigOverwrite extends AppConfig {
	/** @var string[][] */
	private $overWrite = [];

	public function __construct(
		IDBConnection $conn,
		LoggerInterface $logger,
		ICrypto $crypto,
	) {
		parent::__construct($conn, $logger, $crypto);
	}

	public function getValueMixed(
		string $app,
		string $key,
		string $default = '',
		?bool $lazy = false,
	): string {
		if (isset($this->overWrite[$app]) && isset($this->overWrite[$app][$key])) {
			return $this->overWrite[$app][$key];
		}

		return parent::getValue($app, $key, $default);
	}

	public function setValueMixed(
		string $app,
		string $key,
		string $value,
		bool $lazy = false,
		bool $sensitive = false,
	): bool {
		$this->overWrite[$app][$key] = $value;
		return true;
	}

	public function getValueArray(string $app, string $key, array $default = [], bool $lazy = false): array {
		if (isset($this->overWrite[$app]) && isset($this->overWrite[$app][$key])) {
			return $this->overWrite[$app][$key];
		}

		return parent::getValueArray($app, $key, $default);
	}

	public function setValueArray(string $app, string $key, array $value, bool $lazy = false, bool $sensitive = false): bool {
		$this->overWrite[$app][$key] = $value;
		return true;
	}

	public function setValueBool(string $app, string $key, bool $value, bool $lazy = false): bool {
		$this->overWrite[$app][$key] = $value;
		return true;
	}

	public function getValueBool(string $app, string $key, bool $default = false, bool $lazy = false): bool {
		if (isset($this->overWrite[$app]) && isset($this->overWrite[$app][$key])) {
			return $this->overWrite[$app][$key];
		}

		return parent::getValueBool($app, $key, $default);
	}
}
