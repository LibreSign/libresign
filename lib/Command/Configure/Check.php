<?php

declare(strict_types=1);

namespace OCA\Libresign\Command\Configure;

use OC\Core\Command\Base;
use OCA\Libresign\Service\ConfigureCheckService;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Check extends Base {
	private ConfigureCheckService $configureCheckService;
	public function __construct(
		ConfigureCheckService $configureCheckService
	) {
		parent::__construct();
		$this->configureCheckService = $configureCheckService;
	}

	protected function configure(): void {
		$this
			->setName('libresign:configure:check')
			->setDescription('Check configure')
			->addOption('preview',
				'p',
				InputOption::VALUE_NONE,
				'Check requirements to generate image preview'
			)
			->addOption('sign',
				's',
				InputOption::VALUE_NONE,
				'Check requirements to sign document'
			)
			->addOption('cfssl',
				'c',
				InputOption::VALUE_NONE,
				'Check requirements to use CFSSL API'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$preview = $input->getOption('preview');
		$sign = $input->getOption('sign');
		$cfssl = $input->getOption('cfssl');
		$all = (!$preview && !$sign && !$cfssl);

		$result = [];
		if ($all || $preview) {
			$result = array_merge_recursive($result, $this->configureCheckService->canPreview());
		}
		if ($all || $sign) {
			$result = array_merge_recursive($result, $this->configureCheckService->checkSign());
		}
		if ($all || $cfssl) {
			$result = array_merge_recursive($result, $this->configureCheckService->checkCfssl());
		}

		$table = new Table($output);
		if (count($result)) {
			if (array_key_exists('errors', $result)) {
				foreach ($result['errors'] as $error) {
					$table->addRow(['🔴', $error]);
				}
			}
			if (array_key_exists('success', $result)) {
				foreach ($result['success'] as $success) {
					$table->addRow(['🟢', $success]);
				}
			}
			$table
				->setHeaders(['Status', 'Description'])
				->setStyle('compact')
				->render();
		}
		return 0;
	}
}
