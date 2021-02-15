<?php

namespace OCA\Libresign\Handler;

use Jeidison\JSignPDF\JSignPDF;
use Jeidison\JSignPDF\Sign\JSignParam;
use OC\Files\Node\File;

class JLibresignHandler {
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

		$jSignPdf = new JSignPDF($param);
		$contentFileSigned = $jSignPdf->sign();

		return [
			'signed_'.$inputFile->getName(),
			$contentFileSigned,
		];
	}
}
