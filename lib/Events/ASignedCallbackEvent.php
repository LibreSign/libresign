<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Events;

use OCA\Libresign\Service\SignFileService;
use OCP\EventDispatcher\Event;
use OCP\Files\File;

abstract class ASignedCallbackEvent extends Event {
	public function __construct(
		public SignFileService $fileService,
		public File $signedFile,
		public bool $allSigned,
	) {
		parent::__construct();
	}
}
