<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Command\SignatureElements;

use OCA\Libresign\Service\EmailSignerElementsService;
use OCA\Libresign\Service\FolderService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\Files\Folder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Explicit retention for guest images, including keys orphaned by secret rotation. */
class Cleanup extends Command {
	public function __construct(
		private FolderService $folderService,
		private ITimeFactory $timeFactory,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this->setName('libresign:signature-elements:cleanup')
			->setDescription('Purge old saved email signatures and anonymous session images, even when their policy is disabled')
			->addOption('days', null, InputOption::VALUE_REQUIRED, 'Delete folders whose images have not changed in this many days (required)')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'List eligible folders without deleting them');
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$days = filter_var($input->getOption('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		if ($days === false || $days === null) {
			$output->writeln('<error>Specify a positive number of days with --days.</error>');
			return self::INVALID;
		}
		$cutoff = $this->timeFactory->getDateTime()->modify('-' . $days . ' days')->getTimestamp();
		$dryRun = (bool)$input->getOption('dry-run');
		$count = 0;
		foreach ($this->folderService->getUnauthenticatedFolder()->getDirectoryListing() as $folder) {
			if (!$folder instanceof Folder) {
				continue;
			}
			if ($folder->getName() === EmailSignerElementsService::STORAGE_FOLDER) {
				foreach ($folder->getDirectoryListing() as $saved) {
					if ($saved instanceof Folder && preg_match('/^[a-f0-9]{64}$/D', $saved->getName())
						&& $this->purgeFolder($saved, '/^(signature|initial)\.png$/D', $cutoff, $dryRun, $output)) {
						$count++;
					}
				}
			} elseif (preg_match('/^[a-zA-Z0-9,-]{16,128}$/D', $folder->getName())
				&& $this->purgeFolder($folder, '/^(signature|initial)_[0-9]+\.png$/D', $cutoff, $dryRun, $output)) {
				$count++;
			}
		}
		$output->writeln(($dryRun ? 'Would delete ' : 'Deleted ') . $count . ' signature folders.');
		return self::SUCCESS;
	}

	private function purgeFolder(Folder $folder, string $pattern, int $cutoff, bool $dryRun, OutputInterface $output): bool {
		if ($folder->getMTime() >= $cutoff) {
			return false;
		}
		$files = $folder->getDirectoryListing();
		if ($files === []) {
			return false;
		}
		foreach ($files as $file) {
			// The unauthenticated container can also contain documents. Only purge
			// folders holding exclusively signature images with the expected names.
			if (!$file instanceof File || !preg_match($pattern, $file->getName()) || $file->getMTime() >= $cutoff) {
				return false;
			}
		}
		$output->writeln($folder->getName());
		if (!$dryRun) {
			$folder->delete();
		}
		return true;
	}
}
