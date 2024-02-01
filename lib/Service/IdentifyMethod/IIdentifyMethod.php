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

namespace OCA\Libresign\Service\IdentifyMethod;

use OCA\Libresign\Db\IdentifyMethod;
use OCP\IUser;

interface IIdentifyMethod {
	public function getName(): string;
	public function isEnabledAsSignatueMethod(): bool;
	public function setCodeSentByUser(string $code): void;
	public function setUser(?IUser $user): void;
	public function cleanEntity(): void;
	public function setEntity(IdentifyMethod $entity): void;
	public function getEntity(): IdentifyMethod;
	public function getSettings(): array;
	public function willNotifyUser(bool $willNotify): void;
	public function notify(bool $isNew): void;
	public function validateToRequest(): void;
	public function validateToCreateAccount(string $value): void;
	public function validateToIdentify(): void;
	public function validateToRenew(?IUser $user = null): void;
	public function save(): void;
	public function delete(): void;
}
