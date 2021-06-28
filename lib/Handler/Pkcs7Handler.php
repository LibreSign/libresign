<?php

namespace OCA\Libresign\Handler;

use OCP\Files\File;

class Pkcs7Handler {
	public function sign(
		File $fileToSign,
		File $certificate,
<<<<<<< HEAD
		string $passphrase
=======
		string $password
>>>>>>> more-detail-in-me
	): File {
		$newName = $fileToSign->getName() . '.p7s';
		$p7sFile = $fileToSign
			->getParent()
			->newFile($newName);
<<<<<<< HEAD
		openssl_pkcs12_read($certificate->getContent(), $certificateData, $passphrase);
		$tempntam = tempnam('/temp', 'pkey');
		file_put_contents($tempntam, $certificateData['pkey']);
		openssl_pkcs7_sign(
			$fileToSign->getInternalPath(),
			$p7sFile->getInternalPath(),
			'file:/' . $tempntam,
			$passphrase,
=======
		openssl_pkcs7_sign(
			$fileToSign->getInternalPath(),
			$p7sFile->getInternalPath(),
			$certificate->getContent(),
			$password,
>>>>>>> more-detail-in-me
			[]
		);
		return $p7sFile;
	}
}
