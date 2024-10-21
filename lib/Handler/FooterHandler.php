<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler;

use BaconQrCode\Encoder\Encoder;
use Endroid\QrCode\Bacon\ErrorCorrectionLevelConverter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Mpdf\Mpdf;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\PdfParserService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\File;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\IURLGenerator;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

class FooterHandler {
	private QrCode $qrCode;
	private Environment $twigEnvironment;
	private File $file;
	private FileEntity $fileEntity;
	private const MIN_QRCODE_SIZE = 100;
	private const PIXEL_TO_CENTIMETER = 0.264583333;
	private array $templateVars = [];

	public function __construct(
		private IAppConfig $appConfig,
		private PdfParserService $pdfParserService,
		private IURLGenerator $urlGenerator,
		private IL10N $l10n,
		private ITempManager $tempManager,
	) {
		$this->twigEnvironment = new Environment(
			new FilesystemLoader(),
		);
	}

	public function getFooter(File $file, FileEntity $fileEntity): string {
		$this->file = $file;
		$this->fileEntity = $fileEntity;
		$add_footer = (bool)$this->appConfig->getAppValue('add_footer', '1');
		if (!$add_footer) {
			return '';
		}

		$metadata = $this->getMetadata();
		foreach ($metadata['d'] as $dimension) {
			$orientation = $dimension['w'] > $dimension['h'] ? 'L' : 'P';
			if (!isset($pdf)) {
				$pdf = new Mpdf([
					'tempDir' => $this->tempManager->getTempBaseDir(),
					'orientation' => $orientation,
					'margin_left' => 0,
					'margin_right' => 0,
					'margin_top' => 0,
					'margin_bottom' => 0,
					'margin_header' => 0,
					'margin_footer' => 0,
					'format' => [
						$dimension['w'] * self::PIXEL_TO_CENTIMETER,
						$dimension['h'] * self::PIXEL_TO_CENTIMETER,
					],
				]);
			} else {
				$pdf->AddPage(
					orientation: $orientation,
					newformat: [
						$dimension['w'] * self::PIXEL_TO_CENTIMETER,
						$dimension['h'] * self::PIXEL_TO_CENTIMETER,
					],
				);
			}

			$pdf->SetHTMLFooter($this->getRenderedHtmlFooter());
		}

		return $pdf->Output('', 'S');
	}

	private function getMetadata(): array {
		$metadata = $this->fileEntity->getMetadata();
		if (!is_array($metadata) || !isset($metadata['d'])) {
			$metadata = $this->pdfParserService->getMetadata($this->file);
		}
		return $metadata;
	}

	private function getRenderedHtmlFooter(): string {
		try {
			return $this->twigEnvironment
				->createTemplate($this->getTemplate())
				->render($this->getTemplateVars());
		} catch (SyntaxError $e) {
			throw new LibresignException($e->getMessage());
		}
	}

	public function setTemplateVar(string $name, mixed $value): self {
		$this->templateVars[$name] = $value;
		return $this;
	}

	private function getTemplateVars(): array {
		$this->templateVars['signedBy'] = iconv(
			'UTF-8',
			'windows-1252',
			$this->appConfig->getAppValue('footer_signed_by', $this->l10n->t('Digital signed by LibreSign.'))
		);

		$this->templateVars['linkToSite'] = $this->appConfig->getAppValue('footer_link_to_site', 'https://libresign.coop');

		$this->templateVars['validationSite'] = $this->appConfig->getAppValue('validation_site');
		if ($this->templateVars['validationSite']) {
			$this->templateVars['validationSite'] = rtrim($this->templateVars['validationSite'], '/') . '/' . $this->fileEntity->getUuid();
		} else {
			$this->templateVars['validationSite'] = $this->urlGenerator->linkToRouteAbsolute('libresign.page.validationFileWithShortUrl', [
				'uuid' => $this->fileEntity->getUuid(),
			]);
		}

		$this->templateVars['validateIn'] = $this->appConfig->getAppValue('footer_validate_in', 'Validate in %s.');
		if ($this->templateVars['validateIn'] === 'Validate in %s.') {
			$this->templateVars['validateIn'] = $this->l10n->t('Validate in %s.', ['%s']);
		}

		$this->templateVars['qrcode'] = $this->getQrCodeImageBase64($this->templateVars['validationSite']);

		return $this->templateVars;
	}

	private function getTemplate(): string {
		return $this->appConfig->getAppValue('footer_template', <<<'HTML'
			<table style="width:100%;border:0;font-size:8px;">
				<tr>
					{% if qrcode %}
						<td width="{{ qrcodeSize }}px">
							<img src="data:image/png;base64,{{ qrcode }}" style="width:{{ qrcodeSize }}px"/>
						</td>
					{% endif %}
					<td style="vertical-align: bottom;padding: 0px 0px 15px 0px;line-height:1.5em;">
						<a href="{{ linkToSite }}" style="text-decoration: none;color:unset;">{{ signedBy }}</a>
						{% if validateIn %}
							<br>
							<a href="{{ validationSite }}"
								style="text-decoration: none;color:unset;">
								{{ validateIn|replace({'%s': validationSite}) }}
							</a>
						{% endif %}
					</td>
				</tr>
			</table>
			HTML
		);
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

		$this->templateVars['qrcodeSize'] = $this->qrCode->getSize() + $this->qrCode->getMargin() * 2;

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
