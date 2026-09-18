<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use OCA\Files\Command\ScanAppData;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\Service\Install\JSignPdfRelease;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\Folder;
use OCP\Files\IAppData;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Removes the binaries of previous LibreSign versions from the AppData folder.
 *
 * This step only deletes what it explicitly knows to be obsolete. Everything
 * else stays untouched: the AppData folder also holds data that belongs to the
 * admin and to the signers, and it is not the job of this step to know about
 * it.
 */
class DeleteOldBinaries implements IRepairStep {
	protected IAppData $appData;
	protected IOutput $output;

	/**
	 * Binary folders of the layout used before the binaries were split by
	 * architecture.
	 */
	private const OBSOLETE_ROOT_NODES = [
		'libresign-cli',
		'java',
		'jsignpdf',
		'pdftk',
		'cfssl',
	];

	private const ARCHITECTURES = [
		'x86_64',
		'aarch64',
	];

	private const LINUX_DISTRIBUTIONS = [
		'linux',
		'alpine-linux',
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

		$this->deleteObsoleteBinaries($this->getInternalFolder($this->appData->getFolder('/')));
	}

	private function scan(): void {
		$application = \OCP\Server::get(Application::class);
		$input = new ArrayInput([
			'command' => 'files:scan-app-data',
			'folder' => 'libresign',
		]);
		$application->add(\OCP\Server::get(ScanAppData::class));
		$application->setAutoExit(false);
		$output = new NullOutput();
		$application->run($input, $output);
	}

	protected function deleteObsoleteBinaries(Folder $root): void {
		foreach (self::OBSOLETE_ROOT_NODES as $name) {
			$this->getNode($root, $name)?->delete();
		}

		foreach (self::ARCHITECTURES as $architecture) {
			$this->deleteOutdatedReleases(
				$root,
				$architecture . '/jsignpdf',
				'jsignpdf-',
				'jsignpdf-' . JSignPdfRelease::VERSION,
			);

			foreach (self::LINUX_DISTRIBUTIONS as $distribution) {
				$this->deleteOutdatedReleases(
					$root,
					$architecture . '/' . $distribution . '/java',
					'jdk-',
					'jdk-' . InstallService::JAVA_URL_PATH_NAME . '-jre',
				);
			}
		}
	}

	/**
	 * Deletes the releases of a dependency that are no longer the one installed
	 * by InstallService.
	 *
	 * Only nodes named like a release of that dependency are considered, so
	 * anything else living in the folder is never touched.
	 */
	private function deleteOutdatedReleases(Folder $root, string $path, string $prefix, string $current): void {
		$folder = $this->getNode($root, $path);
		if (!$folder instanceof Folder) {
			return;
		}

		foreach ($folder->getDirectoryListing() as $node) {
			$name = $node->getName();
			if ($name === $current || !str_starts_with($name, $prefix)) {
				continue;
			}
			$node->delete();
		}
	}

	private function getNode(Folder $root, string $path): ?Node {
		try {
			return $root->get($path);
		} catch (NotFoundException) {
			return null;
		}
	}

	private function getInternalFolder(ISimpleFolder $node): Folder {
		$reflection = new \ReflectionClass($node);
		$reflectionProperty = $reflection->getProperty('folder');
		return $reflectionProperty->getValue($node);
	}
}
