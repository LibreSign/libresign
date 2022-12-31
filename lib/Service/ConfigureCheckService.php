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
	private JSignPdfHandler $jSignPdfHandler;

	public function __construct(
		IConfig $config,
		SystemConfig $systemConfig,
		JSignPdfHandler $jSignPdfHandler
	) {
		$this->config = $config;
		$this->systemConfig = $systemConfig;
		$this->jSignPdfHandler = $jSignPdfHandler;
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

		if (!shell_exec(sprintf("which %s", escapeshellarg('ghostscript')))) {
			return [
				(new ConfigureCheckHelper())
					->setErrorMessage('Is necessary install ghostscript in your operational system to make possible Imagick read PDF files. This feature will be used only if you need to add visible signatures in your PDF files using the web interface.')
					->setResource('imagick')
					->setTip('https://www.php.net/manual/en/imagick.requirements.php '),
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
				if (!$this->isJavaOk()) {
					return [
						(new ConfigureCheckHelper())
							->setErrorMessage('Necessary Java to run JSignPdf')
							->setResource('jsignpdf')
							->setTip('Run occ libresign:install --java'),
					];
				}
				$jsignPdf = $this->jSignPdfHandler->getJSignPdf();
				$jsignPdf->setParam($this->jSignPdfHandler->getJSignParam());
				$currentVersion = $jsignPdf->getVersion();
				if ($currentVersion < JSignPdfHandler::VERSION) {
					if (!$currentVersion) {
						$message = 'Necessary install the version ' . JSignPdfHandler::VERSION;
					} else {
						$message = 'Necessary bump JSignPdf versin from ' . $currentVersion . ' to ' . JSignPdfHandler::VERSION;
					}
					$return[] = (new ConfigureCheckHelper())
						->setErrorMessage($message)
						->setResource('jsignpdf')
						->setTip('Run occ libresign:install --jsignpdf');
				}
				if ($currentVersion > JSignPdfHandler::VERSION) {
					$return[] = (new ConfigureCheckHelper())
						->setErrorMessage('Necessary downgrade JSignPdf versin from ' . $currentVersion . ' to ' . JSignPdfHandler::VERSION)
						->setResource('jsignpdf')
						->setTip('Run occ libresign:install --jsignpdf');
				}
				$return[] = (new ConfigureCheckHelper())
						->setSuccessMessage('JSignPdf version: ' . $currentVersion)
						->setResource('jsignpdf');
				$return[] = (new ConfigureCheckHelper())
						->setSuccessMessage('JSignPdf path: ' . $jsignpdJarPath)
						->setResource('jsignpdf');
				return $return;
			}
			return [
				(new ConfigureCheckHelper())
					->setErrorMessage('JSignPdf binary not found: ' . $jsignpdJarPath)
					->setResource('jsignpdf')
					->setTip('Run occ libresign:install --jsignpdf'),
			];
		}
		return [
			(new ConfigureCheckHelper())
				->setErrorMessage('JSignPdf not found')
				->setResource('jsignpdf')
				->setTip('Run occ libresign:install --jsignpdf'),
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
				\exec($javaPath . " -version 2>&1", $javaVersion);
				$javaVersion = current($javaVersion);
				if ($javaVersion !== InstallService::JAVA_VERSION) {
					return [
						(new ConfigureCheckHelper())
							->setErrorMessage(
								sprintf(
									"Invalid java version. Found: %s expected: %s",
									$javaVersion,
									InstallService::JAVA_VERSION
								)
							)
							->setResource('java')
							->setTip('Run occ libresign:install --java'),
					];
				}
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
					->setTip('Run occ libresign:install --java'),
			];
		}
		\exec("java -version 2>&1", $javaVersion);
		$javaVersion = current($javaVersion);
		$hasJavaVersion = strpos($javaVersion, 'not found') === false;
		if ($hasJavaVersion) {
			return [
				(new ConfigureCheckHelper())
					->setSuccessMessage('Using java from operational system. Version: ' . $javaVersion)
					->setResource('java')
					->setTip('Run occ libresign:install --java'),
			];
		}
		return [
			(new ConfigureCheckHelper())
				->setErrorMessage('Java not installed')
				->setResource('java')
				->setTip('Run occ libresign:install --java'),
		];
	}

	private function isJavaOk() : bool {
		$checkJava = $this->checkJava();
		$error = array_filter(
			$checkJava,
			function (ConfigureCheckHelper $config) {
				return $config->getStatus() === 'error';
			}
		);
		return empty($error);
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
		$return = [];
		$return = array_merge($return, $this->checkCfsslBinaries());
		$return = array_merge($return, $this->checkCfsslConfigure());
		return $return;
	}

	public function checkCfsslBinaries(): array {
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
		$version = str_replace("\n", ', ', trim(`$binary version`));
		if (strpos($version, InstallService::CFSSL_VERSION) === false) {
			return [
				(new ConfigureCheckHelper())
					->setErrorMessage(sprintf(
						'Invalid version. Expected: %s, actual: %s',
						InstallService::CFSSL_VERSION,
						$version
					))
					->setResource('cfssl')
					->setTip('Run occ libresign:install --cfssl')
			];
		}
		$return[] = (new ConfigureCheckHelper())
			->setSuccessMessage('CFSSL binary path: ' . $binary)
			->setResource('cfssl');
		$return[] = (new ConfigureCheckHelper())
			->setSuccessMessage('CFSSL: ' . $version)
			->setResource('cfssl');
		return $return;
	}

	public function checkCfsslConfigure(): array {
		$configPath = $this->config->getAppValue(Application::APP_ID, 'configPath');
		if (is_dir($configPath)) {
			return [(new ConfigureCheckHelper())
				->setSuccessMessage('Root certificate config files found.')
				->setResource('cfssl-configure')];
		}
		return [(new ConfigureCheckHelper())
			->setErrorMessage('CFSSL not configured.')
			->setResource('cfssl-configure')
			->setTip('Run occ libresign:configure --cfssl')];
	}
}
