<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Files;

use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;

trait TSimpleFile {
	/**
	 * @todo check a best solution to don't use reflection
	 */
	public function getInternalPathOfFile(ISimpleFile $node): string {
		$reflection = new \ReflectionClass($node);
		if ($reflection->hasProperty('parentFolder')) {
			$reflectionProperty = $reflection->getProperty('parentFolder');
			$folder = $reflectionProperty->getValue($node);
			$path = $folder->getInternalPath() . '/' . $node->getName();
		} elseif ($reflection->hasProperty('file')) {
			$reflectionProperty = $reflection->getProperty('file');
			$file = $reflectionProperty->getValue($node);
			$path = $file->getPath();
		}
		return $path;
	}

	/**
	 * @todo check a best solution to don't use reflection
	 */
	private function getInternalPathOfFolder(ISimpleFolder $node): string {
		$reflection = new \ReflectionClass($node);
		$reflectionProperty = $reflection->getProperty('folder');
		$folder = $reflectionProperty->getValue($node);
		$path = $folder->getInternalPath();
		return $path;
	}
}
