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
		$newName = $this->getInputFile()->getName() . '.p7s';
		$p7sFile = $this->getInputFile()
			->getParent()
			->newFile($newName);
		openssl_pkcs12_read($this->getCertificate()->getContent(), $certificateData, $this->getPassword());
		$tempntam = tempnam('/temp', 'pkey');
		file_put_contents($tempntam, $certificateData['pkey']);
		openssl_pkcs7_sign(
			$this->getInputFile()->getInternalPath(),
			$p7sFile->getInternalPath(),
			'file:/' . $tempntam,
			$this->getPassword(),
			[]
		);
		return $p7sFile;
	}
}
