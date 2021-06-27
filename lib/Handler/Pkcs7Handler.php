<?php

namespace OCA\Libresign\Handler;

use OCP\Files\File;

class Pkcs7Handler {
	public function sign(
		File $inputFile,
		File $certificate,
		string $password
	): string {
		return $this->jSignPdfHandler->sign($inputFile, $certificate, $password);
	}
}
