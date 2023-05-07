<?php

declare(strict_types=1);

namespace OCA\Libresign\Event;

use OCA\Libresign\Service\SignFileService;
use OCP\EventDispatcher\Event;
use OCP\Files\File;

abstract class ASignedEvent extends Event {
	public function __construct(
		public SignFileService $fileService,
		public File $signedFile,
		public bool $allSigned
	) {
		parent::__construct();
	}
}
