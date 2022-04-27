<?php

namespace OCA\Libresign\Handler;

use OC\SystemConfig;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Command\Install;
use OCP\IConfig;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

class ToolCliHandler {
	/** @var SystemConfig */
	private $systemConfig;
	/** @var IConfig */
	private $config;
	/** @var Install */
	private $install;
	/** @var string */
	private $toolPath;
	/** @var string */
	private $dataDir;
	public function __construct(
		SystemConfig $systemConfig,
		IConfig $config,
		Install $install
	) {
		$this->systemConfig = $systemConfig;
		$this->config = $config;
		$this->install = $install;
		$this->toolPath = $this->getLibresignCli();
		$this->dataDir = $this->systemConfig->getValue('datadirectory', \OC::$SERVERROOT . '/data/');
	}

	public function getMetadata(string $filePath): array {
		$fullPath = $this->dataDir . $filePath;
		$json = shell_exec($this->toolPath . ' info ' . $fullPath);
		$array = json_decode($json, true);
		$output = [
			'p' => count($array['pages']),
			'extension' => 'pdf',
		];
		foreach ($array['pages'] as $page) {
			$output['d'][] = [
				'w' => $page['width'],
				'h' => $page['height'],
			];
		}
		return $output;
	}

	private function getLibresignCli(): string {
		$this->toolPath = $this->config->getAppValue(Application::APP_ID, 'libresign_cli_path');
		if (empty($this->toolPath) || !file_exists($this->toolPath)) {
			$this->install->run(new StringInput('--cli'), new NullOutput());
			$this->toolPath = $this->config->getAppValue(Application::APP_ID, 'libresign_cli_path');
		}
		return $this->toolPath;
	}
}
