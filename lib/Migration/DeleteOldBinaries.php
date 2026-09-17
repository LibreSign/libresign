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
	/**
	 * Everything that LibreSign is expected to keep inside its appdata folder.
	 *
	 * This repair step runs on every update and deletes each node that isn't
	 * listed here, so a new entry stored in appdata MUST also be added to this
	 * list, otherwise it is silently removed when the admin updates the app.
	 *
	 * A string is the name of a node to keep as is, an array is a folder whose
	 * content is filtered by the same rules.
	 */
	protected array $allowedFiles = [
		// Binaries downloaded by InstallService, one folder per architecture
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
		// Certificate engine data and configuration
		'pki',
		'openssl_config',
		'cfssl_config',
		'generated_crl',
		// JSignPdf home, pointed by the jsignpdf_home app config
		'jsignpdf_home',
		// Admin customizations
		'signature',
		'certificate-policy.pdf',
		// Files of signers that have no home folder
		'unauthenticated',
		'guest_app',
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
		$folder = $this->getInternalFolder($this->appData->getFolder('/'));

		$this->deleteRecursive($folder, $this->allowedFiles);
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

	protected function deleteRecursive(Folder $folder, array $allowedFiles): void {
		$list = $folder->getDirectoryListing();
		foreach ($list as $node) {
			if (in_array($node->getName(), $allowedFiles, true)) {
				continue;
			}
			if (array_key_exists($node->getName(), $allowedFiles) && $node instanceof Folder) {
				$this->deleteRecursive($node, $allowedFiles[$node->getName()]);
				continue;
			}
			$node->delete();
		}
	}

	private function getInternalFolder(ISimpleFolder $node): Folder {
		$reflection = new \ReflectionClass($node);
		$reflectionProperty = $reflection->getProperty('folder');
		return $reflectionProperty->getValue($node);
	}
}
