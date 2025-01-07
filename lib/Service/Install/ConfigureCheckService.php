<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Libresign\Service\Install;

use OC\AppConfig;
use OC\SystemConfig;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngine;
use OCA\Libresign\Handler\JSignPdfHandler;
use OCA\Libresign\Helper\ConfigureCheckHelper;
use OCP\AppFramework\Services\IAppConfig;
use Psr\Log\LoggerInterface;

class ConfigureCheckService {
	private string $architecture;
	private bool $isCacheDisabled = false;
	public function __construct(
		private IAppConfig $appConfig,
		private SystemConfig $systemConfig,
		private AppConfig $ocAppConfig,
		private JSignPdfHandler $jSignPdfHandler,
		private CertificateEngine $certificateEngine,
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
		if (shell_exec('which pdfsigf') === null) {
			return [
				(new ConfigureCheckHelper())
					->setInfoMessage('Poppler utils not installed')
					->setResource('poppler-utils')
					->setTip('Install the package poppler-utils at your operational system to be possible get more details about validation of signatures.'),
			];
		}
		// The output of this command go to STDERR and shell_exec get the STDOUT
		// With 2>&1 the STRERR is redirected to STDOUT
		$version = shell_exec('pdfsig -v 2>&1');
		if (!$version) {
			return [
				(new ConfigureCheckHelper())
					->setInfoMessage('Fail to retrieve pdfsig version')
					->setResource('poppler-utils')
					->setTip("The command <pdfsig -v> executed by PHP haven't any output."),
			];
		}
		$version = preg_match('/pdfsig version (?<version>.*)/', $version, $matches);
		if (!$version) {
			return [
				(new ConfigureCheckHelper())
					->setInfoMessage('Fail to retrieve pdfsig version')
					->setResource('poppler-utils')
					->setTip("This is a poppler-utils dependency and wasn't possible to parse the output of command pdfsig -v"),
			];
		}
		return [(new ConfigureCheckHelper())
			->setSuccessMessage('pdfsig version: ' . $matches['version'])
			->setResource('pdfsig')
		];
	}

	/**
	 * Check all requirements to use JSignPdf
	 *
	 * @return ConfigureCheckHelper[]
	 */
	public function checkJSignPdf(): array {
		$jsignpdJarPath = $this->appConfig->getAppValue('jsignpdf_jar_path');
		if ($jsignpdJarPath) {
			$resultOfVerify = $this->verify('jsignpdf');
			if (count($resultOfVerify)) {
				[$errorMessage, $tip] = $this->getErrorAndTipToResultOfVerify($resultOfVerify);
				return [
					(new ConfigureCheckHelper())
						->setErrorMessage($errorMessage)
						->setResource('jsignpdf')
						->setTip($tip),
				];
			}
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
	 * Check all requirements to use PDFtk
	 *
	 * @return ConfigureCheckHelper[]
	 */
	public function checkPdftk(): array {
		$pdftkPath = $this->appConfig->getAppValue('pdftk_path');
		if ($pdftkPath) {
			$resultOfVerify = $this->verify('pdftk');
			if (count($resultOfVerify)) {
				[$errorMessage, $tip] = $this->getErrorAndTipToResultOfVerify($resultOfVerify);
				return [
					(new ConfigureCheckHelper())
						->setErrorMessage($errorMessage)
						->setResource('pdftk')
						->setTip($tip),
				];
			}
			if (file_exists($pdftkPath)) {
				if (!$this->isJavaOk()) {
					return [
						(new ConfigureCheckHelper())
							->setErrorMessage('Necessary Java to run PDFtk')
							->setResource('jsignpdf')
							->setTip('Run occ libresign:install --java'),
					];
				}
				$javaPath = $this->appConfig->getAppValue('java_path');
				$version = [];
				\exec($javaPath . ' -jar ' . $pdftkPath . ' --version 2>&1', $version, $resultCode);
				if ($resultCode !== 0) {
					return [
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
							$return[] = (new ConfigureCheckHelper())
								->setSuccessMessage('PDFtk version: ' . InstallService::PDFTK_VERSION)
								->setResource('pdftk');
							$return[] = (new ConfigureCheckHelper())
								->setSuccessMessage('PDFtk path: ' . $pdftkPath)
								->setResource('pdftk');
							return $return;
						}
						$message = 'Necessary install the version ' . InstallService::PDFTK_VERSION;
						$return[] = (new ConfigureCheckHelper())
							->setErrorMessage($message)
							->setResource('jsignpdf')
							->setTip('Run occ libresign:install --jsignpdf');
					}
				}
				return [
					(new ConfigureCheckHelper())
						->setErrorMessage('PDFtk binary is invalid: ' . $pdftkPath)
						->setResource('pdftk')
						->setTip('Run occ libresign:install --pdftk'),
				];
			}
			return [
				(new ConfigureCheckHelper())
					->setErrorMessage('PDFtk binary not found: ' . $pdftkPath)
					->setResource('pdftk')
					->setTip('Run occ libresign:install --pdftk'),
			];
		}
		return [
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

	private function getErrorAndTipToResultOfVerify(array $result): array {
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
		$this->logger->error('Invalid hash of binaries files', ['result' => $result]);
		return [
			'Invalid hash of binaries files.',
			'Run occ libresign:install --all',
		];
	}

	/**
	 * Check all requirements to use Java
	 *
	 * @return ConfigureCheckHelper[]
	 */
	private function checkJava(): array {
		$javaPath = $this->appConfig->getAppValue('java_path');
		if ($javaPath) {
			$resultOfVerify = $this->verify('java');
			if (count($resultOfVerify)) {
				[$errorMessage, $tip] = $this->getErrorAndTipToResultOfVerify($resultOfVerify);
				return [
					(new ConfigureCheckHelper())
						->setErrorMessage($errorMessage)
						->setResource('java')
						->setTip($tip),
				];
			}
			if (file_exists($javaPath)) {
				\exec($javaPath . ' -version 2>&1', $javaVersion, $resultCode);
				if (empty($javaVersion)) {
					return [
						(new ConfigureCheckHelper())
							->setErrorMessage(
								'Failed to execute Java. Sounds that your operational system is blocking the JVM.'
							)
							->setResource('java')
							->setTip('https://github.com/LibreSign/libresign/issues/2327#issuecomment-1961988790'),
					];
				}
				if ($resultCode !== 0) {
					return [
						(new ConfigureCheckHelper())
							->setErrorMessage('Failure to check Java version.')
							->setResource('java')
							->setTip('Run occ libresign:install --java'),
					];
				}
				$javaVersion = current($javaVersion);
				if ($javaVersion !== InstallService::JAVA_VERSION) {
					return [
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
	 * Check all requirements to use certificate
	 *
	 * @return ConfigureCheckHelper[]
	 */
	public function checkCertificate(): array {
		try {
			$return = $this->certificateEngine->getEngine()->configureCheck();
		} catch (\Throwable $th) {
			$return = [
				(new ConfigureCheckHelper())
					->setErrorMessage('Define the certificate engine to use')
					->setResource('certificate-engine')
					->setTip(sprintf('Run occ libresign:configure:%s --help',
						$this->certificateEngine->getEngine()->getName()
					)),
			];
		}
		return $return;
	}
}
