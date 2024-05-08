<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2024 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Libresign\Handler;

use BaconQrCode\Encoder\Encoder;
use Endroid\QrCode\Bacon\ErrorCorrectionLevelConverter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Matrix\Matrix;
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
		$add_footer = (bool) $this->appConfig->getAppValue('add_footer', '1');
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

	private function getTemplateVars(): array {
		$this->templateVars['signedBy'] = iconv(
			'UTF-8',
			'windows-1252',
			$this->appConfig->getAppValue('footer_signed_by', $this->l10n->t('Digital signed by LibreSign.'))
		);

		$this->templateVars['linkToSite'] = $this->appConfig->getAppValue('footer_link_to_site', 'https://libresign.coop');

		$this->templateVars['validationSite'] = $this->appConfig->getAppValue('validation_site');
		if ($this->templateVars['validationSite']) {
			$this->templateVars['validationSite'] = rtrim($this->templateVars['validationSite'], '/').'/'.$this->fileEntity->getUuid();
		} else {
			$this->templateVars['validationSite'] = $this->urlGenerator->linkToRouteAbsolute('libresign.page.validationFileWithShortUrl', [
				'uuid' => $this->fileEntity->getUuid(),
			]);
		}

		$this->templateVars['validateIn'] = $this->appConfig->getAppValue('footer_validate_in', 'Validate in %s.');
		if ($this->templateVars['validateIn'] === 'Validate in %s.') {
			$this->templateVars['validateIn'] = $this->l10n->t('Validate in %s.', $this->templateVars['validationSite']);
		}

		$this->templateVars['qrcode'] = $this->getQrCodeImageTag($this->templateVars['validationSite']);

		return $this->templateVars;
	}

	private function getTemplate(): string {
		return $this->appConfig->getAppValue('footer_template', <<<'HTML'
			<table border="0" width="100%" style="font-size:8px;">
				<tr>
					{% if qrcode %}
						<td width="{{ qrcodeSize }}px">
							{{ qrcode|raw }}
						</td>
					{% endif %}
					{% if validateIn %}
						<td style="vertical-align: bottom;padding: 0px 0px 15px 0px;line-height:1.5em;">
							<a href="{{ linkToSite }}" style="text-decoration: none;color:unset;">{{ signedBy }}</a><br>
							<a href="{{ validateIn }}" style="text-decoration: none;color:unset;">{{ validateIn }}</a>
						</td>
					{% endif %}
				</tr>
			</table>
			HTML
		);
	}

	private function getQrCodeImageTag(string $text): string {
		$this->qrCode = QrCode::create($text)
			->setEncoding(new Encoding('UTF-8'))
			->setErrorCorrectionLevel(ErrorCorrectionLevel::Low)
			->setMargin(5)
			->setRoundBlockSizeMode(RoundBlockSizeMode::Margin)
			->setForegroundColor(new Color(0, 0, 0))
			->setBackgroundColor(new Color(255, 255, 255));
		$blockValues = $this->getQrCodeBlocks();
		$this->setQrCodeSize($blockValues);
		$writer = new PngWriter();
		$result = $writer->write($this->qrCode);
		$qrcode = base64_encode($result->getString());

		$matrix = new Matrix($blockValues, $this->qrCode->getSize(), $this->qrCode->getMargin(), $this->qrCode->getRoundBlockSizeMode());
		$this->templateVars['qrcodeSize'] = $matrix->getOuterSize();

		return '<img src="data:image/png;base64,' . $qrcode . '" style="width:' . $this->templateVars['qrcodeSize'] . 'px"/>';
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
}
