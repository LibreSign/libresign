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

namespace OCA\Libresign\Service\IdentifyMethod;

use OCA\Libresign\Db\FileUser;
use OCA\Libresign\Db\IdentifyMethod;

interface IIdentifyMethod {
	public function setEntity(IdentifyMethod $entity): void;
	public function getEntity(): IdentifyMethod;
	public function notify(bool $isNew): void;
	public function validate(): void;
	public function save(): void;
}
