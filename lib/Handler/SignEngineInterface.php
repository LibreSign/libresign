<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler;

use OCP\Files\File;

interface SignEngineInterface {
	/**
	 * Sign a file
	 *
	 * @return string string of signed file
	 */
	public function sign(
		File $inputFile,
		File $certificate,
		string $password,
	): string;
}
