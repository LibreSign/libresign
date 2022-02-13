<?php

namespace OCA\Libresign\Handler;

use OCP\Files\File;
use OCP\Files\Node;

/**
 * @codeCoverageIgnore
 */
class Pkcs7Handler extends SignEngineHandler {
	/**
	 * @psalm-suppress MixedReturnStatement
	 * @param Node $fileToSign
	 * @param Node $certificate
	 * @param string $passphrase
	 * @return Node
	 */
	public function sign(): File {
		$p7sFile = $this->getP7sFile();
		openssl_pkcs12_read($this->getCertificate()->getContent(), $certificateData, $this->getPassword());
		openssl_pkcs7_sign(
			$this->getInputFile()->getInternalPath(),
			$p7sFile->getInternalPath(),
			$certificateData['cert'],
			$certificateData['pkey'],
			[],
			PKCS7_DETACHED
		);
		return $p7sFile;
	}

	public function getP7sFile(): File {
		$newName = $this->getInputFile()->getName() . '.p7s';
		$p7sFile = $this->getInputFile()
			->getParent()
			->newFile($newName);
		return $p7sFile;
	}
}
