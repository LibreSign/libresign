<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2024 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Libresign\Command\Developer;

use OC\Core\Command\Base;
use OC\IntegrityCheck\Helpers\FileAccessHelper;
use OCA\Libresign\Service\Install\SignFiles;
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
		private SignFiles $signFiles,
	) {
		parent::__construct();
	}

	public function isEnabled(): bool {
		return $this->config->getSystemValue('debug', false) === true;
	}

	protected function configure(): void {
		$this
			->setName('libresign:developer:sign-setup')
			->setDescription('Clean all LibreSign data')
			->addOption('privateKey', null, InputOption::VALUE_REQUIRED, 'Path to private key to use for signing')
			->addOption('certificate', null, InputOption::VALUE_REQUIRED, 'Path to certificate to use for signing')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$privateKeyPath = $input->getOption('privateKey');
		$keyBundlePath = $input->getOption('certificate');
		if (is_null($privateKeyPath) || is_null($keyBundlePath)) {
			$output->writeln('This command requires the --path, --privateKey and --certificate.');
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
			foreach ($this->signFiles->getArchitectures() as $architecture) {
				$this->signFiles->writeAppSignature($x509, $rsa, $architecture);
			}
			$output->writeln('Successfully signed');
		} catch (\Exception $e) {
			$output->writeln('Error: ' . $e->getMessage());
			return 1;
		}
		return 0;
	}
}
