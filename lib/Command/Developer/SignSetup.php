<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Command\Developer;

use OC\Core\Command\Base;
use OC\IntegrityCheck\Helpers\FileAccessHelper;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\Service\Install\SignSetupService;
use OCP\IConfig;
use phpseclib\Crypt\RSA;
use phpseclib\File\X509;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SignSetup extends Base {
	public function __construct(
		private IConfig $config,
		private FileAccessHelper $fileAccessHelper,
		private SignSetupService $signSetupService,
		private InstallService $installService,
	) {
		parent::__construct();
	}

	public function isEnabled(): bool {
		return $this->config->getSystemValue('debug', false) === true;
	}

	protected function configure(): void {
		$this
			->setName('libresign:developer:sign-setup')
			->setDescription('Sign the current setup')
			->addOption('privateKey', null, InputOption::VALUE_REQUIRED, 'Path to private key to use for signing')
			->addOption('certificate', null, InputOption::VALUE_REQUIRED, 'Path to certificate to use for signing')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$privateKeyPath = $input->getOption('privateKey');
		$keyBundlePath = $input->getOption('certificate');
		if (is_null($privateKeyPath) || is_null($keyBundlePath)) {
			$output->writeln('This command requires --privateKey and --certificate.');
			$output->writeln('Example: ./occ libresign:developer:sign-setup --privateKey="/libresign/private/myapp.key" --certificate="/libresign/public/mycert.crt"');
			return 1;
		}
		$privateKey = $this->fileAccessHelper->file_get_contents((string) $privateKeyPath);
		$keyBundle = $this->fileAccessHelper->file_get_contents((string) $keyBundlePath);
		if ($privateKey === false) {
			$output->writeln(sprintf('Private key "%s" does not exists.', $privateKeyPath));
			return 1;
		}

		if ($keyBundle === false) {
			$output->writeln(sprintf('Certificate "%s" does not exists.', $keyBundlePath));
			return 1;
		}

		$rsa = new RSA();
		$rsa->loadKey($privateKey);
		$x509 = new X509();
		$x509->loadX509($keyBundle);
		$x509->setPrivateKey($rsa);
		try {
			$this->signSetupService->setCertificate($x509);
			$this->signSetupService->setPrivateKey($rsa);
			foreach ($this->signSetupService->getArchitectures() as $architecture) {
				foreach ($this->installService->getAvailableResources() as $resource) {
					$this->signSetupService->writeAppSignature($architecture, $resource);
				}
			}
			$output->writeln('Successfully signed');
		} catch (\Exception $e) {
			$output->writeln('Error: ' . $e->getMessage());
			return 1;
		}
		return 0;
	}
}
