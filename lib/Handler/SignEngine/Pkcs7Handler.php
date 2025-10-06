<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\SignEngine;

use DateTime;
use OCP\Files\File;

class Pkcs7Handler extends SignEngineHandler {
	#[\Override]
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

	protected function getP7sFile(): File {
		$newName = $this->getInputFile()->getName() . '.p7s';
		$p7sFile = $this->getInputFile()
			->getParent()
			->newFile($newName);
		return $p7sFile;
	}

	/**
	 * @todo Replace this method by a real implementation that retrieves the certificate chain and not just the file's last modified time.
	 */
	#[\Override]
	public function getCertificateChain($resource): array {
		$metadata = stream_get_meta_data($resource);
		$lastModifiedTime = filemtime($metadata['uri']);
		return [
			[
				'signingTime' => (new DateTime())->setTimestamp($lastModifiedTime)->setTimezone(new \DateTimeZone('UTC')),
			],
		];
	}

}
