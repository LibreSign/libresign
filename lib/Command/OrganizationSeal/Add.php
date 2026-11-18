<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Command\OrganizationSeal;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\OrganizationSeal\OrganizationSealStore;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class Add extends Command {
	public const PASSWORD_ENV = 'LIBRESIGN_SEAL_PASSWORD';

	public function __construct(
		private OrganizationSealStore $sealStore,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('libresign:organization-seal:add')
			->setDescription('Add an organization seal certificate (PKCS#12) applied after the last signature when a request asks for it')
			->addArgument('label', InputArgument::REQUIRED, 'Label shown to requesters')
			->addArgument('pfx', InputArgument::REQUIRED, 'Path to the PKCS#12 (.p12/.pfx) file')
			->addOption('password-from-env', null, InputOption::VALUE_NONE, 'Read the certificate password from the ' . self::PASSWORD_ENV . ' environment variable instead of asking for it');
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$path = (string)$input->getArgument('pfx');
		$pfx = is_file($path) && is_readable($path) ? file_get_contents($path) : false;
		if ($pfx === false) {
			$output->writeln('<error>Cannot read the certificate file.</error>');
			return self::FAILURE;
		}

		$password = $this->getPassword($input, $output);
		if ($password === null) {
			$output->writeln('<error>No certificate password given.</error>');
			return self::FAILURE;
		}

		try {
			$seal = $this->sealStore->add((string)$input->getArgument('label'), $pfx, $password);
		} catch (LibresignException $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return self::FAILURE;
		}

		$output->writeln(sprintf('Organization seal added with id %s (%s, valid until %s).', $seal['id'], $seal['subject'], $seal['validTo']));
		return self::SUCCESS;
	}

	private function getPassword(InputInterface $input, OutputInterface $output): ?string {
		if ($input->getOption('password-from-env')) {
			$password = getenv(self::PASSWORD_ENV);
			return is_string($password) ? $password : null;
		}

		if (!$input->isInteractive()) {
			return null;
		}

		$question = (new Question('Certificate password: '))
			->setHidden(true)
			->setHiddenFallback(false);
		$password = (new QuestionHelper())->ask($input, $output, $question);
		return is_string($password) ? $password : '';
	}
}
