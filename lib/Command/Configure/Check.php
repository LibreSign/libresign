<?php

declare(strict_types=1);

namespace OCA\Libresign\Command\Configure;

use OC\Core\Command\Base;
use OCA\Libresign\Service\ConfigureCheckService;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
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
		if ($all) {
			$result = $this->configureCheckService->checkAll();
		} else {
			if ($preview) {
				$result = array_merge($result, $this->configureCheckService->canPreview());
			}
			if ($sign) {
				$result = array_merge($result, $this->configureCheckService->checkSign());
			}
			if ($cfssl) {
				$result = array_merge($result, $this->configureCheckService->checkCfssl());
			}
		}

		if (count($result)) {
			$table = new Table($output);
			foreach ($result as $row) {
				$table->addRow([
					new TableCell($row->getStatus(), ['style' => new TableCellStyle([
						'bg' => $row->getStatus() === 'success' ? 'green' : 'red',
						'align' => 'center',
					])]),
					$row->getResource(),
					$row->getMessage(),
					$row->getTip(),
				]);
			}
			$table
				->setHeaders([
					'Status',
					'Resource',
					'Message',
					'Tip',
				])
				->setStyle('symfony-style-guide')
				->render();
		}
		return 0;
	}
}
