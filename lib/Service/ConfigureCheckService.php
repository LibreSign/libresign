<?php

namespace OCA\Libresign\Service;

use ImagickException;
use OC\SystemConfig;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\JSignPdfHandler;
use OCP\IConfig;

class ConfigureCheckService {
	private IConfig $config;
	private SystemConfig $systemConfig;

	public function __construct(
		IConfig $config,
		SystemConfig $systemConfig
	) {
		$this->config = $config;
		$this->systemConfig = $systemConfig;
	}

	public function canPreview(): array {
		if (!extension_loaded('imagick')) {
			return [
				'errors' => ['Extension Imagick required'],
			];
		}

		$imagick = new \Imagick();
		$imagick->setResolution(100, 100);
		$pdf = file_get_contents(__DIR__ . '/../../tests/fixtures/small_valid.pdf');
		try {
			$imagick->readImageBlob($pdf);
		} catch (ImagickException $ie) {
			if ($ie->getCode() === 499) {
				return [
					'errors' => ['Necessary configure the security policy of ImageMagick to work with PDF. More informations: https://github.com/LibreSign/libresign/issues/829'],
				];
			}
		}
		return [
			'success' => ['Can generate preview'],
		];
	}

	public function checkSign(): array {
		$return = [];
		$return = array_merge_recursive($return, $this->checkJava());
		$return = array_merge_recursive($return, $this->checkJSignPdf());
		$return = array_merge_recursive($return, $this->checkLibresignCli());
		return $return;
	}

	public function checkJSignPdf(): array {
		$jsignpdJarPath = $this->config->getAppValue(Application::APP_ID, 'jsignpdf_jar_path');
		if ($jsignpdJarPath) {
			if (file_exists($jsignpdJarPath)) {
				return [
					'success' => [
						'JSignPdf version: ' . JSignPdfHandler::VERSION,
						'JSignPdf path: ' . $jsignpdJarPath,
					]
				];
			}
			return ['errors' => ['JSignPdf binary not found: ' . $jsignpdJarPath . ' run occ libresign:install --jsignpdf']];
		}
		return ['errors' => ['JSignPdf not found. run occ libresign:install --jsignpdf']];
	}

	private function checkJava(): array {
		$javaPath = $this->config->getAppValue(Application::APP_ID, 'java_path');
		if ($javaPath) {
			if (file_exists($javaPath)) {
				$javaVersion = exec($javaPath . " -version 2>&1");
				return [
					'success' => [
						'Java version: ' . $javaVersion,
						'Java binary: ' . $javaPath,
					]
				];
			}
			return ['errors' => ['Java binary not found: ' . $javaPath . ' run occ libresign:install --java']];
		}
		$javaVersion = exec("java -version 2>&1");
		$hasJavaVersion = strpos($javaVersion, 'not found') === false;
		if ($hasJavaVersion) {
			return ['success' => ['Using java from operational system. Version: ' . $javaVersion]];
		}
		return ['errors' => ['Java not installed.']];
	}

	private function checkLibresignCli(): array {
		$path = $this->config->getAppValue(Application::APP_ID, 'libresign_cli_path');
		if (!file_exists($path) || !is_executable($path)) {
			return ['errors' => ['LibreSign cli tools not found or without execute permission. Run occ libresign:install --cli']];
		}
		return ['success' => ['LibreSign cli tools found in path: ' . $path]];
	}

	public function checkCfssl(): array {
		if (PHP_OS_FAMILY === 'Windows') {
			return ['errors' => ['CFSSL is incompatible with Windows']];
		}
		$cfsslInstalled = $this->config->getAppValue(Application::APP_ID, 'cfssl_bin');
		if (!$cfsslInstalled) {
			return ['errors' => ['CFSSL not installed. Run occ libresign:install --cfssl']];
		}

		$instanceId = $this->systemConfig->getValue('instanceid', null);
		$binary = $this->systemConfig->getValue('datadirectory', \OC::$SERVERROOT . '/data/') . DIRECTORY_SEPARATOR .
			'appdata_' . $instanceId . DIRECTORY_SEPARATOR .
			Application::APP_ID . DIRECTORY_SEPARATOR .
			'cfssl';
		if (!file_exists($binary)) {
			return ['errors' => ['CFSSL not found. Run occ libresign:install --cfssl']];
		}
		$return = ['success' => ['CFSSL binary path: ' . $binary]];
		$version = str_replace("\n", ', ', trim(`$binary version`));
		$return = ['success' => ['CFSSL: ' . $version]];
		$configPath = $this->config->getAppValue(Application::APP_ID, 'configPath');
		if (!is_dir($configPath)) {
			$return = array_merge_recursive(
				$return,
				['errors' => ['CFSSL not configured. Run occ libresign:configure --cfssl']]
			);
		}
		return $return;
	}
}
