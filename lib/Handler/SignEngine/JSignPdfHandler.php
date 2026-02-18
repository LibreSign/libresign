<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\SignEngine;

use Imagick;
use ImagickPixel;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Helper\JavaHelper;
use OCA\Libresign\Service\DocMdp\ConfigService as DocMdpConfigService;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Vendor\Jeidison\JSignPDF\JSignPDF;
use OCA\Libresign\Vendor\Jeidison\JSignPDF\Sign\JSignParam;
use OCP\Files\File;
use OCP\IAppConfig;
use OCP\ITempManager;
use Psr\Log\LoggerInterface;

class JSignPdfHandler extends Pkcs12Handler {
	private const MIN_PDF_VERSION = 1.2;
	private const TARGET_OLD_PDF_VERSION = '1.3';
	private const MIN_PDF_VERSION_SHA256 = 1.6;
	private const TARGET_PDF_VERSION_SHA256 = '1.6';
	private const MIN_PDF_VERSION_SHA1_REJECT = 1.7;
	private const SIGNATURE_DEFAULT_FONT_SIZE = 10.0;
	private const PAGE_FIRST = 1;
	private const SCALE_FACTOR_MIN = 5;

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
		protected JavaHelper $javaHelper,
		private DocMdpConfigService $docMdpConfigService,
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
			$javaPath = $this->javaHelper->getJavaPath();
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
				->setJavaDownloadUrl('')
				->setJSignPdfDownloadUrl('')
				->setjSignPdfJarPath($jSignPdfJarPath);
			if (!empty($javaPath)) {
				if (!file_exists($javaPath)) {
					throw new \Exception('Invalid Java binary. Run occ libresign:install --java');
				}
				$this->jSignParam->setJavaPath(
					$this->getEnvironments()
					. $javaPath
					. ' -Duser.home=' . escapeshellarg($this->getHome()) . ' '
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
		$configuredHome = $this->getConfiguredHome();
		if ($configuredHome !== null) {
			return $configuredHome;
		}

		$tempFolder = $this->createJSignPdfTempFolder();
		$this->initializeJSignPdfConfigurationFiles($tempFolder);
		return $tempFolder;
	}

	private function getConfiguredHome(): ?string {
		$jSignPdfHome = $this->appConfig->getValueString(Application::APP_ID, 'jsignpdf_home', '');
		if ($jSignPdfHome && is_dir($jSignPdfHome)) {
			return $jSignPdfHome;
		}
		return null;
	}

	private function createJSignPdfTempFolder(): string {
		$jsignpdfTempFolder = $this->tempManager->getTemporaryFolder('jsignpdf');
		if (!$jsignpdfTempFolder) {
			throw new \Exception('Temporary file not accessible');
		}
		mkdir(
			directory: $jsignpdfTempFolder . '/conf',
			recursive: true
		);
		return $jsignpdfTempFolder;
	}

	private function initializeJSignPdfConfigurationFiles(string $folder): void {
		$this->createEmptyFile($folder . '/conf/conf.properties');
		$this->createEmptyFile($folder . '/.JSignPdf');
	}

	private function createEmptyFile(string $path): void {
		$file = fopen($path, 'w');
		fclose($file);
	}

	private function getHashAlgorithm(string $pdfContent): string {
		$configuredAlgorithm = $this->appConfig->getValueString(Application::APP_ID, 'signature_hash_algorithm', 'SHA256');
		/**
		 * Need to respect the follow code:
		 * https://github.com/intoolswetrust/jsignpdf/blob/JSignPdf_2_2_2/jsignpdf/src/main/java/net/sf/jsignpdf/types/HashAlgorithm.java#L46-L47
		 */
		$pdfVersion = $this->extractPdfVersion($pdfContent);

		if ($pdfVersion === null) {
			return $this->validateHashAlgorithm($configuredAlgorithm);
		}

		return $this->getHashAlgorithmForPdfVersion($pdfVersion, $configuredAlgorithm);
	}

	private function extractPdfVersion(string $content): ?float {
		if (!preg_match('/^%PDF-(?<version>\d+(\.\d+)?)/', $content, $match)) {
			return null;
		}
		return (float)$match['version'];
	}

	private function getHashAlgorithmForPdfVersion(float $pdfVersion, string $configuredAlgorithm): string {
		if ($pdfVersion < 1.6) {
			return 'SHA1';
		}
		if ($pdfVersion < self::MIN_PDF_VERSION_SHA1_REJECT) {
			return 'SHA256';
		}
		if ($pdfVersion >= self::MIN_PDF_VERSION_SHA1_REJECT && $configuredAlgorithm === 'SHA1') {
			return 'SHA256';
		}
		return $this->validateHashAlgorithm($configuredAlgorithm);
	}

	private function validateHashAlgorithm(string $algorithm): string {
		$supportedAlgorithms = ['SHA1', 'SHA256', 'SHA384', 'SHA512', 'RIPEMD160'];
		return in_array($algorithm, $supportedAlgorithms) ? $algorithm : 'SHA256';
	}

	/**
	 * Normalizes very old PDFs (1.0/1.1) to 1.3.
	 * Rationale: JSignPDF enum PdfVersion only defines 1.2+; for 1.0/1.1,
	 * PdfVersion.fromCharVersion(...) returns null and SignerLogic.signFile() NPEs.
	 * See JSignPDF 2.3.0 sources: types/PdfVersion.java and SignerLogic.signFile().
	 */
	private function normalizePdfVersion(string $content): string {
		$version = $this->extractPdfVersion($content);
		if ($version === null) {
			return $content;
		}

		// Convert very old PDFs (< 1.2) to 1.3 to avoid JSignPDF NullPointerException
		if ($this->isVeryOldPdfVersion($version)) {
			return $this->replacePdfVersion($content, self::TARGET_OLD_PDF_VERSION);
		}

		// Convert PDFs < 1.6 to 1.6 if using SHA-256 (the default hash algorithm)
		// This prevents "The chosen hash algorithm (SHA-256) requires a newer PDF version" error
		if ($this->requiresPdfVersionUpgradeForSha256($version)) {
			return $this->replacePdfVersion($content, self::TARGET_PDF_VERSION_SHA256);
		}

		return $content;
	}

	private function isVeryOldPdfVersion(float $version): bool {
		return $version > 0 && $version < self::MIN_PDF_VERSION;
	}

	private function requiresPdfVersionUpgradeForSha256(float $version): bool {
		if ($version >= self::MIN_PDF_VERSION_SHA256) {
			return false;
		}
		$hashAlgorithm = $this->appConfig->getValueString(Application::APP_ID, 'signature_hash_algorithm', 'SHA256');
		return $hashAlgorithm === 'SHA256';
	}

	private function replacePdfVersion(string $content, string $newVersion): string {
		return (string)preg_replace('/^%PDF-\d+(\.\d+)?/', '%PDF-' . $newVersion, $content, 1);
	}

	private function getCertificationLevel(): ?string {
		if (!$this->docMdpConfigService->isEnabled()) {
			return null;
		}

		return $this->docMdpConfigService->getLevel()->name;
	}

	#[\Override]
	public function sign(): File {
		$this->beforeSign();

		$signedContent = $this->getSignedContent();
		$this->getInputFile()->putContent($signedContent);
		return $this->getInputFile();
	}

	#[\Override]
	public function getSignedContent(): string {
		$normalizedPdf = $this->normalizePdfVersion($this->getInputFile()->getContent());
		$hashAlgorithm = $this->getHashAlgorithm($normalizedPdf);
		$param = $this->getJSignParam();

		$tsaParams = $this->listParamsToString($this->getTsaParameters());

		$visibleElements = $this->getVisibleElements();
		$certParams = '';
		$certificationLevel = $this->getCertificationLevel();
		if ($certificationLevel !== null && !$visibleElements && !$this->hasExistingSignatures($normalizedPdf)) {
			$certParams = ' -cl ' . $certificationLevel;
		}

		$param->setJSignParameters(
			$param->getJSignParameters()
			. $certParams
			. $tsaParams
		);
		$param->setCertificate($this->getCertificate())
			->setPdf($normalizedPdf)
			->setPassword($this->getPassword());

		$signed = $this->signUsingVisibleElements($normalizedPdf, $hashAlgorithm);
		if ($signed) {
			return $signed;
		}

		$param->setJSignParameters(
			$param->getJSignParameters()
			. $this->listParamsToString([
				'--hash-algorithm' => $hashAlgorithm,
			])
		);
		$jSignPdf = $this->getJSignPdf();
		$jSignPdf->setParam($param);
		return $this->signWrapper($jSignPdf);
	}

	private function signUsingVisibleElements(string $normalizedPdf, string $hashAlgorithm): string {
		$visibleElements = $this->getVisibleElements();
		if ($visibleElements) {
			$jSignPdf = $this->getJSignPdf();

			$renderMode = $this->signatureTextService->getRenderMode();

			$params = [
				'--l2-text' => $this->getSignatureText(),
				'-V' => null,
			];

			// When l2-text is empty, add hash-algorithm at the beginning
			if ($params['--l2-text'] === '""') {
				$params = [
					'--hash-algorithm' => $hashAlgorithm,
					'--l2-text' => $params['--l2-text'],
					'-V' => null,
				];
			}

			$fontSize = $this->parseSignatureText()['templateFontSize'];
			if ($fontSize === self::SIGNATURE_DEFAULT_FONT_SIZE || !$fontSize || $params['--l2-text'] === '""') {
				$fontSize = 0;
			}

			$backgroundType = $this->signatureBackgroundService->getSignatureBackgroundType();
			if ($backgroundType !== 'deleted') {
				$backgroundPath = $this->signatureBackgroundService->getImagePath();
			} else {
				$backgroundPath = '';
			}

			$certificationLevel = $this->getCertificationLevel();
			$applyCertification = $certificationLevel !== null && !$this->hasExistingSignatures($normalizedPdf);
			$certParams = $applyCertification ? ' -cl ' . $certificationLevel : '';
			$elementIndex = 0;

			$param = $this->getJSignParam();
			$originalParam = clone $param;

			foreach ($visibleElements as $element) {
				$elementIndex++;
				$params['-pg'] = $element->getFileElement()->getPage();
				if ($params['-pg'] <= self::PAGE_FIRST) {
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

				$backgroundPathForElement = $backgroundPath
					? $this->prepareBackgroundForPdf($backgroundPath, $this->normalizeScaleFactor($scaleFactor))
					: '';

				$signatureImagePath = $element->getTempFile();
				if ($backgroundType === 'deleted') {
					if ($renderMode === SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION) {
						$params['--render-mode'] = SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION;
						$params['--img-path'] = $this->createTextImage(
							width: ($params['-urx'] - $params['-llx']),
							height: ($params['-ury'] - $params['-lly']),
							fontSize: $this->signatureTextService->getSignatureFontSize() * $scaleFactor,
							scaleFactor: $this->normalizeScaleFactor($scaleFactor),
						);
					} elseif ($signatureImagePath) {
						$params['--bg-path'] = $signatureImagePath;
					}
				} elseif ($params['--l2-text'] === '""') {
					if ($backgroundPathForElement) {
						$params['--bg-path'] = $this->mergeBackgroundWithSignature(
							$backgroundPathForElement,
							$signatureImagePath,
							$this->normalizeScaleFactor($scaleFactor),
						);
					} else {
						$params['--bg-path'] = $signatureImagePath;
					}
				} else {
					if ($renderMode === SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION) {
						$params['--render-mode'] = SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION;
						$params['--bg-path'] = $backgroundPathForElement;
						$params['--img-path'] = $signatureImagePath;
					} elseif ($renderMode === SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION) {
						$params['--render-mode'] = SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION;
						$params['--bg-path'] = $backgroundPathForElement;
						$params['--img-path'] = $this->createTextImage(
							width: (int)(($params['-urx'] - $params['-llx']) / 2),
							height: $params['-ury'] - $params['-lly'],
							fontSize: $this->signatureTextService->getSignatureFontSize() * $scaleFactor,
							scaleFactor: $this->normalizeScaleFactor($scaleFactor),
						);

					} else {
						$params['--bg-path'] = $backgroundPathForElement;
					}
				}

				// Only add hash-algorithm at the end if l2-text is not empty
				if ($params['--l2-text'] !== '""') {
					$params['--hash-algorithm'] = $hashAlgorithm;
				}

				$elementCertParams = ($applyCertification && $elementIndex === 1) ? $certParams : '';
				$param->setJSignParameters(
					$originalParam->getJSignParameters()
					. $elementCertParams
					. $this->listParamsToString($params)
				);
				$param->setPdf($normalizedPdf);
				$jSignPdf->setParam($param);
				$signed = $this->signWrapper($jSignPdf);
				$normalizedPdf = $signed;
			}
			return $signed;
		}
		return '';
	}

	private function hasExistingSignatures(string $pdfContent): bool {
		return (bool)preg_match('/\/ByteRange\s*\[|\/Type\s*\/Sig\b|\/DocMDP\b|\/Perms\b/', $pdfContent);
	}

	private function getScaleFactor(float $width): float {
		$systemWidth = $this->signatureTextService->getFullSignatureWidth();
		if (!$systemWidth) {
			return 1;
		}
		return $width / $systemWidth;
	}

	private function normalizeScaleFactor(float $scaleFactor): float {
		return max($scaleFactor, self::SCALE_FACTOR_MIN);
	}


	#[\Override]
	public function readCertificate(): array {
		$result = $this->certificateEngineFactory
			->getEngine()
			->readCertificate(
				$this->getCertificate(),
				$this->getPassword()
			);

		if (!is_array($result)) {
			throw new \RuntimeException('Failed to read certificate data');
		}

		return $result;
	}

	private function createTextImage(int $width, int $height, float $fontSize, float $scaleFactor): string {
		$params = $this->getSignatureParams();
		if (!empty($params['SignerCommonName'])) {
			$commonName = $params['SignerCommonName'];
		} else {
			$certificateData = $this->readCertificate();
			$commonName = $certificateData['subject']['CN'] ?? throw new \RuntimeException('Certificate must have a Common Name (CN) in subject field');
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
		if (!extension_loaded('imagick')) {
			throw new \Exception('Extension imagick is not loaded.');
		}
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
			(int)$canvasWidth,
			(int)$canvasHeight,
			Imagick::FILTER_LANCZOS,
			1,
			true
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

	private function prepareBackgroundForPdf(string $backgroundPath, float $scaleFactor): string {
		if (!extension_loaded('imagick')) {
			throw new \Exception('Extension imagick is not loaded.');
		}
		$baseWidth = $this->signatureTextService->getFullSignatureWidth();
		$baseHeight = $this->signatureTextService->getFullSignatureHeight();

		$canvasWidth = (int)round($baseWidth * $scaleFactor);
		$canvasHeight = (int)round($baseHeight * $scaleFactor);

		$background = new Imagick($backgroundPath);
		$background->setImageFormat('png');
		$background->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
		$background->resizeImage(
			$canvasWidth,
			$canvasHeight,
			Imagick::FILTER_LANCZOS,
			1,
			true
		);

		$canvas = new Imagick();
		$canvas->newImage($canvasWidth, $canvasHeight, new ImagickPixel('transparent'));
		$canvas->setImageFormat('png32');
		$canvas->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);

		$bgX = (int)(($canvasWidth - $background->getImageWidth()) / 2);
		$bgY = (int)(($canvasHeight - $background->getImageHeight()) / 2);
		$canvas->compositeImage($background, Imagick::COMPOSITE_OVER, $bgX, $bgY);

		$tmpPath = $this->tempManager->getTemporaryFile('_background.png');
		if (!$tmpPath) {
			throw new \Exception('Temporary file not accessible');
		}
		$canvas->writeImage($tmpPath);

		$canvas->clear();
		$background->clear();

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

	private function getTsaParameters(): array {
		$tsaUrl = $this->appConfig->getValueString(Application::APP_ID, 'tsa_url', '');
		if (empty($tsaUrl)) {
			return [];
		}

		$params = [
			'--tsa-server-url' => $tsaUrl,
			'--tsa-policy-oid' => $this->appConfig->getValueString(Application::APP_ID, 'tsa_policy_oid', ''),
		];

		if (!$params['--tsa-policy-oid']) {
			unset($params['--tsa-policy-oid']);
		}

		$tsaAuthType = $this->appConfig->getValueString(Application::APP_ID, 'tsa_auth_type', 'none');
		if ($tsaAuthType === 'basic') {
			$tsaUsername = $this->appConfig->getValueString(Application::APP_ID, 'tsa_username', '');
			$tsaPassword = $this->appConfig->getValueString(Application::APP_ID, 'tsa_password', '');

			if (!empty($tsaUsername) && !empty($tsaPassword)) {
				$params['--tsa-authentication'] = 'PASSWORD';
				$params['--tsa-user'] = $tsaUsername;
				$params['--tsa-password'] = $tsaPassword;
			}
		}

		return $params;
	}

	private function signWrapper(JSignPDF $jSignPDF): string {
		try {
			return $jSignPDF->sign();
		} catch (\Throwable $th) {
			$errorMessage = $th->getMessage();

			$this->checkTsaError($errorMessage);
			$this->checkHashAlgorithmError($errorMessage);

			$this->logger->error('Error at JSignPdf side. LibreSign can not do nothing. Follow the error message: ' . $errorMessage);
			throw new \Exception($errorMessage);
		}
	}

	private function checkTsaError(string $errorMessage): void {
		$tsaErrors = ['TSAClientBouncyCastle', 'UnknownHostException', 'Invalid TSA'];
		$isTsaError = false;
		foreach ($tsaErrors as $error) {
			if (str_contains($errorMessage, $error)) {
				$isTsaError = true;
				break;
			}
		}

		if ($isTsaError) {
			if (str_contains($errorMessage, 'Invalid TSA') && preg_match("/Invalid TSA '([^']+)'/", $errorMessage, $matches)) {
				$friendlyMessage = 'Timestamp Authority (TSA) service is unavailable or misconfigured: ' . $matches[1];
			} else {
				$friendlyMessage = 'Timestamp Authority (TSA) service error.' . "\n"
					. 'Please check the TSA configuration.';
			}
			throw new LibresignException($friendlyMessage);
		}
	}

	private function checkHashAlgorithmError(string $errorMessage): void {
		$rows = str_getcsv($errorMessage);
		$hashAlgorithm = array_filter($rows, fn ($r) => str_contains((string)$r, 'The chosen hash algorithm'));

		if (!empty($hashAlgorithm)) {
			$hashAlgorithm = current($hashAlgorithm);
			$hashAlgorithm = trim((string)$hashAlgorithm, 'INFO ');
			$hashAlgorithm = str_replace('\"', '"', $hashAlgorithm);
			$hashAlgorithm = preg_replace('/\.( )/', ".\n", $hashAlgorithm);
			throw new LibresignException($hashAlgorithm);
		}
	}
}
