<?php

namespace OCA\Libresign\Handler;

use OCP\Files\File;

class Pkcs7Handler {
	public function sign(
		File $fileToSign,
		File $certificate,
		string $password
	): File {
		$newName = $fileToSign->getName() . '.p7s';
		$p7sFile = $fileToSign
			->getParent()
			->newFile($newName);
		openssl_pkcs7_sign(
			$fileToSign->getInternalPath(),
			$p7sFile->getInternalPath(),
			$certificate->getContent(),
			$password,
			[]
		);
		return $p7sFile;
	}
}
