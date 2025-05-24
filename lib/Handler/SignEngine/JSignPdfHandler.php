<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\SignEngine;

use Imagick;
use ImagickPixel;
use Jeidison\JSignPDF\JSignPDF;
use Jeidison\JSignPDF\Sign\JSignParam;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use OCP\Files\File;
use OCP\IAppConfig;
use OCP\ITempManager;
use Psr\Log\LoggerInterface;

class JSignPdfHandler extends Pkcs12Handler {
	/** @var JSignPDF */
	private $jSignPdf;
	/** @var JSignParam */
	private $jSignParam;
	private array $parsedSignatureText = [];

	public function __construct(
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
		private SignatureTextService $signatureTextService,
		private ITempManager $tempManager,
		private SignatureBackgroundService $signatureBackgroundService,
		protected CertificateEngineFactory $certificateEngineFactory,
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
			$tempPath = $this->appConfig->getValueString(Application::APP_ID, 'jsignpdf_temp_path', sys_get_temp_dir() . DIRECTORY_SEPARATOR);
			if (!is_writable($tempPath)) {
				throw new \Exception('The path ' . $tempPath . ' is not writtable. Fix this or change the LibreSign app setting jsignpdf_temp_path to a writtable path');
			}
			$jSignPdfJarPath = $this->appConfig->getValueString(Application::APP_ID, 'jsignpdf_jar_path', '/opt/jsignpdf-' . InstallService::JSIGNPDF_VERSION . '/JSignPdf.jar');
			if (!file_exists($jSignPdfJarPath)) {
				throw new \Exception('Invalid JSignPdf jar path. Run occ libresign:install --jsignpdf');
			}
			$this->jSignParam = (new JSignParam())
				->setTempPath($tempPath)
				->setIsUseJavaInstalled(empty($javaPath))
				->setjSignPdfJarPath($jSignPdfJarPath);
			if (!empty($javaPath)) {
				if (!file_exists($javaPath)) {
					throw new \Exception('Invalid Java binary. Run occ libresign:install --java');
				}
				$this->jSignParam->setJavaPath(
					$this->getEnvironments() .
					$javaPath .
					' -Duser.home=' . escapeshellarg($this->getHome()) . ' '
				);
			}
		}
		return $this->jSignParam;
	}

	private function getEnvironments(): string {
		return 'JSIGNPDF_HOME=' . escapeshellarg($this->getHome()) . ' ';
	}

	/**
	 * It's a workaround to create the folder structure that JSignPdf needs. Without
	 * this, the JSignPdf will return the follow message to all commands:
	 * > FINE Config file conf/conf.properties doesn't exists.
	 * > FINE Default property file /root/.JSignPdf doesn't exists.
	 */
	private function getHome(): string {
		$jSignPdfHome = $this->appConfig->getValueString(Application::APP_ID, 'jsignpdf_home', '');
		if ($jSignPdfHome) {
			return $jSignPdfHome;
		}
		$jsignpdfTempFolder = $this->tempManager->getTemporaryFolder('jsignpdf');
		if (!$jsignpdfTempFolder) {
			throw new \Exception('Temporary file not accessible');
		}
		mkdir(
			directory: $jsignpdfTempFolder . '/conf',
			recursive: true
		);
		$configFile = fopen($jsignpdfTempFolder . '/conf/conf.properties', 'w');
		fclose($configFile);
		$propertyFile = fopen($jsignpdfTempFolder . '/.JSignPdf', 'w');
		fclose($propertyFile);
		return $jsignpdfTempFolder;
	}

	private function getHashAlgorithm(): string {
		$hashAlgorithm = $this->appConfig->getValueString(Application::APP_ID, 'signature_hash_algorithm', 'SHA256');
		/**
		 * Need to respect the follow code:
		 * https://github.com/intoolswetrust/jsignpdf/blob/JSignPdf_2_2_2/jsignpdf/src/main/java/net/sf/jsignpdf/types/HashAlgorithm.java#L46-L47
		 */
		$content = $this->getInputFile()->getContent();
		preg_match('/^%PDF-(?<version>\d+(\.\d+)?)/', $content, $match);
		if (isset($match['version'])) {
			$version = (float)$match['version'];
			if ($version < 1.6) {
				return 'SHA1';
			}
			if ($version < 1.7) {
				return 'SHA256';
			}
			if ($version >= 1.7 && $hashAlgorithm === 'SHA1') {
				return 'SHA256';
			}
		}

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

			$renderMode = $this->signatureTextService->getRenderMode();

			$params = [
				'--l2-text' => $this->getSignatureText(),
				'-V' => null,
			];

			$fontSize = $this->parseSignatureText()['templateFontSize'];
			if ($fontSize === 10.0 || !$fontSize || $params['--l2-text'] === '""') {
				$fontSize = 0;
			}

			$backgroundType = $this->signatureBackgroundService->getSignatureBackgroundType();
			if ($backgroundType !== 'deleted') {
				$backgroundPath = $this->signatureBackgroundService->getImagePath();
			} else {
				$backgroundPath = '';
			}

			$param = $this->getJSignParam();
			$originalParam = clone $param;

			foreach ($visibleElements as $element) {
				$params['-pg'] = $element->getFileElement()->getPage();
				if ($params['-pg'] <= 1) {
					unset($params['-pg']);
				}
				$params['-llx'] = $element->getFileElement()->getLlx();
				$params['-lly'] = $element->getFileElement()->getLly();
				$params['-urx'] = $element->getFileElement()->getUrx();
				$params['-ury'] = $element->getFileElement()->getUry();

				$scaleFactor = $this->getScaleFactor($params['-urx'] - $params['-llx']);
				if ($fontSize) {
					$params['--font-size'] = $fontSize * $scaleFactor;
				}

				$signatureImagePath = $element->getTempFile();
				if ($backgroundType === 'deleted') {
					if ($renderMode === SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION) {
						$params['--render-mode'] = SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION;
						$params['--img-path'] = $this->createTextImage(
							width: ($params['-urx'] - $params['-llx']),
							height: ($params['-ury'] - $params['-lly']),
							fontSize: $this->signatureTextService->getSignatureFontSize() * $scaleFactor,
							scaleFactor: $scaleFactor < 5 ? 5 : $scaleFactor,
						);
					} elseif ($signatureImagePath) {
						$params['--bg-path'] = $signatureImagePath;
					}
				} elseif ($params['--l2-text'] === '""') {
					if ($backgroundPath) {
						$params['--bg-path'] = $this->mergeBackgroundWithSignature(
							$backgroundPath,
							$signatureImagePath,
							$scaleFactor < 5 ? 5 : $scaleFactor
						);
					} else {
						$params['--bg-path'] = $signatureImagePath;
					}
				} else {
					if ($renderMode === SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION) {
						$params['--render-mode'] = SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION;
						$params['--bg-path'] = $backgroundPath;
						$params['--img-path'] = $signatureImagePath;
					} elseif ($renderMode === SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION) {
						$params['--render-mode'] = SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION;
						$params['--bg-path'] = $backgroundPath;
						$params['--img-path'] = $this->createTextImage(
							width: (int)(($params['-urx'] - $params['-llx']) / 2),
							height: $params['-ury'] - $params['-lly'],
							fontSize: $this->signatureTextService->getSignatureFontSize() * $scaleFactor,
							scaleFactor: $scaleFactor < 5 ? 5 : $scaleFactor,
						);

					} else {
						// --render-mode DESCRIPTION_ONLY, this is the default
						// render-mode, because this, is unecessary to set here
						$params['--bg-path'] = $backgroundPath;
					}
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

	private function getScaleFactor(float $width): float {
		$systemWidth = $this->signatureTextService->getFullSignatureWidth();
		if (!$systemWidth) {
			return 1;
		}
		$widthScale = $width / $systemWidth;
		return $widthScale;
	}


	public function readCertificate(): array {
		return $this->certificateEngineFactory
			->getEngine()
			->readCertificate(
				$this->getCertificate(),
				$this->getPassword()
			);
	}

	private function createTextImage(int $width, int $height, float $fontSize, float $scaleFactor): string {
		$params = $this->getSignatureParams();
		if (!empty($params['SignerCommonName'])) {
			$commonName = $params['SignerCommonName'];
		} else {
			$certificateData = $this->readCertificate();
			$commonName = $certificateData['subject']['CN'];
		}
		$content = $this->signatureTextService->signerNameImage(
			width: $width,
			height: $height,
			text: $commonName,
			fontSize: $fontSize,
			scale: $scaleFactor,
		);

		$tmpPath = $this->tempManager->getTemporaryFile('_text_image.png');
		if (!$tmpPath) {
			throw new \Exception('Temporary file not accessible');
		}
		file_put_contents($tmpPath, $content);
		return $tmpPath;
	}

	private function mergeBackgroundWithSignature(string $backgroundPath, string $signaturePath, float $scaleFactor): string {
		$baseWidth = $this->signatureTextService->getFullSignatureWidth();
		$baseHeight = $this->signatureTextService->getFullSignatureHeight();

		$canvasWidth = round($baseWidth * $scaleFactor);
		$canvasHeight = round($baseHeight * $scaleFactor);

		$background = new Imagick($backgroundPath);
		$signature = new Imagick($signaturePath);

		$background->setImageFormat('png');
		$signature->setImageFormat('png');

		$background->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
		$signature->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);

		$background->resizeImage(
			(int)round($background->getImageWidth() * $scaleFactor),
			(int)round($background->getImageHeight() * $scaleFactor),
			Imagick::FILTER_LANCZOS,
			1
		);

		$signature->resizeImage(
			(int)round($signature->getImageWidth() * $scaleFactor),
			(int)round($signature->getImageHeight() * $scaleFactor),
			Imagick::FILTER_LANCZOS,
			1
		);

		$canvas = new Imagick();
		$canvas->newImage((int)$canvasWidth, (int)$canvasHeight, new ImagickPixel('transparent'));
		$canvas->setImageFormat('png32');
		$canvas->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);

		$bgX = (int)(($canvasWidth - $background->getImageWidth()) / 2);
		$bgY = (int)(($canvasHeight - $background->getImageHeight()) / 2);
		$canvas->compositeImage($background, Imagick::COMPOSITE_OVER, $bgX, $bgY);

		$sigX = (int)(($canvasWidth - $signature->getImageWidth()) / 2);
		$sigY = (int)(($canvasHeight - $signature->getImageHeight()) / 2);
		$canvas->compositeImage($signature, Imagick::COMPOSITE_OVER, $sigX, $sigY);

		$tmpPath = $this->tempManager->getTemporaryFile('_merged.png');
		if (!$tmpPath) {
			throw new \Exception('Temporary file not accessible');
		}
		$canvas->writeImage($tmpPath);

		$canvas->clear();
		$background->clear();
		$signature->clear();

		return $tmpPath;
	}

	private function parseSignatureText(): array {
		if (!$this->parsedSignatureText) {
			$params = $this->getSignatureParams();
			$params['ServerSignatureDate'] = '${timestamp}';
			$this->parsedSignatureText = $this->signatureTextService->parse(context: $params);
		}
		return $this->parsedSignatureText;
	}

	public function getSignatureText(): string {
		$renderMode = $this->signatureTextService->getRenderMode();
		if ($renderMode !== 'GRAPHIC_ONLY') {
			$data = $this->parseSignatureText();
			$signatureText = '"' . str_replace(
				['"', '$'],
				['\"', '\$'],
				$data['parsed']
			) . '"';
		} else {
			$signatureText = '""';
		}

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
			$hashAlgorithm = array_filter($rows, fn ($r) => str_contains((string)$r, 'The chosen hash algorithm'));
			if (!empty($hashAlgorithm)) {
				$hashAlgorithm = current($hashAlgorithm);
				$hashAlgorithm = trim((string)$hashAlgorithm, 'INFO ');
				$hashAlgorithm = str_replace('\"', '"', $hashAlgorithm);
				$hashAlgorithm = preg_replace('/\.( )/', ".\n", $hashAlgorithm);
				throw new LibresignException($hashAlgorithm);
			}
			$this->logger->error('Error at JSignPdf side. LibreSign can not do nothing. Follow the error message: ' . $th->getMessage());
			throw new \Exception($th->getMessage());
		}
	}
}
