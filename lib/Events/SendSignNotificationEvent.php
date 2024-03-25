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

namespace OCA\Libresign\Events;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCP\EventDispatcher\Event;

class SendSignNotificationEvent extends Event {
	public function __construct(
		private SignRequest $signRequest,
		private FileEntity $libreSignFile,
		private IIdentifyMethod $identifyMethod,
	) {
	}

	public function getLibreSignFile(): FileEntity {
		return $this->libreSignFile;
	}

	public function getSignRequest(): SignRequest {
		return $this->signRequest;
	}

	public function getIdentifyMethod(): IIdentifyMethod {
		return $this->identifyMethod;
	}
}
