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

namespace OCA\Libresign\Helper;

use JsonSerializable;

/**
 * @method ConfigureCheckHelper setStatus(string $value)
 * @method string getStatus()
 * @method ConfigureCheckHelper setMessage(string $value)
 * @method string getMessage()
 * @method ConfigureCheckHelper setResource(string $value)
 * @method string getResource()
 * @method ConfigureCheckHelper setTip(string $value)
 * @method string getTip()
 */
class ConfigureCheckHelper implements JsonSerializable {
	use MagicGetterSetterTrait {
		MagicGetterSetterTrait::__call as __getSet;
	}
	private string $status = '';
	private string $message = '';
	private string $resource = '';
	private string $tip = '';

	public function __call($name, $arguments) {
		if (!preg_match('/^set(?<status>.+)Message/', $name, $matches)) {
			return $this->__getSet($name, $arguments);
		}
		$status = strtolower($matches['status']);
		if (!in_array($status, ['error', 'success', 'info'])) {
			throw new \LogicException(sprintf('Cannot set non existing message status %s.', $status));
		}
		$message = $arguments[0] ?? null;
		if (!is_string($message)) {
			throw new \LogicException(sprintf('Invalid error message %s.', var_export($arguments, true)));
		}
		if (count($arguments) > 1) {
			throw new \LogicException(sprintf('Need to have only an argument %s.', var_export($arguments, true)));
		}
		$this->setStatus($status);
		$this->setMessage($message);
		return $this;
	}

	public function jsonSerialize(): array {
		return [
			'status' => $this->getStatus(),
			'message' => $this->getMessage(),
			'resource' => $this->getResource(),
			'tip' => $this->getTip(),
		];
	}
}
