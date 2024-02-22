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

use OC\SystemConfig;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngine;
use OCA\Libresign\Handler\JSignPdfHandler;
use OCA\Libresign\Helper\ConfigureCheckHelper;
use OCP\AppFramework\Services\IAppConfig;

class ConfigureCheckService {
	public function __construct(
		private IAppConfig $appConfig,
		private SystemConfig $systemConfig,
		private JSignPdfHandler $jSignPdfHandler,
		private CertificateEngine $certificateEngine
	) {
	}

	/**
	 * Get result of all checks
	 *
	 * @return ConfigureCheckHelper[]
	 */
	public function checkAll(): array {
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
		return $return;
	}

	/**
	 * Check all requirements to use JSignPdf
	 *
	 * @return ConfigureCheckHelper[]
	 */
	public function checkJSignPdf(): array {
		$signatureEngine = $this->appConfig->getAppValue('signature_engine', 'jsignpdf');
		if ($signatureEngine !== 'jsignpdf') {
			return [];
		}
		$jsignpdJarPath = $this->appConfig->getAppValue('jsignpdf_jar_path');
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
	 * Check all requirements to use PDFtk
	 *
	 * @return ConfigureCheckHelper[]
	 */
	public function checkPdftk(): array {
		$pdftkPath = $this->appConfig->getAppValue('pdftk_path');
		if ($pdftkPath) {
			if (file_exists($pdftkPath)) {
				$javaPath = $this->appConfig->getAppValue('java_path');
				\exec($javaPath . ' -jar ' . $pdftkPath . " --version 2>&1", $version);
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

	/**
	 * Check all requirements to use Java
	 *
	 * @return ConfigureCheckHelper[]
	 */
	private function checkJava(): array {
		$signatureEngine = $this->appConfig->getAppValue('signature_engine', 'jsignpdf');
		if ($signatureEngine !== 'jsignpdf') {
			return [];
		}
		$javaPath = $this->appConfig->getAppValue('java_path');
		if ($javaPath) {
			if (file_exists($javaPath)) {
				\exec($javaPath . " -version 2>&1", $javaVersion);
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
					->setTip('Run occ libresign:configure:openssl --help or occ libresign:configure:cfssl --help'),
			];
		}
		return $return;
	}
}
