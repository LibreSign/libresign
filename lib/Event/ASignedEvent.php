<?php

declare(strict_types=1);

namespace OCA\LibreSign\Event;

use OCA\Libresign\Service\SignFileService;
use OCP\EventDispatcher\Event;
use OCP\Files\File;

abstract class ASignedEvent extends Event {
	/** @var SignFileService */
	public $fileService;
	/** @var \OCP\Files\Node */
	public $signedFile;
	/** @var bool */
	public $allSigned;

	public function __construct(
		SignFileService $fileService,
		File $signedFile,
		bool $allSigned
	) {
		parent::__construct();
		$this->fileService = $fileService;
		$this->signedFile = $signedFile;
		$this->allSigned = $allSigned;
	}
}
