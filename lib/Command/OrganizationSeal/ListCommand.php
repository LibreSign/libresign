<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Command\OrganizationSeal;

use OC\Core\Command\Base;
use OCA\Libresign\Service\OrganizationSeal\OrganizationSealStore;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends Base {
	public function __construct(
		private OrganizationSealStore $sealStore,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		parent::configure();
		$this
			->setName('libresign:organization-seal:list')
			->setDescription('List the organization seal certificates');
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->writeTableInOutputFormat($input, $output, $this->sealStore->list());
		return self::SUCCESS;
	}
}
