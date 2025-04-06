<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\SignEngine;

use OCP\Files\File;

interface ISignEngineHandler {
	public function setInputFile(File $inputFile): self;
	public function getInputFile(): File;
	public function setCertificate(string $certificate): self;
	public function getCertificate(): string;
	public function readCertificate(): array;
	public function setPassword(string $password): self;
	public function getPassword(): string;
	public function sign(): File;
	public function getSignedContent(): string;
	public function getSignatureParams(): array;
	public function setSignatureParams(array $params): self;
}
