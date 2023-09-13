<?php

namespace OCA\Libresign\Handler;

use BaconQrCode\Encoder\Encoder;
use Endroid\QrCode\Bacon\ErrorCorrectionLevelConverter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\Matrix\Matrix;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use OC\SystemConfig;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngineHandler;
use OCA\Libresign\Service\FolderService;
use OCP\Files\File;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use TCPDI;
use TypeError;

class Pkcs12Handler extends SignEngineHandler {
	/** @var string */
	private $pfxFilename = 'signature.pfx';
	/** @var QrCode */
	private $qrCode;
	private const MIN_QRCODE_SIZE = 100;

	public function __construct(
		private FolderService $folderService,
		private IConfig $config,
		private IURLGenerator $urlGenerator,
		private SystemConfig $systemConfig,
		private CertificateEngineHandler $certificateEngineHandler,
		private IL10N $l10n,
		private JSignPdfHandler $jSignPdfHandler,
	) {
	}

	public function savePfx(string $uid, string $content): string {
		$this->folderService->setUserId($uid);
		$folder = $this->folderService->getFolder();
		if ($folder->nodeExists($this->pfxFilename)) {
			$file = $folder->get($this->pfxFilename);
			if (!$file instanceof File) {
				throw new LibresignException("path {$this->pfxFilename} already exists and is not a file!", 400);
			}
			$file->putContent($content);
			return $content;
		}

		$file = $folder->newFile($this->pfxFilename);
		$file->putContent($content);
		return $content;
	}

	/**
	 * Get content of pfx file
	 */
	public function getPfx($uid): string {
		$this->folderService->setUserId($uid);
		$folder = $this->folderService->getFolder();
		if (!$folder->nodeExists($this->pfxFilename)) {
			throw new LibresignException($this->l10n->t('Password to sign not defined. Create a password to sign.'), 400);
		}
		/** @var \OCP\Files\File */
		$node = $folder->get($this->pfxFilename);
		if (!$node->getContent()) {
			throw new LibresignException($this->l10n->t('Password to sign not defined. Create a password to sign.'), 400);
		}
		return $node->getContent();
	}

	private function getHandler(): SignEngineHandler {
		$sign_engine = $this->config->getAppValue(Application::APP_ID, 'sign_engine', 'JSignPdf');
		$property = lcfirst($sign_engine) . 'Handler';
		if (!property_exists($this, $property)) {
			throw new LibresignException($this->l10n->t('Invalid Sign engine.'), 400);
		}
		$classHandler = 'OCA\\Libresign\\Handler\\' . $property;
		if (!$this->$property instanceof $classHandler) {
			$this->$property = \OC::$server->get($classHandler);
		}
		return $this->$property;
	}

	public function sign(): File {
		$signedContent = $this->getHandler()
			->setCertificate($this->getCertificate())
			->setInputFile($this->getInputFile())
			->setPassword($this->getPassword())
			->setVisibleElements($this->getvisibleElements())
			->sign();
		$this->getInputFile()->putContent($signedContent);
		return $this->getInputFile();
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 */
	public function getFooter(File $file, string $uuid): string {
		$add_footer = $this->config->getAppValue(Application::APP_ID, 'add_footer', 1);
		if (!$add_footer) {
			return '';
		}
		$validation_site = $this->config->getAppValue(Application::APP_ID, 'validation_site');
		if ($validation_site) {
			$validation_site = rtrim($validation_site, '/').'/'.$uuid;
		} else {
			$validation_site = $this->urlGenerator->linkToRouteAbsolute('libresign.page.validationFileWithShortUrl', ['uuid' => $uuid]);
		}

		$pdf = new TCPDILibresign();
		$pageCount = $pdf->setSourceData($file->getContent());

		$dimensions = null;
		for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
			$pdf->importPage($pageNo);

			// Define dimensions of page
			$tpl = $pdf->tpls[$pageNo];
			$dimensions['or'] = $tpl['w'] > $tpl['h'] ? 'L' : 'P';
			$pdf->setPageOrientation($dimensions['or']);
			$dimensions = $pdf->getPageDimensions($pageNo - 1);
			$dimensions['w'] = $tpl['w'];
			$dimensions['h'] = $tpl['h'];
			$dimensions['wk'] = $tpl['h'];
			$dimensions['hk'] = $tpl['h'];
			foreach (['MediaBox', 'CropBox', 'BleedBox', 'TrimBox', 'ArtBox'] as $box) {
				if (!isset($dimensions[$box])) {
					continue;
				}
				$dimensions[$box]['urx'] = $tpl['h'];
				$dimensions[$box]['ury'] = $tpl['w'];
			}
			$pdf->AddPage($dimensions['or'], $dimensions);

			$pdf->SetFont('Helvetica');
			$pdf->SetFontSize(8);
			$pdf->SetAutoPageBreak(false);

			$x = 10;
			if ($this->config->getAppValue(Application::APP_ID, 'write_qrcode_on_footer', 1)) {
				$this->writeQrCode($validation_site, $pdf);
				$x += $this->qrCode->getSize();
			}

			$pdf->SetXY($x, -35);
			$pdf->Write(
				10,
				iconv(
					'UTF-8',
					'windows-1252',
					$this->config->getAppValue(Application::APP_ID, 'footer_first_row', $this->l10n->t('Digital signed by LibreSign.'))
				),
				$this->config->getAppValue(Application::APP_ID, 'footer_link_to_site', 'https://libresign.coop')
			);

			$footerSecondRow = $this->config->getAppValue(Application::APP_ID, 'footer_second_row', 'Validate in %s.');
			if ($footerSecondRow === 'Validate in %s.') {
				$footerSecondRow = $this->l10n->t('Validate in %s.', $validation_site);
			}
			$pdf->SetXY($x, -25);
			$pdf->Write(
				10,
				iconv('UTF-8', 'windows-1252', $footerSecondRow),
				$validation_site
			);
		}

		return $pdf->Output('', 'S');
	}

