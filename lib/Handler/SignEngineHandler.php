<?php

namespace OCA\Libresign\Handler;

use OCA\Libresign\DataObjects\VisibleElementAssoc;
use OCP\Files\File;

abstract class SignEngineHandler implements ISignEngineHandler {
	/** @var File */
	private $inputFile;
	/** @var File */
	private $certificate;
	/** @var string */
	private $password;
	/** @var VisibleElementAssoc[] */
	private $visibleElements = [];

	/**
	 * @return static
	 */
	public function setInputFile(File $inputFile): self {
		$this->inputFile = $inputFile;
		return $this;
	}

	public function getInputFile(): File {
		return $this->inputFile;
	}

	/**
	 * @return static
	 */
	public function setCertificate(File $certificate): self {
		$this->certificate = $certificate;
		return $this;
	}

	public function getCertificate(): File {
		return $this->certificate;
	}

	/**
	 * @return static
	 */
	public function setPassword(string $password): self {
		$this->password = $password;
		return $this;
	}

	public function getPassword(): string {
		return $this->password;
	}

	/**
	 * @param VisibleElementAssoc[] $visibleElements
	 *
	 * @return static
	 */
	public function setVisibleElements(array $visibleElements): self {
		$this->visibleElements = $visibleElements;
		return $this;
	}

	/**
	 * @return VisibleElementAssoc[]
	 *
	 * @psalm-return array<VisibleElementAssoc>
	 */
	public function getvisibleElements(): array {
		return $this->visibleElements;
	}
}
