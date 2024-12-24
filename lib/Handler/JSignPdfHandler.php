<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler;

use Jeidison\JSignPDF\JSignPDF;
use Jeidison\JSignPDF\Sign\JSignParam;
use OCA\Libresign\Exception\LibresignException;
use OCP\AppFramework\Services\IAppConfig;
use Psr\Log\LoggerInterface;

class JSignPdfHandler extends SignEngineHandler {
	/** @var JSignPDF */
	private $jSignPdf;
	/** @var JSignParam */
	private $jSignParam;
	public const VERSION = '2.2.2';

	public function __construct(
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
	}

	public function setJSignPdf(JSignPDF $jSignPdf): void {
		$this->jSignPdf = $jSignPdf;
	}

	public function getJSignPdf(): JSignPDF {
		if (!$this->jSignPdf) {
			// @codeCoverageIgnoreStart
			$this->setJSignPdf(new JSignPDF());
			// @codeCoverageIgnoreEnd
		}
		return $this->jSignPdf;
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 */
	public function getJSignParam(): JSignParam {
		if (!$this->jSignParam) {
			$javaPath = $this->appConfig->getAppValue('java_path');
			$this->jSignParam = (new JSignParam())
				->setTempPath(
					$this->appConfig->getAppValue('jsignpdf_temp_path', sys_get_temp_dir() . DIRECTORY_SEPARATOR)
				)
				->setIsUseJavaInstalled(empty($javaPath))
				->setjSignPdfJarPath(
					$this->appConfig->getAppValue('jsignpdf_jar_path', '/opt/jsignpdf-' . self::VERSION . '/JSignPdf.jar')
				);
			if (!empty($javaPath)) {
				if (!file_exists($javaPath)) {
					throw new \Exception('Invalid Java binary. Run occ libresign:install --java');
				}
				$this->jSignParam->setJavaPath($javaPath);
			}
		}
		return $this->jSignParam;
	}

	private function getHashAlgorithm(): string {
		/**
		 * Need to respect the follow code:
		 * https://github.com/intoolswetrust/jsignpdf/blob/JSignPdf_2_2_2/jsignpdf/src/main/java/net/sf/jsignpdf/types/HashAlgorithm.java#L46-L47
		 */
		$content = $this->getInputFile()->getContent();
		if (!$content) {
			return 'SHA1';
		}
		preg_match('/^%PDF-(?<version>\d+(\.\d+)?)/', $content, $match);
		if (isset($match['version'])) {
			$version = (float)$match['version'];
			if ($version < 1.6) {
				return 'SHA1';
			}
			if ($version < 1.7) {
				return 'SHA256';
			}
		}

		$hashAlgorithm = $this->appConfig->getAppValue('signature_hash_algorithm', 'SHA256');
		if (in_array($hashAlgorithm, ['SHA1', 'SHA256', 'SHA384', 'SHA512', 'RIPEMD160'])) {
			return $hashAlgorithm;
		}
		return 'SHA256';
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 */
	public function sign(): string {
		$param = $this->getJSignParam()
			->setCertificate($this->getCertificate())
			->setPdf($this->getInputFile()->getContent())
			->setPassword($this->getPassword());

		$signed = $this->signUsingVisibleElements();
		if ($signed) {
			return $signed;
		}
		$jSignPdf = $this->getJSignPdf();
		$jSignPdf->setParam($param);
		return $this->signWrapper($jSignPdf);
	}

	private function signUsingVisibleElements(): string {
		$visibleElements = $this->getvisibleElements();
		if ($visibleElements) {
			$jSignPdf = $this->getJSignPdf();
			$param = $this->getJSignParam();
			$originalParam = clone $param;
			foreach ($visibleElements as $element) {
				$param
					->setJSignParameters(
						$originalParam->getJSignParameters() .
						' -pg ' . $element->getFileElement()->getPage() .
						' -llx ' . $element->getFileElement()->getLlx() .
						' -lly ' . $element->getFileElement()->getLly() .
						' -urx ' . $element->getFileElement()->getUrx() .
						' -ury ' . $element->getFileElement()->getUry() .
						' --l2-text ""' .
						' -V' .
						' --bg-path ' . $element->getTempFile()
					);
				$jSignPdf->setParam($param);
				$signed = $this->signWrapper($jSignPdf);
				$param->setPdf($signed);
			}
			return $signed;
		}
		return '';
	}

	private function signWrapper(JSignPDF $jSignPDF): string {
		try {
			$param = $this->getJSignParam();
			$param
				->setJSignParameters(
					$this->jSignParam->getJSignParameters() .
					' --hash-algorithm ' . $this->getHashAlgorithm()
				);
			$jSignPDF->setParam($param);
			return $jSignPDF->sign();
		} catch (\Throwable $th) {
			$rows = str_getcsv($th->getMessage());
			$hashAlgorithm = array_filter($rows, fn ($r) => str_contains($r, 'The chosen hash algorithm'));
			if (!empty($hashAlgorithm)) {
				$hashAlgorithm = current($hashAlgorithm);
				$hashAlgorithm = trim($hashAlgorithm, 'INFO ');
				$hashAlgorithm = str_replace('\"', '"', $hashAlgorithm);
				$hashAlgorithm = preg_replace('/\.( )/', ".\n", $hashAlgorithm);
				throw new LibresignException($hashAlgorithm);
			}
			$this->logger->error('Error at JSignPdf side. LibreSign can not do nothing. Follow the error message: ' . $th->getMessage());
			throw new \Exception($th->getMessage());
		}
	}
}
