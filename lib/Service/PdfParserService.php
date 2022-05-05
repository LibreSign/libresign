<?php

namespace OCA\Libresign\Service;

use OC\SystemConfig;
use OCA\Libresign\AppInfo\Application;
use OCP\IConfig;

class PdfParserService {
	/** @var SystemConfig */
	private $systemConfig;
	/** @var IConfig */
	private $config;
	/** @var InstallService */
	private $installService;
	/** @var string */
	private $cliPath;

	public function __construct(
		IConfig $config,
		SystemConfig $systemConfig,
		InstallService $installService
	) {
		$this->systemConfig = $systemConfig;
		$this->config = $config;
		$this->installService = $installService;
		$this->cliPath = $this->getLibesignCli();
	}

	private function getDataDir(): string {
		return $this->systemConfig->getValue('datadirectory', \OC::$SERVERROOT . '/data/');
	}

	public function getMetadata(string $filePath): array {
		$fullPath = $this->getDataDir() . $filePath;
		$json = shell_exec($this->cliPath . ' info ' . $fullPath);
		$array = json_decode($json, true);
		$output = [
			'p' => count($array['pages']),
		];
		foreach ($array['pages'] as $page) {
			$output['d'][] = [
				'w' => $page['width'],
				'h' => $page['height'],
			];
		}
		return $output;
	}

	private function getLibesignCli(): string {
		$path = $this->config->getAppValue(Application::APP_ID, 'libresign_cli_path');
		if (!file_exists($path)) {
			$this->installService->installCli();
			$path = $this->config->getAppValue(Application::APP_ID, 'libresign_cli_path');
		}
		return $path;
	}
}
