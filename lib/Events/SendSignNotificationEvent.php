<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Events;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCP\EventDispatcher\Event;

class SendSignNotificationEvent extends Event {
	public const FILE_TO_SIGN = 'libresign_file_to_sign';
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
