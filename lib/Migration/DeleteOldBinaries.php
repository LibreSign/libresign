<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class DeleteOldBinaries implements IRepairStep {
	protected IAppData $appData;
	protected IOutput $output;
	protected array $allowedFiles = [
		'x86_64',
		'aarch64',
		'openssl_config',
		'cfssl_config',
		'unauthenticated',
	];
	public function __construct(
		protected IAppDataFactory $appDataFactory,
	) {
		$this->appData = $appDataFactory->get('libresign');
	}

	public function getName(): string {
		return 'Delete old binaries.';
	}

	public function run(IOutput $output): void {
		$output->warning('Run the follow command first: files:scan-app-data libresign');
		$this->output = $output;
		$folder = $this->appData->getFolder('/');

		$list = $this->getDirectoryListing($folder);
		foreach ($list as $file) {
			if (!in_array($file->getName(), $this->allowedFiles)) {
				$file->delete();
			}
		}
	}

	private function getDirectoryListing(ISimpleFolder $node): array {
		$reflection = new \ReflectionClass($node);
		$reflectionProperty = $reflection->getProperty('folder');
		$reflectionProperty->setAccessible(true);
		$folder = $reflectionProperty->getValue($node);
		$list = $folder->getDirectoryListing();
		return $list;
	}
}
