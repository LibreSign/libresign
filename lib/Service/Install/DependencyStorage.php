<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Install;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Files\TSimpleFile;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IConfig;

class DependencyStorage {
	use TSimpleFile {
		getInternalPathOfFile as getInternalPathOfFileTrait;
		getInternalPathOfFolder as getInternalPathOfFolderTrait;
	}

	private IAppData $appData;

	public function __construct(
		IAppDataFactory $appDataFactory,
		private IConfig $config,
	) {
		$this->appData = $appDataFactory->get('libresign');
	}

	public function rootFolder(): ISimpleFolder {
		return $this->appData->getFolder('/');
	}

	public function resourceFolder(
		InstallTarget $target,
		string $path = '',
		bool $empty = false,
	): ISimpleFolder {
		if ($path === '') {
			$path = $target->architecture();
		} elseif ($path === 'java') {
			$path = $target->architecture() . '/' . $target->distro() . '/java';
		} else {
			$path = $target->architecture() . '/' . ltrim($path, '/');
		}

		$folder = $this->rootFolder();
		$parts = explode('/', $path);
		foreach ($parts as $index => $part) {
			$folder = $this->childFolder(
				$folder,
				$part,
				$empty && $index === array_key_last($parts),
			);
		}

		return $folder;
	}

	public function childFolder(
		ISimpleFolder $parent,
		string $name,
		bool $empty = false,
	): ISimpleFolder {
		try {
			$folder = $parent->getFolder($name);
			if (!$empty) {
				return $folder;
			}
			$folder->delete();
			return $parent->newFolder($name);
		} catch (NotFoundException) {
			try {
				return $parent->newFolder($name);
			} catch (NotPermittedException $e) {
				throw $this->permissionException('create', $parent, $e);
			}
		} catch (NotPermittedException $e) {
			throw $this->permissionException('access', $parent, $e);
		}
	}

	public function pathOfFolder(ISimpleFolder $folder): string {
		return $this->dataDirectory() . '/' . $this->getInternalPathOfFolderTrait($folder);
	}

	public function pathOfFile(ISimpleFile $file): string {
		return $this->dataDirectory() . '/' . $this->getInternalPathOfFileTrait($file);
	}

	private function dataDirectory(): string {
		return $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data/');
	}

	private function permissionException(
		string $operation,
		ISimpleFolder $parent,
		NotPermittedException $previous,
	): LibresignException {
		return new LibresignException(
			'LibreSign cannot ' . $operation . ' its dependency directory in app data. '
			. 'Check that the Nextcloud process can write to ' . $this->pathOfFolder($parent)
			. ' and review the Nextcloud server log for the original permission error.',
			previous: $previous,
		);
	}
}
