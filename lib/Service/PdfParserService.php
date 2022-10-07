<?php

namespace OCA\Libresign\Service;

use OC\SystemConfig;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
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
	}

	private function getDataDir(): string {
		return $this->systemConfig->getValue('datadirectory', \OC::$SERVERROOT . '/data/');
	}

	/**
	 * @param string $filePath
	 *
	 * @return (array[]|int)[]
	 *
	 * @throws LibresignException
	 *
	 * @psalm-return array{p: int, d?: non-empty-list<array{w: mixed, h: mixed}>}
	 */
	public function getMetadata(string $filePath): array {
		$fullPath = $this->getDataDir() . $filePath;
		$fullPath = realpath($fullPath);
		if ($fullPath === false) {
			throw new LibresignException('File not found on specified place.');
		}
		$cliPath = $this->getLibesignCli();
		$json = shell_exec($cliPath . ' info ' . escapeshellarg($fullPath));
		$array = json_decode($json, true);
		if (!is_array($array)) {
			throw new LibresignException('Impossible get metadata from this file. Check if you installed correctly the libresign-cli.');
		}
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
		if (!$this->cliPath) {
			$this->cliPath = $this->config->getAppValue(Application::APP_ID, 'libresign_cli_path');
			if (!file_exists($this->cliPath) || !is_executable($this->cliPath)) {
				$this->installService->installCli();
				$this->cliPath = $this->config->getAppValue(Application::APP_ID, 'libresign_cli_path');
			}
		}
		return $this->cliPath;
	}
}
