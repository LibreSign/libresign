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
