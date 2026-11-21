<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Command\OrganizationSeal;

use OCA\Libresign\Service\OrganizationSeal\OrganizationSealStore;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Delete extends Command {
	public function __construct(
		private OrganizationSealStore $sealStore,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('libresign:organization-seal:delete')
			->setDescription('Delete an organization seal certificate')
			->addArgument('id', InputArgument::REQUIRED, 'Id of the seal, see libresign:organization-seal:list');
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		if (!$this->sealStore->delete((string)$input->getArgument('id'))) {
			$output->writeln('<error>Unknown organization seal.</error>');
			return self::FAILURE;
		}

		$output->writeln('Organization seal deleted.');
		return self::SUCCESS;
	}
}
