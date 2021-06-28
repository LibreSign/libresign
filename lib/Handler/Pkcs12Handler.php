<?php

namespace OCA\Libresign\Handler;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FolderService;
use OCP\Files\File;

class Pkcs12Handler {

	/** @var string */
	private $pfxFilename = 'signature.pfx';
	/** @var FolderService */
	private $folderService;
	/** @var JSignPdfHandler */
	private $jSignPdfHandler;

	public function __construct(
		FolderService $folderService,
		JSignPdfHandler $jSignPdfHandler
	) {
		$this->folderService = $folderService;
		$this->jSignPdfHandler = $jSignPdfHandler;
	}

	public function savePfx($uid, $content): File {
		$this->folderService->setUserId($uid);
		$folder = $this->folderService->getFolder();
		if ($folder->nodeExists($this->pfxFilename)) {
			$file = $folder->get($this->pfxFilename);
			if (!$file instanceof File) {
				throw new LibresignException("path {$this->pfxFilename} already exists and is not a file!", 400);
			}
			$file->putContent($content);
			return $file;
		}

		$file = $folder->newFile($this->pfxFilename);
		$file->putContent($content);
		return $file;
	}

	/**
	 * Get pfx file
	 *
	 * @param string $uid user id
	 * @return \OCP\Files\Node
	 */
	public function getPfx($uid) {
		$this->folderService->setUserId($uid);
		$folder = $this->folderService->getFolder();
		if (!$folder->nodeExists($this->pfxFilename)) {
			throw new \Exception('Password to sign not defined. Create a password to sign', 400);
		}
		return $folder->get($this->pfxFilename);
	}

	public function sign(
		File $fileToSign,
		File $certificate,
		string $password
	): File {
		$signedContent = $this->jSignPdfHandler->sign($fileToSign, $certificate, $password);
		$fileToSign->putContent($signedContent);
		return $fileToSign;
	}
}