	private function writeQrCode(string $text, TCPDI $fpdf): void {
		$this->qrCode = QrCode::create($text)
			->setEncoding(new Encoding('UTF-8'))
			->setErrorCorrectionLevel(new ErrorCorrectionLevelLow())
			->setMargin(5)
			->setRoundBlockSizeMode(new RoundBlockSizeModeMargin())
			->setForegroundColor(new Color(0, 0, 0))
			->setBackgroundColor(new Color(255, 255, 255));

		$blockValues = $this->getQrCodeBlocks();
		$this->setQrCodeSize($blockValues);
		$matrix = new Matrix($blockValues, $this->qrCode->getSize(), $this->qrCode->getMargin(), $this->qrCode->getRoundBlockSizeMode());

		$backgroundColor = $this->qrCode->getBackgroundColor();
		$foregroundColor = $this->qrCode->getForegroundColor();

		$fpdf->SetFillColor($backgroundColor->getRed(), $backgroundColor->getGreen(), $backgroundColor->getBlue());
		$backgroundBottonPosition = $fpdf->GetPageHeight() - $matrix->getOuterSize();
		$fpdf->Rect(0, $backgroundBottonPosition, $matrix->getOuterSize(), $matrix->getOuterSize(), 'F');
		$fpdf->SetFillColor($foregroundColor->getRed(), $foregroundColor->getGreen(), $foregroundColor->getBlue());

		$qrCodeBottonPosition = $fpdf->GetPageHeight() - $matrix->getOuterSize() + $matrix->getMarginLeft();
		for ($rowIndex = 0; $rowIndex < $matrix->getBlockCount(); ++$rowIndex) {
			for ($columnIndex = 0; $columnIndex < $matrix->getBlockCount(); ++$columnIndex) {
				if (1 === $matrix->getBlockValue($rowIndex, $columnIndex)) {
					$fpdf->Rect(
						$matrix->getMarginLeft() + ($columnIndex * $matrix->getBlockSize()),
						$qrCodeBottonPosition + ($rowIndex * $matrix->getBlockSize()),
						$matrix->getBlockSize(),
						$matrix->getBlockSize(),
						'F'
					);
				}
			}
		}
	}

	private function setQrCodeSize(array $blockValues): void {
		$this->qrCode->setSize(self::MIN_QRCODE_SIZE);
		$blockSize = $this->qrCode->getSize() / count($blockValues);
		if ($blockSize < 1) {
			$this->qrCode->setSize(count($blockValues));
		}
	}

	/**
	 * @return int[][]
	 *
	 * @psalm-return array<0|positive-int, array<0|positive-int, int>>
	 */
	private function getQrCodeBlocks(): array {
		$baconErrorCorrectionLevel = ErrorCorrectionLevelConverter::convertToBaconErrorCorrectionLevel($this->qrCode->getErrorCorrectionLevel());
		$baconMatrix = Encoder::encode($this->qrCode->getData(), $baconErrorCorrectionLevel, strval($this->qrCode->getEncoding()))->getMatrix();

		$blockValues = [];
		$columnCount = $baconMatrix->getWidth();
		$rowCount = $baconMatrix->getHeight();
		for ($rowIndex = 0; $rowIndex < $rowCount; ++$rowIndex) {
			$blockValues[$rowIndex] = [];
			for ($columnIndex = 0; $columnIndex < $columnCount; ++$columnIndex) {
				$blockValues[$rowIndex][$columnIndex] = $baconMatrix->get($columnIndex, $rowIndex);
			}
		}
		return $blockValues;
	}

	public function isHandlerOk(): bool {
		return $this->certificateEngineHandler->getEngine()->isSetupOk();
	}

	/**
	 * Generate certificate
	 *
	 * @param array $user Example: ['identify' => '', 'name' => '']
	 * @param string $signPassword Password of signature
	 * @param string $friendlyName Friendly name
	 * @param bool $isTempFile
	 */
	public function generateCertificate(array $user, string $signPassword, string $friendlyName, bool $isTempFile = false): string {
		$content = $this->certificateEngineHandler->getEngine()
			->setHosts([$user['identify']])
			->setCommonName($user['name'])
			->setFriendlyName($friendlyName)
			->setPassword($signPassword)
			->generateCertificate();
		if (!$content) {
			throw new TypeError();
		}
		if ($isTempFile) {
			return $content;
		}
		return $this->savePfx($user['identify'], $content);
	}
}
