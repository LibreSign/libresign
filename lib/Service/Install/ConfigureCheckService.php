<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Install;

use OC\AppConfig;
use OC\SystemConfig;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\SignEngine\JSignPdfHandler;
use OCA\Libresign\Helper\ConfigureCheckHelper;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

class ConfigureCheckService {
	private string $architecture;
	private bool $isCacheDisabled = false;
	private array $result = [];
	public function __construct(
		private IAppConfig $appConfig,
		private SystemConfig $systemConfig,
		private AppConfig $ocAppConfig,
		private JSignPdfHandler $jSignPdfHandler,
		private CertificateEngineFactory $certificateEngineFactory,
		private SignSetupService $signSetupService,
		private LoggerInterface $logger,
	) {
		$this->architecture = php_uname('m');
	}

	public function disableCache(): void {
		$this->isCacheDisabled = true;
	}

	/**
	 * Get result of all checks
	 *
	 * @return ConfigureCheckHelper[]
	 */
	public function checkAll(): array {
		if ($this->isCacheDisabled) {
			$this->ocAppConfig->clearCache();
		}
		$result = [];
		$result = array_merge($result, $this->checkSign());
		$result = array_merge($result, $this->checkCertificate());
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
		$return = array_merge($return, $this->checkPdftk());
		$return = array_merge($return, $this->checkJSignPdf());
		$return = array_merge($return, $this->checkPoppler());
		return $return;
	}

	public function checkPoppler(): array {
		$return = $this->checkPdfSig();
		$return = array_merge($return, $this->checkPdfinfo());
		return $return;
	}

	public function checkPdfSig(): array {
		if (!empty($this->result['poppler'])) {
			return $this->result['poppler'];
		}
		if (shell_exec('which pdfsig') === null) {
			return $this->result['poppler'] = [
				(new ConfigureCheckHelper())
					->setInfoMessage('Poppler utils not installed')
					->setResource('pdfsig')
					->setTip('Install the package poppler-utils at your operational system to be possible get more details about validation of signatures.'),
			];
		}
		// The output of this command go to STDERR and shell_exec get the STDOUT
		// With 2>&1 the STRERR is redirected to STDOUT
		$version = shell_exec('pdfsig -v 2>&1');
		if (!$version) {
			return $this->result['poppler'] = [
				(new ConfigureCheckHelper())
					->setErrorMessage('Fail to retrieve pdfsig version')
					->setResource('pdfsig')
					->setTip("The command <pdfsig -v> executed by PHP haven't any output."),
			];
		}
		$version = preg_match('/pdfsig version (?<version>.*)/', $version, $matches);
		if (!$version) {
			return $this->result['poppler'] = [
				(new ConfigureCheckHelper())
					->setErrorMessage('Fail to retrieve pdfsig version')
					->setResource('pdfsig')
					->setTip("This is a poppler-utils dependency and wasn't possible to parse the output of command pdfsig -v"),
			];
		}
		return $this->result['poppler'] = [(new ConfigureCheckHelper())
			->setSuccessMessage('pdfsig version: ' . $matches['version'])
			->setResource('pdfsig')
		];
	}

	public function checkPdfinfo(): array {
		if (!empty($this->result['pdfinfo'])) {
			return $this->result['pdfinfo'];
		}
		if (shell_exec('which pdfinfo') === null) {
			return $this->result['pdfinfo'] = [
				(new ConfigureCheckHelper())
					->setInfoMessage('Poppler utils not installed')
					->setResource('pdfinfo')
					->setTip('Install the package poppler-utils at your operational system have a fallback to fetch page dimensions.'),
			];
		}
		// The output of this command go to STDERR and shell_exec get the STDOUT
		// With 2>&1 the STRERR is redirected to STDOUT
		$version = shell_exec('pdfinfo -v 2>&1');
		if (!$version) {
			return $this->result['pdfinfo'] = [
				(new ConfigureCheckHelper())
					->setErrorMessage('Fail to retrieve pdfinfo version')
					->setResource('pdfinfo')
					->setTip("The command <pdfinfo -v> executed by PHP haven't any output."),
			];
		}
		$version = preg_match('/pdfinfo version (?<version>.*)/', $version, $matches);
		if (!$version) {
			return $this->result['pdfinfo'] = [
				(new ConfigureCheckHelper())
					->setErrorMessage('Fail to retrieve pdfinfo version')
					->setResource('pdfinfo')
					->setTip("This is a poppler-utils dependency and wasn't possible to parse the output of command pdfinfo -v"),
			];
		}
		return $this->result['pdfinfo'] = [(new ConfigureCheckHelper())
			->setSuccessMessage('pdfinfo version: ' . $matches['version'])
			->setResource('pdfinfo')
		];
	}

