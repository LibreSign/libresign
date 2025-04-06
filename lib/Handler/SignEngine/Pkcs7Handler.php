<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\SignEngine;

use OCP\Files\File;

/**
 * @codeCoverageIgnore
 */
class Pkcs7Handler extends SignEngineHandler {
	public function sign(): File {
		$p7sFile = $this->getP7sFile();
		openssl_pkcs12_read($this->getCertificate(), $certificateData, $this->getPassword());
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

	public function getSignedContent(): string {
		return $this->sign()->getContent();
	}

	public function getP7sFile(): File {
		$newName = $this->getInputFile()->getName() . '.p7s';
		$p7sFile = $this->getInputFile()
			->getParent()
			->newFile($newName);
		return $p7sFile;
	}
}
