<?php

namespace OCA\Libresign\Service;

use ImagickException;
use OC\SystemConfig;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\JSignPdfHandler;
use OCA\Libresign\Helper\ConfigureCheckHelper;
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

	/**
	 * Get result of all checks
	 *
	 * @return ConfigureCheckHelper[]
	 */
	public function checkAll(): array {
		$result = [];
		$result = array_merge($result, $this->checkSign());
		$result = array_merge($result, $this->canPreview());
		$result = array_merge($result, $this->checkCfssl());
		return $result;
	}

	/**
	 * Check all requirements to sign
	 *
	 * @return ConfigureCheckHelper[]
	 */
	public function checkSign(): array {
		$return = [];
		$return = array_merge($return, $this->checkJava());
		$return = array_merge($return, $this->checkJSignPdf());
		$return = array_merge($return, $this->checkLibresignCli());
		return $return;
	}

	/**
	 * Can preview PDF Files
	 *
	 * @return ConfigureCheckHelper[]
	 */
	public function canPreview(): array {
		if (!extension_loaded('imagick')) {
			return [
				(new ConfigureCheckHelper())
					->setErrorMessage('Extension Imagick required')
					->setResource('imagick')
					->setTip('https://github.com/LibreSign/libresign/issues/829'),
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
					(new ConfigureCheckHelper())
						->setErrorMessage('Is necessary to configure the ImageMagick security policy to work with PDF.')
						->setResource('imagick')
						->setTip('https://github.com/LibreSign/libresign/issues/829'),
				];
			}
		}
		return [
			(new ConfigureCheckHelper())
				->setSuccessMessage('Can generate the preview')
				->setResource('imagick'),
		];
	}

	/**
	 * Check all requirements to use JSignPdf
	 *
	 * @return ConfigureCheckHelper[]
	 */
	public function checkJSignPdf(): array {
		$jsignpdJarPath = $this->config->getAppValue(Application::APP_ID, 'jsignpdf_jar_path');
		if ($jsignpdJarPath) {
			if (file_exists($jsignpdJarPath)) {
				return [
					(new ConfigureCheckHelper())
						->setSuccessMessage('JSignPdf version: ' . JSignPdfHandler::VERSION)
						->setResource('jsignpdf'),
					(new ConfigureCheckHelper())
						->setSuccessMessage('JSignPdf path: ' . $jsignpdJarPath)
						->setResource('jsignpdf'),
				];
			}
			return [
				(new ConfigureCheckHelper())
					->setErrorMessage('JSignPdf binary not found: ' . $jsignpdJarPath)
					->setResource('jsignpdf')
					->setTip('run occ libresign:install --jsignpdf'),
			];
		}
		return [
			(new ConfigureCheckHelper())
				->setErrorMessage('JSignPdf not found')
				->setResource('jsignpdf')
				->setTip('run occ libresign:install --jsignpdf'),
		];
	}

	/**
	 * Check all requirements to use Java
	 *
	 * @return ConfigureCheckHelper[]
	 */
	private function checkJava(): array {
		$javaPath = $this->config->getAppValue(Application::APP_ID, 'java_path');
		if ($javaPath) {
			if (file_exists($javaPath)) {
				$javaVersion = exec($javaPath . " -version 2>&1");
				return [
					(new ConfigureCheckHelper())
						->setSuccessMessage('Java version: ' . $javaVersion)
						->setResource('java'),
					(new ConfigureCheckHelper())
						->setSuccessMessage('Java binary: ' . $javaPath)
						->setResource('java'),
				];
			}
			return [
				(new ConfigureCheckHelper())
					->setErrorMessage('Java binary not found: ' . $javaPath)
					->setResource('java')
					->setTip('run occ libresign:install --java'),
			];
		}
		$javaVersion = exec("java -version 2>&1");
		$hasJavaVersion = strpos($javaVersion, 'not found') === false;
		if ($hasJavaVersion) {
			return [
				(new ConfigureCheckHelper())
					->setSuccessMessage('Using java from operational system. Version: ' . $javaVersion)
					->setResource('java')
					->setTip('run occ libresign:install --java'),
			];
		}
		return [
			(new ConfigureCheckHelper())
				->setErrorMessage('Java not installed')
				->setResource('java')
				->setTip('run occ libresign:install --java'),
		];
	}

	/**
	 * Check all requirements to use LibreSign CLI tool
	 *
	 * @return ConfigureCheckHelper[]
	 */
	private function checkLibresignCli(): array {
		$path = $this->config->getAppValue(Application::APP_ID, 'libresign_cli_path');
		if (!file_exists($path) || !is_executable($path)) {
			return [
				(new ConfigureCheckHelper())
					->setErrorMessage('LibreSign cli tools not found or without execute permission.')
					->setResource('libresign-cli')
					->setTip('Run occ libresign:install --cli'),
			];
		}
		return [
			(new ConfigureCheckHelper())
				->setSuccessMessage('LibreSign cli tools found in path: ' . $path)
				->setResource('libresign-cli'),
		];
	}

	/**
	 * Check all requirements to use CFSSL
	 *
	 * @return ConfigureCheckHelper[]
	 */
	public function checkCfssl(): array {
		if (PHP_OS_FAMILY === 'Windows') {
			return [
				(new ConfigureCheckHelper())
					->setErrorMessage('CFSSL is incompatible with Windows')
					->setResource('cfssl'),
			];
		}
		$cfsslInstalled = $this->config->getAppValue(Application::APP_ID, 'cfssl_bin');
		if (!$cfsslInstalled) {
			return [
				(new ConfigureCheckHelper())
					->setErrorMessage('CFSSL not installed.')
					->setResource('cfssl')
					->setTip('Run occ libresign:install --cfssl'),
			];
		}

		$instanceId = $this->systemConfig->getValue('instanceid', null);
		$binary = $this->systemConfig->getValue('datadirectory', \OC::$SERVERROOT . '/data/') . DIRECTORY_SEPARATOR .
			'appdata_' . $instanceId . DIRECTORY_SEPARATOR .
			Application::APP_ID . DIRECTORY_SEPARATOR .
			'cfssl';
		if (!file_exists($binary)) {
			return [
				(new ConfigureCheckHelper())
					->setErrorMessage('CFSSL not found.')
					->setResource('cfssl')
					->setTip('Run occ libresign:install --cfssl'),
			];
		}
		$return = [];
		$return[] = (new ConfigureCheckHelper())
			->setSuccessMessage('CFSSL binary path: ' . $binary)
			->setResource('cfssl');
		$version = str_replace("\n", ', ', trim(`$binary version`));
		$return[] = (new ConfigureCheckHelper())
			->setSuccessMessage('CFSSL: ' . $version)
			->setResource('cfssl');
		$configPath = $this->config->getAppValue(Application::APP_ID, 'configPath');
		if (!is_dir($configPath)) {
			$return[] = (new ConfigureCheckHelper())
				->setErrorMessage('CFSSL not configured.')
				->setResource('cfssl')
				->setTip('Run occ libresign:configure --cfssl');
		}
		return $return;
	}
}