	/**
	 * Check all requirements to use JSignPdf
	 *
	 * @return ConfigureCheckHelper[]
	 */
	public function checkJSignPdf(): array {
		if (!empty($this->result['jsignpdf'])) {
			return $this->result['jsignpdf'];
		}
		$jsignpdJarPath = $this->appConfig->getValueString(Application::APP_ID, 'jsignpdf_jar_path');
		if ($jsignpdJarPath) {
			$resultOfVerify = $this->verify('jsignpdf');
			if (count($resultOfVerify)) {
				[$errorMessage, $tip] = $this->getErrorAndTipToResultOfVerify($resultOfVerify, 'jsignpdf');
				return $this->result['jsignpdf'] = [
					(new ConfigureCheckHelper())
						->setErrorMessage($errorMessage)
						->setResource('jsignpdf')
						->setTip($tip),
				];
			}
			if (file_exists($jsignpdJarPath)) {
				if (!$this->isJavaOk()) {
					return $this->result['jsignpdf'] = [
						(new ConfigureCheckHelper())
							->setErrorMessage('Necessary Java to run JSignPdf')
							->setResource('jsignpdf')
							->setTip('Run occ libresign:install --java'),
					];
				}
				$jsignPdf = $this->jSignPdfHandler->getJSignPdf();
				$jsignPdf->setParam($this->jSignPdfHandler->getJSignParam());
				$currentVersion = $jsignPdf->getVersion();
				$return = [];
				if ($currentVersion < InstallService::JSIGNPDF_VERSION) {
					if (!$currentVersion) {
						$message = 'Necessary install the version ' . InstallService::JSIGNPDF_VERSION;
					} else {
						$message = 'Necessary bump JSignPdf versin from ' . $currentVersion . ' to ' . InstallService::JSIGNPDF_VERSION;
					}
					$return[] = (new ConfigureCheckHelper())
						->setErrorMessage($message)
						->setResource('jsignpdf')
						->setTip('Run occ libresign:install --jsignpdf');
				}
				if ($currentVersion > InstallService::JSIGNPDF_VERSION) {
					$return[] = (new ConfigureCheckHelper())
						->setErrorMessage('Necessary downgrade JSignPdf versin from ' . $currentVersion . ' to ' . InstallService::JSIGNPDF_VERSION)
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
			return $this->result['jsignpdf'] = [
				(new ConfigureCheckHelper())
					->setErrorMessage('JSignPdf binary not found: ' . $jsignpdJarPath)
					->setResource('jsignpdf')
					->setTip('Run occ libresign:install --jsignpdf'),
			];
		}
		return $this->result['jsignpdf'] = [
			(new ConfigureCheckHelper())
				->setErrorMessage('JSignPdf not found')
				->setResource('jsignpdf')
				->setTip('Run occ libresign:install --jsignpdf'),
		];
	}

	/**
	 * Check all requirements to use PDFtk
	 *
	 * @return ConfigureCheckHelper[]
	 */
	public function checkPdftk(): array {
		if (!empty($this->result['pdftk'])) {
			return $this->result['pdftk'];
		}
		$pdftkPath = $this->appConfig->getValueString(Application::APP_ID, 'pdftk_path');
		if ($pdftkPath) {
			$resultOfVerify = $this->verify('pdftk');
			if (count($resultOfVerify)) {
				[$errorMessage, $tip] = $this->getErrorAndTipToResultOfVerify($resultOfVerify, 'pdftk');
				return $this->result['pdftk'] = [
					(new ConfigureCheckHelper())
						->setErrorMessage($errorMessage)
						->setResource('pdftk')
						->setTip($tip),
				];
			}
			if (file_exists($pdftkPath)) {
				if (!$this->isJavaOk()) {
					return $this->result['pdftk'] = [
						(new ConfigureCheckHelper())
							->setErrorMessage('Necessary Java to run PDFtk')
							->setResource('jsignpdf')
							->setTip('Run occ libresign:install --java'),
					];
				}
				$javaPath = $this->appConfig->getValueString(Application::APP_ID, 'java_path');
				$version = [];
				\exec($javaPath . ' -jar ' . $pdftkPath . ' --version 2>&1', $version, $resultCode);
				if ($resultCode !== 0) {
					return $this->result['pdftk'] = [
						(new ConfigureCheckHelper())
							->setErrorMessage('Failure to check PDFtk version.')
							->setResource('java')
							->setTip('Run occ libresign:install --pdftk'),
					];
				}
				if (isset($version[0])) {
					preg_match('/pdftk port to java (?<version>.*) a Handy Tool/', $version[0], $matches);
					if (isset($matches['version'])) {
						if ($matches['version'] === InstallService::PDFTK_VERSION) {
							return $this->result['pdftk'] = [
								$this->result['pdftk'][] = (new ConfigureCheckHelper())
									->setSuccessMessage('PDFtk version: ' . InstallService::PDFTK_VERSION)
									->setResource('pdftk'),
								$this->result['pdftk'][] = (new ConfigureCheckHelper())
									->setSuccessMessage('PDFtk path: ' . $pdftkPath)
									->setResource('pdftk'),
							];
						}
						$message = 'Necessary install the version ' . InstallService::PDFTK_VERSION;
						return $this->result['pdftk'] = [
							(new ConfigureCheckHelper())
								->setErrorMessage($message)
								->setResource('jsignpdf')
								->setTip('Run occ libresign:install --jsignpdf')
						];
					}
				}
				return $this->result['pdftk'] = [
					(new ConfigureCheckHelper())
						->setErrorMessage('PDFtk binary is invalid: ' . $pdftkPath)
						->setResource('pdftk')
						->setTip('Run occ libresign:install --pdftk'),
				];
			}
			return $this->result['pdftk'] = [
				(new ConfigureCheckHelper())
					->setErrorMessage('PDFtk binary not found: ' . $pdftkPath)
					->setResource('pdftk')
					->setTip('Run occ libresign:install --pdftk'),
			];
		}
		return $this->result['pdftk'] = [
			(new ConfigureCheckHelper())
				->setErrorMessage('PDFtk not found')
				->setResource('pdftk')
				->setTip('Run occ libresign:install --pdftk'),
		];
	}

	public function isDebugEnabled(): bool {
		return $this->systemConfig->getValue('debug', false) === true;
	}

	private function verify(string $resource): array {
		$this->signSetupService->willUseLocalCert($this->isDebugEnabled());
		$result = $this->signSetupService->verify($this->architecture, $resource);
		if (count($result) === 1 && $this->isDebugEnabled()) {
			if (isset($result['SIGNATURE_DATA_NOT_FOUND'])) {
				return [];
			}
			if (isset($result['EMPTY_SIGNATURE_DATA'])) {
				return [];
			}
		}
		return $result;
	}

	private function getErrorAndTipToResultOfVerify(array $result, string $resource): array {
		if (count($result) === 1 && !$this->isDebugEnabled()) {
			if (isset($result['SIGNATURE_DATA_NOT_FOUND'])) {
				return [
					'Signature data not found.',
					"Sounds that you are running from source code of LibreSign.\nEnable debug mode by: occ config:system:set debug --value true --type boolean",
				];
			}
			if (isset($result['EMPTY_SIGNATURE_DATA'])) {
				return [
					'Your signature data is empty.',
					"Sounds that you are running from source code of LibreSign.\nEnable debug mode by: occ config:system:set debug --value true --type boolean",
				];
			}
		}
		if (isset($result['HASH_FILE_ERROR'])) {
			if ($this->isDebugEnabled()) {
				return [
					'Invalid hash of binaries files.',
					'Debug mode is enabled at your config.php and your LibreSign app was signed using a production signature. If you are not working at development of LibreSign, disable your debug mode or run the command: occ libresign install --' . $resource . ' --use-local-cert',
				];
			}
		}
		$this->logger->error('Invalid hash of binaries files', ['result' => $result]);
		return [
			'Invalid hash of binaries files.',
			'Check your nextcloud.log file an run occ libresign:install --all',
		];
	}

	/**
	 * Check all requirements to use Java
	 *
	 * @return ConfigureCheckHelper[]
	 */
	private function checkJava(): array {
		if (!empty($this->result['java'])) {
			return $this->result['java'];
		}
		$javaPath = $this->appConfig->getValueString(Application::APP_ID, 'java_path');
		if ($javaPath) {
			$resultOfVerify = $this->verify('java');
			if (count($resultOfVerify)) {
				[$errorMessage, $tip] = $this->getErrorAndTipToResultOfVerify($resultOfVerify, 'java');
				return $this->result['java'] = [
					(new ConfigureCheckHelper())
						->setErrorMessage($errorMessage)
						->setResource('java')
						->setTip($tip),
				];
			}
			if (file_exists($javaPath)) {
				\exec($javaPath . ' -version 2>&1', $javaVersion, $resultCode);
				if (empty($javaVersion)) {
					return $this->result['java'] = [
						(new ConfigureCheckHelper())
							->setErrorMessage(
								'Failed to execute Java. Sounds that your operational system is blocking the JVM.'
							)
							->setResource('java')
							->setTip('https://github.com/LibreSign/libresign/issues/2327#issuecomment-1961988790'),
					];
				}
				if ($resultCode !== 0) {
					return $this->result['java'] = [
						(new ConfigureCheckHelper())
							->setErrorMessage('Failure to check Java version.')
							->setResource('java')
							->setTip('Run occ libresign:install --java'),
					];
				}
				$javaVersion = current($javaVersion);
				if ($javaVersion !== InstallService::JAVA_VERSION) {
					return $this->result['java'] = [
						(new ConfigureCheckHelper())
							->setErrorMessage(
								sprintf(
									'Invalid java version. Found: %s expected: %s',
									$javaVersion,
									InstallService::JAVA_VERSION
								)
							)
							->setResource('java')
							->setTip('Run occ libresign:install --java'),
					];
				}
				\exec($javaPath . ' -XshowSettings:properties -version 2>&1', $output, $resultCode);
				preg_match('/native.encoding = (?<encoding>.*)\n/', implode("\n", $output), $matches);
				if (!isset($matches['encoding'])) {
					return $this->result['java'] = [
						(new ConfigureCheckHelper())
							->setErrorMessage('Java encoding not found.')
							->setResource('java')
							->setTip(sprintf('The command %s need to have native.encoding', $javaPath . ' -XshowSettings:properties -version')),
					];
				}
				if (!str_contains($matches['encoding'], 'UTF-8')) {
					return $this->result['java'] = [
						(new ConfigureCheckHelper())
							->setInfoMessage('Non-UTF-8 encoding detected. This may cause issues with accented or special characters')
							->setResource('java')
							->setTip(' Ensure the system encoding is UTF-8. You can check it using: locale charmap'),
					];
				}
				return $this->result['java'] = [
					(new ConfigureCheckHelper())
						->setSuccessMessage('Java version: ' . $javaVersion)
						->setResource('java'),
					(new ConfigureCheckHelper())
						->setSuccessMessage('Java binary: ' . $javaPath)
						->setResource('java'),
				];
			}
			return $this->result['java'] = [
				(new ConfigureCheckHelper())
					->setErrorMessage('Java binary not found: ' . $javaPath)
					->setResource('java')
					->setTip('Run occ libresign:install --java'),
			];
		}
		return $this->result['java'] = [
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
			fn (ConfigureCheckHelper $config) => $config->getStatus() === 'error'
		);
		return empty($error);
	}


	/**
	 * Check all requirements to use certificate
	 *
	 * @return ConfigureCheckHelper[]
	 */
	public function checkCertificate(): array {
		try {
			$return = $this->certificateEngineFactory->getEngine()->configureCheck();
		} catch (\Throwable) {
			$return = [
				(new ConfigureCheckHelper())
					->setErrorMessage('Define the certificate engine to use')
					->setResource('certificate-engine')
					->setTip(sprintf('Run occ libresign:configure:%s --help',
						$this->certificateEngineFactory->getEngine()->getName()
					)),
			];
		}
		return $return;
	}
}
