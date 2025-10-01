<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Command\Developer;

use Exception;
use OC\Core\Command\Base;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\Service\Install\SignSetupService;
use OCA\Libresign\Vendor\phpseclib3\Crypt\RSA;
use OCA\Libresign\Vendor\phpseclib3\Exception\NoKeyLoadedException;
use OCA\Libresign\Vendor\phpseclib3\File\X509;
use OCP\IConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SignSetup extends Base {
	public function __construct(
		private IConfig $config,
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
		try {
			$privateKey = $this->fileGetContents((string)$privateKeyPath);
			$keyBundle = $this->fileGetContents((string)$keyBundlePath);
		} catch (\Throwable $th) {
			$output->writeln('<error>' . $th->getMessage() . '</error>');
			return 1;
		}

		try {
			$rsa = RSA::loadPrivateKey($privateKey);
		} catch (NoKeyLoadedException) {
			$output->writeln('Invalid private key');
			return 1;
		}
		$x509 = new X509();
		if ($x509->loadX509($keyBundle) === false) {
			$output->writeln('Invalid certificate');
			return 1;
		}
		$x509->setPrivateKey($rsa);
		try {
			$this->signSetupService->setCertificate($x509);
			$this->signSetupService->setPrivateKey($rsa);
			foreach ($this->signSetupService->getArchitectures() as $architecture) {
				foreach ($this->installService->getAvailableResources() as $resource) {
					if ($resource === 'java') {
						foreach (['linux', 'alpine-linux'] as $distro) {
							$this->signSetupService
								->setDistro($distro)
								->setArchitecture($architecture)
								->setResource($resource)
								->writeAppSignature();
						}
						continue;
					}
					$this->signSetupService
						->setArchitecture($architecture)
						->setResource($resource)
						->writeAppSignature();
				}
			}
			$output->writeln('Successfully signed');
		} catch (\Exception $e) {
			$output->writeln('Error: ' . $e->getMessage());
			return 1;
		}
		return 0;
	}

	/**
	 * Wrapper around file_get_contents($filename)
	 *
	 * @param string $filename
	 * @return string|false
	 */
	private function fileGetContents(string $path) {
		$filename = $path;
		$isRelative = false;
		if (!str_starts_with($filename, '/')) {
			$filename = \OC::$SERVERROOT . '/' . $filename;
			$isRelative = true;
		}
		$filename = realpath($filename);
		if (!$filename) {
			if ($isRelative) {
				throw new Exception(sprintf(
					"File %s does not exists.\nRoot dir: %s.\nCurrent dir: %s.\nLibreSign dir: %s",
					$path,
					\OC::$SERVERROOT,
					getcwd(),
					realpath(__DIR__ . '/../../../'),
				));
			}
			throw new Exception(sprintf('File "%s" does not exists.', $path));
		}
		return file_get_contents($filename);
	}
}
