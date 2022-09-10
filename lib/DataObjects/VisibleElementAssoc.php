<?php

namespace OCA\Libresign\DataObjects;

use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\UserElement;

class VisibleElementAssoc {
	/** @var FileElement */
	private $fileElement;
	/** @var UserElement */
	private $userElement;
	/** @var string */
	private $tempFile;

	public function __construct(FileElement $fileElement, UserElement $userElement, string $tempFile) {
		$this->fileElement = $fileElement;
		$this->userElement = $userElement;
		$this->tempFile = $tempFile;
	}

	public function getFileElement(): FileElement {
		return $this->fileElement;
	}

	public function getUserElement(): UserElement {
		return $this->userElement;
	}

	public function getTempFile(): string {
		return $this->tempFile;
	}
}
