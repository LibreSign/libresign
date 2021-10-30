<?php

namespace OCA\Libresign\Handler;

use Jeidison\JSignPDF\JSignPDF;
use Jeidison\JSignPDF\Sign\JSignParam;
use OCA\Libresign\AppInfo\Application;
use OCP\Files\Node;
use OCP\IConfig;

class JSignPdfHandler extends SignEngineHandler {
	/** @var JSignPDF */
	private $jSignPdf;
	/** @var JSignParam */
	private $jSignParam;
	/** @var IConfig */
	private $config;

	public function __construct(
		IConfig $config
	) {
		$this->config = $config;
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
	 * @return JSignParam
	 */
	public function getJSignParam(): JSignParam {
		if (!$this->jSignParam) {
			$this->jSignParam = (new JSignParam())
				->setTempPath(
					$this->config->getAppValue(Application::APP_ID, 'jsignpdf_temp_path', '/tmp/')
				)
				->setIsUseJavaInstalled(true)
				->setjSignPdfJarPath(
					$this->config->getAppValue(Application::APP_ID, 'jsignpdf_jar_path', '/opt/jsignpdf-2.0.0/JSignPdf.jar')
				);
		}
		return $this->jSignParam;
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 * @param Node $inputFile
	 * @param Node $certificate
	 * @param string $password
	 * @return string
	 */
	public function sign(): string {
		$param = $this->getJSignParam()
			->setCertificate($this->getCertificate()->getContent())
			->setPdf($this->getInputFile()->getContent())
			->setPassword($this->getPassword());

		$signed = $this->signUsingVisibleElements();
		if ($signed) {
			return $signed;
		}
		$jSignPdf = $this->getJSignPdf();
		$jSignPdf->setParam($param);
		return $jSignPdf->sign();
	}

	private function signUsingVisibleElements(): string {
		$visibleElements = $this->getvisibleElements();
		if ($visibleElements) {
			$jSignPdf = $this->getJSignPdf();
			$param = $this->getJSignParam();
			foreach ($visibleElements as $element) {
				$param
					->setJSignParameters(
						' -pg ' . $element->getFileElement()->getPage() .
						' -llx ' . $element->getFileElement()->getLlx() .
						' -lly ' . $element->getFileElement()->getLly() .
						' -urx ' . $element->getFileElement()->getUrx() .
						' -ury ' . $element->getFileElement()->getUry() .
						' --l2-text ""' .
						' -V ' .
						' --bg-path ' . $element->getTempFile()
					);
				$jSignPdf->setParam($param);
				$signed = $jSignPdf->sign();
			}
			return $signed;
		}
		return '';
	}
}
