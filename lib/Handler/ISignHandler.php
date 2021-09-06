<?php

namespace OCA\Libresign\Handler;

use OCP\Files\File;

interface ISignHandler {
	public function sign(
		File $inputFile,
		File $certificate,
		string $password
	): string;
}
