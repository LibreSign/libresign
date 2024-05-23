<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler;

use OCP\Files\File;

interface ISignEngineHandler {
	public function setInputFile(File $inputFile): self;
	public function getInputFile(): File;
	public function setCertificate(string $certificate): self;
	public function getCertificate(): string;
	public function setPassword(string $password): self;
	public function getPassword(): string;
	/**
	 * Sign a file
	 *
	 * @return string|\OCP\Files\Node string of signed file or Node of signed file
	 */
	public function sign();
}
