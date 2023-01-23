<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCP\Files\File;
use OCP\IConfig;
use OCP\ITempManager;

class PdfParserService {
	/** @var IConfig */
	private $config;
	/** @var ITempManager */
	private $tempManager;
	/** @var InstallService */
	private $installService;
	/** @var string */
	private $cliPath;

	public function __construct(
		IConfig $config,
		ITempManager $tempManager,
		InstallService $installService
	) {
		$this->config = $config;
		$this->tempManager = $tempManager;
		$this->installService = $installService;
	}

	private function getDataDir(): string {
		return $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data/');
	}

	/**
	 * @param \OCP\Files\File $node
	 *
	 * @return (array[]|int)[]
	 *
	 * @throws LibresignException
	 *
	 * @psalm-return array{p: int, d?: non-empty-list<array{w: mixed, h: mixed}>}
	 */
	public function getMetadata(File $node): array {
		$content = $node->getContent();
		if (!$content) {
			throw new LibresignException('Empty file.');
		}

		/**
		 * Generate temporary file to prevent error when get path of
		 * shared file
		 */
		$tempFile = $this->tempManager->getTemporaryFile('.pdf');
		file_put_contents($tempFile, $content);

		$cliPath = $this->getLibesignCli();
		$json = shell_exec($cliPath . ' info ' . $tempFile . ' 2> /dev/null');
		unlink($tempFile);

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
