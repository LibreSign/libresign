<?php

namespace OCA\Libresign\Handler;

use OCP\Files\Node;

/**
 * @codeCoverageIgnore
 */
class Pkcs7Handler {
	/**
	 * @psalm-suppress MixedReturnStatement
	 * @param Node $fileToSign
	 * @param Node $certificate
	 * @param string $passphrase
	 * @return Node
	 */
	public function sign(
		Node $fileToSign,
		Node $certificate,
		string $passphrase
	): Node {
		$newName = $fileToSign->getName() . '.p7s';
		$p7sFile = $fileToSign
			->getParent()
			->newFile($newName);
		openssl_pkcs12_read($certificate->getContent(), $certificateData, $passphrase);
		$tempntam = tempnam('/temp', 'pkey');
		file_put_contents($tempntam, $certificateData['pkey']);
		openssl_pkcs7_sign(
			$fileToSign->getInternalPath(),
			$p7sFile->getInternalPath(),
			'file:/' . $tempntam,
			$passphrase,
			[]
		);
		return $p7sFile;
	}
}
