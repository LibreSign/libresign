<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler;

use Imagick;
use Jeidison\JSignPDF\JSignPDF;
use Jeidison\JSignPDF\Sign\JSignParam;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignatureTextService;
use OCP\Files\File;
use OCP\IAppConfig;
use OCP\ITempManager;
use Psr\Log\LoggerInterface;

class JSignPdfHandler extends SignEngineHandler {
	/** @var JSignPDF */
	private $jSignPdf;
	/** @var JSignParam */
	private $jSignParam;

	public function __construct(
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
		private SignatureTextService $signatureTextService,
		private ITempManager $tempManager,
		private SignatureBackgroundService $signatureBackgroundService,
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
			$javaPath = $this->appConfig->getValueString(Application::APP_ID, 'java_path');
			$this->jSignParam = (new JSignParam())
				->setTempPath(
					$this->appConfig->getValueString(Application::APP_ID, 'jsignpdf_temp_path', sys_get_temp_dir() . DIRECTORY_SEPARATOR)
				)
				->setIsUseJavaInstalled(empty($javaPath))
				->setjSignPdfJarPath(
					$this->appConfig->getValueString(Application::APP_ID, 'jsignpdf_jar_path', '/opt/jsignpdf-' . InstallService::JSIGNPDF_VERSION . '/JSignPdf.jar')
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

		$hashAlgorithm = $this->appConfig->getValueString(Application::APP_ID, 'signature_hash_algorithm', 'SHA256');
		if (in_array($hashAlgorithm, ['SHA1', 'SHA256', 'SHA384', 'SHA512', 'RIPEMD160'])) {
			return $hashAlgorithm;
		}
		return 'SHA256';
	}

	public function sign(): File {
		$signedContent = $this->getSignedContent();
		$this->getInputFile()->putContent($signedContent);
		return $this->getInputFile();
	}

	public function getSignedContent(): string {
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
		$visibleElements = $this->getVisibleElements();
		if ($visibleElements) {
			$jSignPdf = $this->getJSignPdf();
			$param = $this->getJSignParam();
			$backgroundType = $this->signatureBackgroundService->getSignatureBackgroundType();
			$params = [
				'--l2-text' => $this->getSignatureText(),
				'--font-size' => $this->signatureTextService->getFontSize(),
				'-V' => null,
			];
			if ($backgroundType !== 'deleted') {
				$backgroundPath = $this->signatureBackgroundService->getImagePath();
			} else {
				$backgroundPath = '';
			}
			$originalParam = clone $param;
			foreach ($visibleElements as $element) {
				$params['-pg'] = $element->getFileElement()->getPage();
				$params['-llx'] = $element->getFileElement()->getLlx();
				$params['-lly'] = $element->getFileElement()->getLly();
				$params['-urx'] = $element->getFileElement()->getUrx();
				$params['-ury'] = $element->getFileElement()->getUry();
				$imagePath = $element->getTempFile();
				if ($backgroundType === 'deleted') {
					$params['--bg-path'] = $imagePath;
				} elseif ($params['--l2-text'] === '""') {
					if ($backgroundPath) {
						$params['--bg-path'] = $this->mergeBackground($backgroundPath, $imagePath);
					} else {
						$params['--bg-path'] = $imagePath;
					}
				} else {
					$params['--render-mode'] = 'GRAPHIC_AND_DESCRIPTION';
					$params['--img-path'] = $imagePath;
				}
				$param->setJSignParameters(
					$originalParam->getJSignParameters() .
					$this->listParamsToString($params)
				);
				$jSignPdf->setParam($param);
				$signed = $this->signWrapper($jSignPdf);
				$param->setPdf($signed);
			}
			return $signed;
		}
		return '';
	}

	private function mergeBackground(string $backgroundPath, string $signaturePath): string {
		$background = new Imagick($backgroundPath);
		$signature = new Imagick($signaturePath);

		$background->setImageFormat('png');
		$signature->setImageFormat('png');

		$background->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
		$signature->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);

		$backgroundWidth = $background->getImageWidth();
		$backgroundHeight = $background->getImageHeight();
		$signatureWidth = $signature->getImageWidth();
		$signatureHeight = $signature->getImageHeight();

		$x = (int)(($backgroundWidth - $signatureWidth) / 2);
		$y = (int)(($backgroundHeight - $signatureHeight) / 2);

		$background->compositeImage($signature, Imagick::COMPOSITE_OVER, $x, $y);

		$tmpPath = $this->tempManager->getTemporaryFile('_merged.png');
		if (!$tmpPath) {
			throw new \Exception('Temporary file not acessible');
		}
		$background->writeImage($tmpPath);

		$background->clear();
		$signature->clear();

		return $tmpPath;
	}

	public function getSignatureText(): string {
		$params = $this->getSignatureParams();
		$params['SignerName'] = '${signer}';
		$params['ServerSignatureDate'] = '${timestamp}';
		$data = $this->signatureTextService->parse(context: $params);
		if (!$data) {
			return '""';
		}

		$signatureText = '"' . str_replace(
			['"', '$'],
			['\"', '\$'],
			$data['parsed']
		) . '"';

		return $signatureText;
	}

	private function listParamsToString(array $params): string {
		$paramString = '';
		foreach ($params as $flag => $value) {
			$paramString .= ' ' . $flag;
			if ($value !== null && $value !== '') {
				$paramString .= ' ' . $value;
			}
		}
		return $paramString;
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
