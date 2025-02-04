<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Libresign\Handler;

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
