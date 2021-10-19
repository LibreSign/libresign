<?php

namespace OCA\Libresign\Handler;

use Jeidison\JSignPDF\JSignPDF;
use Jeidison\JSignPDF\Sign\JSignParam;
use OCA\Libresign\AppInfo\Application;
use OCP\Files\Node;
use OCP\IConfig;

class JSignPdfHandler implements ISignHandler {
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
	public function sign(
		Node $inputFile,
		Node $certificate,
		string $password
	): string {
		$param = $this->getJSignParam()
			->setCertificate($certificate->getContent())
			->setPdf($inputFile->getContent())
			->setPassword($password);
			// ->setJSignParameters('-llx 10 -lly 10 -urx 250 -ury 100 --bg-path ~/vidalu/Documents/Assinatura/assinatura-vitor/Vitor/assinatura.png -V');

		$jSignPdf = $this->getJSignPdf();
		$jSignPdf->setParam($param);
		return $jSignPdf->sign();
	}
}
