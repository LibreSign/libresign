<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use OC\Files\SetupManager;
use OC\Files\Utils\Scanner;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\Folder;
use OCP\Files\IAppData;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

class DeleteOldBinaries implements IRepairStep {
	protected IAppData $appData;
	protected IOutput $output;
	protected array $allowedFiles = [
		'x86_64' => [
			'alpine-linux' => [
				'java',
			],
			'linux' => [
				'java',
			],
			'cfssl',
			'jsignpdf',
			'pdftk',
		],
		'aarch64' => [
			'alpine-linux' => [
				'java',
			],
			'linux' => [
				'java',
			],
			'cfssl',
			'jsignpdf',
			'pdftk',
		],
		'pki',
		'openssl_config',
		'cfssl_config',
		'unauthenticated',
	];
	public function __construct(
		protected IAppDataFactory $appDataFactory,
	) {
		$this->appData = $appDataFactory->get('libresign');
	}

	#[\Override]
	public function getName(): string {
		return 'Delete old binaries';
	}

	#[\Override]
	public function run(IOutput $output): void {
		$this->scan();
		$this->output = $output;
		$folder = $this->appData->getFolder('/');

		$this->deleteInvalidFolder($folder, $this->allowedFiles);
	}

	private function scan(): void {
		$scanner = new Scanner(
			null,
			\OCP\Server::get(IDBConnection::class),
			\OCP\Server::get(IEventDispatcher::class),
			\OCP\Server::get(LoggerInterface::class),
			\OCP\Server::get(SetupManager::class),
		);
		$scanner->scan($this->getInternalFolder($this->appData->getFolder('/'))->getPath());
	}

	private function deleteInvalidFolder(ISimpleFolder $folder, array $allowedFiles): void {
		$list = $this->getSimpleFolderList($folder);
		foreach ($list as $node) {
			if (!in_array($node->getName(), $allowedFiles)) {
				if (in_array($node->getName(), array_keys($allowedFiles))) {
					$this->deleteRecursive($node, $allowedFiles[$node->getName()]);
					continue;
				}
				$node->delete();
			}
		}
	}

	private function deleteRecursive(Folder $folder, array $allowedFiles): void {
		$list = $folder->getDirectoryListing();
		foreach ($list as $node) {
			if (!in_array($node->getName(), $allowedFiles)) {
				if (in_array($node->getName(), array_keys($allowedFiles))) {
					$this->deleteRecursive($node, $allowedFiles[$node->getName()]);
					continue;
				}
				$node->delete();
			}
		}
	}

	private function getSimpleFolderList(ISimpleFolder $node): array {
		$folder = $this->getInternalFolder($node);
		$list = $folder->getDirectoryListing();
		return $list;
	}

	private function getInternalFolder(ISimpleFolder $node): Folder {
		$reflection = new \ReflectionClass($node);
		$reflectionProperty = $reflection->getProperty('folder');
		return $reflectionProperty->getValue($node);
	}
}
