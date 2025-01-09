<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler;

use OCA\Libresign\DataObjects\VisibleElementAssoc;
use OCP\Files\File;

abstract class SignEngineHandler implements ISignEngineHandler {
	private File $inputFile;
	private string $certificate;
	private string $password = '';
	/** @var VisibleElementAssoc[] */
	private array $visibleElements = [];

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
	public function setCertificate(string $certificate): self {
		$this->certificate = $certificate;
		return $this;
	}

	public function getCertificate(): string {
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
