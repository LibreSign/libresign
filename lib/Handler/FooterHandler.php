<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\PdfParserService;
use OCA\Libresign\Vendor\BaconQrCode\Encoder\Encoder;
use OCA\Libresign\Vendor\Endroid\QrCode\Bacon\ErrorCorrectionLevelConverter;
use OCA\Libresign\Vendor\Endroid\QrCode\Color\Color;
use OCA\Libresign\Vendor\Endroid\QrCode\Encoding\Encoding;
use OCA\Libresign\Vendor\Endroid\QrCode\ErrorCorrectionLevel;
use OCA\Libresign\Vendor\Endroid\QrCode\QrCode;
use OCA\Libresign\Vendor\Endroid\QrCode\RoundBlockSizeMode;
use OCA\Libresign\Vendor\Endroid\QrCode\Writer\PngWriter;
use OCA\Libresign\Vendor\Mpdf\Mpdf;
use OCA\Libresign\Vendor\Twig\Environment;
use OCA\Libresign\Vendor\Twig\Error\SyntaxError;
use OCA\Libresign\Vendor\Twig\Loader\FilesystemLoader;
use OCP\Files\File;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;

class FooterHandler {
	private QrCode $qrCode;
	private const MIN_QRCODE_SIZE = 100;
	private const POINT_TO_MILIMETER = 0.3527777778;

	private TemplateVariables $templateVars;

	public function __construct(
		private IAppConfig $appConfig,
		private PdfParserService $pdfParserService,
		private IURLGenerator $urlGenerator,
		private IL10N $l10n,
		private IFactory $l10nFactory,
		private ITempManager $tempManager,
	) {
		$this->templateVars = new TemplateVariables();
	}

	public function getFooter(array $dimensions): string {
		$add_footer = (bool)$this->appConfig->getValueBool(Application::APP_ID, 'add_footer', true);
		if (!$add_footer) {
			return '';
		}

		$htmlFooter = $this->getRenderedHtmlFooter();
		foreach ($dimensions as $dimension) {
			if (!isset($pdf)) {
				$pdf = new Mpdf([
					'tempDir' => $this->tempManager->getTempBaseDir(),
					'orientation' => 'P',
					'margin_left' => 0,
					'margin_right' => 0,
					'margin_top' => 0,
					'margin_bottom' => 0,
					'margin_header' => 0,
					'margin_footer' => 0,
					'format' => [
						$dimension['w'] * self::POINT_TO_MILIMETER,
						$dimension['h'] * self::POINT_TO_MILIMETER,
					],
				]);
				$pdf->SetDirectionality($this->templateVars->getDirection());
			}
			$pdf->AddPage(
				orientation: 'P',
				newformat: [
					$dimension['w'] * self::POINT_TO_MILIMETER,
					$dimension['h'] * self::POINT_TO_MILIMETER,
				],
			);

			$pdf->SetHTMLFooter($htmlFooter);
		}

		return $pdf->Output('', 'S');
	}

	public function getMetadata(File $file, FileEntity $fileEntity): array {
		$metadata = $fileEntity->getMetadata();
		if (!is_array($metadata) || !isset($metadata['d'])) {
			$metadata = $this->pdfParserService
				->setFile($file)
				->getPageDimensions();
		}
		return $metadata;
	}

	private function getRenderedHtmlFooter(): string {
		try {
			$twigEnvironment = new Environment(
				new FilesystemLoader(),
			);
			return $twigEnvironment
				->createTemplate($this->getTemplate())
				->render($this->prepareTemplateVars());
		} catch (SyntaxError $e) {
			throw new LibresignException($e->getMessage());
		}
	}

	public function setTemplateVar(string $name, mixed $value): self {
		$this->templateVars->merge([$name => $value]);
		return $this;
	}

	private function prepareTemplateVars(): array {
		if (!$this->templateVars->getSignedBy()) {
			$this->templateVars->setSignedBy(
				$this->appConfig->getValueString(Application::APP_ID, 'footer_signed_by', $this->l10n->t('Digitally signed by LibreSign.'))
			);
		}

		if (!$this->templateVars->getDirection()) {
			$this->templateVars->setDirection(
				$this->l10nFactory->getLanguageDirection($this->l10n->getLanguageCode())
			);
		}

		if (!$this->templateVars->getLinkToSite()) {
			$this->templateVars->setLinkToSite(
				$this->appConfig->getValueString(Application::APP_ID, 'footer_link_to_site', 'https://libresign.coop')
			);
		}

		if (!$this->templateVars->getValidationSite() && $this->templateVars->getUuid()) {
			$validationSite = $this->appConfig->getValueString(Application::APP_ID, 'validation_site');
			if ($validationSite) {
				$this->templateVars->setValidationSite(
					rtrim($validationSite, '/') . '/' . $this->templateVars->getUuid()
				);
			} else {
				$this->templateVars->setValidationSite(
					$this->urlGenerator->linkToRouteAbsolute('libresign.page.validationFileWithShortUrl', [
						'uuid' => $this->templateVars->getUuid(),
					])
				);
			}
		}

		if (!$this->templateVars->getValidateIn()) {
			$validateIn = $this->appConfig->getValueString(Application::APP_ID, 'footer_validate_in', 'Validate in %s.');
			if ($validateIn === 'Validate in %s.') {
				$this->templateVars->setValidateIn($this->l10n->t('Validate in %s.', ['%s']));
			} else {
				$this->templateVars->setValidateIn($validateIn);
			}
		}

		if ($this->appConfig->getValueBool(Application::APP_ID, 'write_qrcode_on_footer', true) && $this->templateVars->getValidationSite()) {
			$this->templateVars->setQrcode($this->getQrCodeImageBase64($this->templateVars->getValidationSite()));
		}

		$vars = $this->templateVars->toArray();
		foreach ($vars as $key => $value) {
			if (is_string($value)) {
				$vars[$key] = htmlentities($value, ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML401);
			}
		}

		return $vars;
	}

	private function getTemplate(): string {
		$footerTemplate = $this->appConfig->getValueString(Application::APP_ID, 'footer_template', '');
		if ($footerTemplate) {
			return $footerTemplate;
		}
		return (string)file_get_contents(__DIR__ . '/Templates/footer.twig');
	}

	private function getQrCodeImageBase64(string $text): string {
		$this->qrCode = QrCode::create($text)
			->setEncoding(new Encoding('UTF-8'))
			->setErrorCorrectionLevel(ErrorCorrectionLevel::Low)
			->setMargin(4)
			->setRoundBlockSizeMode(RoundBlockSizeMode::Margin)
			->setForegroundColor(new Color(0, 0, 0))
			->setBackgroundColor(new Color(255, 255, 255));
		$this->setQrCodeSize();
		$writer = new PngWriter();
		$result = $writer->write($this->qrCode);
		$qrcode = base64_encode($result->getString());

		$this->templateVars->setQrcodeSize($this->qrCode->getSize() + $this->qrCode->getMargin() * 2);

		return $qrcode;
	}

	private function setQrCodeSize(): void {
		$blockValues = $this->getQrCodeBlocks();
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
}
