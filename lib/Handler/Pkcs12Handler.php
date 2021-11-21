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
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FolderService;
use OCP\Files\File;
use OCP\IConfig;
use OCP\IL10N;
use TCPDI;

class Pkcs12Handler extends SignEngineHandler {

	/** @var string */
	private $pfxFilename = 'signature.pfx';
	/** @var FolderService */
	private $folderService;
	/** @var JSignPdfHandler|null */
	private $JSignPdfHandler;
	/** @var IConfig */
	private $config;
	/** @var CfsslHandler */
	private $cfsslHandler;
	/** @var IL10N */
	private $l10n;
	/** @var QrCode */
	private $qrCode;
	private const MIN_QRCODE_SIZE = 20;

	public function __construct(
		FolderService $folderService,
		IConfig $config,
		CfsslHandler $cfsslHandler,
		IL10N $l10n
	) {
		$this->folderService = $folderService;
		$this->config = $config;
		$this->cfsslHandler = $cfsslHandler;
		$this->l10n = $l10n;
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 * @param string $uid
	 * @param string $content
	 * @return File
	 */
	public function savePfx(string $uid, string $content, bool $isTempFile = false): File {
		if ($isTempFile) {
			$this->pfxFilename = 'temp.pfx';
		}
		$this->folderService->setUserId($uid);
		$folder = $this->folderService->getFolder();
		if ($folder->nodeExists($this->pfxFilename)) {
			$file = $folder->get($this->pfxFilename);
			if (!$file instanceof File) {
				throw new LibresignException("path {$this->pfxFilename} already exists and is not a file!", 400);
			}
			$file->putContent($content);
			return $file;
		}

		$file = $folder->newFile($this->pfxFilename);
		$file->putContent($content);
		return $file;
	}

	/**
	 * Get pfx file
	 *
	 * @psalm-suppress MixedReturnStatement
	 * @param string $uid user id
	 * @return \OCP\Files\Node
	 */
	public function getPfx($uid): \OCP\Files\Node {
		$this->folderService->setUserId($uid);
		$folder = $this->folderService->getFolder();
		if (!$folder->nodeExists($this->pfxFilename)) {
			throw new LibresignException($this->l10n->t('Password to sign not defined. Create a password to sign.'), 400);
		}
		return $folder->get($this->pfxFilename);
	}

	private function getHandler(): SignEngineHandler {
		$sign_engine = $this->config->getAppValue(Application::APP_ID, 'sign_engine', 'JSignPdf');
		if (!property_exists($this, $sign_engine . 'Handler')) {
			throw new LibresignException($this->l10n->t('Invalid Sign engine.'), 400);
		}
		$property = $sign_engine . 'Handler';
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
	 * @param File $file
	 * @param string $uuid
	 * @return string
	 */
	public function writeFooter(File $file, string $uuid): string {
		$add_footer = $this->config->getAppValue(Application::APP_ID, 'add_footer', 1);
		if (!$add_footer) {
			return '';
		}
		$validation_site = $this->config->getAppValue(Application::APP_ID, 'validation_site');
		if (!$validation_site) {
			return '';
		}
		$validation_site = rtrim($validation_site, '/').'/'.$uuid;

		$pdf = new TCPDILibresign();
		$pageCount = $pdf->setNextcloudSourceFile($file);

		for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
			$templateId = $pdf->importPage($pageNo);
			$pdf->AddPage();
			$pdf->useTemplate($templateId);

			$pdf->SetFont('Helvetica');
			$pdf->SetFontSize(8);
			$pdf->SetAutoPageBreak(false);

			$x = 10;
			if ($this->config->getAppValue(Application::APP_ID, 'write_qrcode_on_footer', 1)) {
				$this->writeQrCode($validation_site, $pdf);
				$x += $this->qrCode->getSize();
			}
			$pdf->SetXY($x, -15);
			$pdf->Write(
				8,
				iconv('UTF-8', 'windows-1252', $this->l10n->t('Digital signed by LibreSign.')),
				'https://libresign.coop'
			);
			$pdf->SetXY($x, -10);
			$pdf->Write(
				8,
				iconv('UTF-8', 'windows-1252', $this->l10n->t('Validate in %s.', $validation_site)),
				$validation_site
			);
		}

		return $pdf->Output(null, 'S');
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

	private function getCertificateHandler(): CfsslHandler {
		if (!$this->cfsslHandler->getCommonName()) {
			$this->cfsslHandler->setCommonName($this->config->getAppValue(Application::APP_ID, 'commonName'));
		}
		if (!$this->cfsslHandler->getCountry()) {
			$this->cfsslHandler->setCountry($this->config->getAppValue(Application::APP_ID, 'country'));
		}
		if (!$this->cfsslHandler->getOrganization()) {
			$this->cfsslHandler->setOrganization($this->config->getAppValue(Application::APP_ID, 'organization'));
		}
		if (!$this->cfsslHandler->getOrganizationUnit()) {
			$this->cfsslHandler->setOrganizationUnit($this->config->getAppValue(Application::APP_ID, 'organizationUnit'));
		}
		if (!$this->cfsslHandler->getCfsslUri()) {
			$this->cfsslHandler->setCfsslUri($this->config->getAppValue(Application::APP_ID, 'cfsslUri'));
		}
		return $this->cfsslHandler;
	}

	/**
	 * Generate certificate
	 *
	 * @param string $email Email
	 * @param string $signPassword Password of signature
	 * @param string $uid User id
	 * @return File
	 */
	public function generateCertificate(string $email, string $signPassword, string $uid, bool $isTempFile = false): File {
		$content = $this->getCertificateHandler()
			->setHosts([$email])
			->setFriendlyName($uid)
			->setPassword($signPassword)
			->generateCertificate();
		if (!$content) {
			throw new LibresignException('Failure on generate certificate', 1);
		}
		return $this->savePfx($uid, $content, $isTempFile);
	}
}
