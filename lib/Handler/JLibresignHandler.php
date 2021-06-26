<?php

namespace OCA\Libresign\Handler;

use Jeidison\JSignPDF\JSignPDF;
use Jeidison\JSignPDF\Sign\JSignParam;
use OCP\Files\File;

class JLibresignHandler {
	/** @var JSignPDF */
	private $jSignPdf;

	public function setJSignPdf($jSignPdf) {
		$this->jSignPdf = $jSignPdf;
	}

	public function getJSignPdf() {
		if (!$this->jSignPdf) {
			// @codeCoverageIgnoreStart
			$this->setJSignPdf(new JSignPDF());
			// @codeCoverageIgnoreEnd
		}
		return $this->jSignPdf;
	}

	public function signExistingFile(
		File $inputFile,
		File $certificate,
		string $password
	): array {
		$param = (new JSignParam())
			->setCertificate($certificate->getContent())
			->setPdf($inputFile->getContent())
			->setPassword($password)
			->setTempPath('/tmp/')
			->setIsUseJavaInstalled(true)
			->setjSignPdfJarPath('/opt/jsignpdf-1.6.4/JSignPdf.jar')
		;

		$jSignPdf = $this->getJSignPdf();
		$jSignPdf->setParam($param);
		$contentFileSigned = $jSignPdf->sign();

		return [
			'signed_'.$inputFile->getName(),
			$contentFileSigned,
		];
	}
}
