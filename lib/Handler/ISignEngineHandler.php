<?php

namespace OCA\Libresign\Handler;

use OCP\Files\File;

interface ISignEngineHandler {
	public function setInputFile(File $inputFile): self;
	public function getInputFile(): File;
	public function setCertificate(File $certificate): self;
	public function getCertificate(): File;
	public function setPassword(string $password): self;
	public function getPassword(): string;
	/**
	 * Sign a file
	 *
	 * @return string|\OCP\Files\Node string of signed file or Node of signed file
	 */
	public function sign();
}
