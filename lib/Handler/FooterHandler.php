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
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use League\Plates\Engine;
use Mpdf\Mpdf;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Service\PdfParserService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\File;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\IURLGenerator;

class FooterHandler {
	private QrCode $qrCode;
	private File $file;
	private FileEntity $fileEntity;
	private const MIN_QRCODE_SIZE = 100;
	private const POINT_TO_MILIMETER = 0.3527777778;
	private array $templateVars = [];

	public function __construct(
		private IAppConfig $appConfig,
		private PdfParserService $pdfParserService,
		private IURLGenerator $urlGenerator,
		private IL10N $l10n,
		private ITempManager $tempManager,
	) {
	}

	public function getFooter(File $file, FileEntity $fileEntity): string {
		$this->file = $file;
		$this->fileEntity = $fileEntity;
		$add_footer = (bool)$this->appConfig->getAppValue('add_footer', '1');
		if (!$add_footer) {
			return '';
		}

		$htmlFooter = $this->getRenderedHtmlFooter();
		$metadata = $this->getMetadata();
		foreach ($metadata['d'] as $dimension) {
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

	private function getMetadata(): array {
		$metadata = $this->fileEntity->getMetadata();
		if (!is_array($metadata) || !isset($metadata['d'])) {
			$metadata = $this->pdfParserService->getMetadata($this->file);
		}
		return $metadata;
	}

	private function getRenderedHtmlFooter(): string {
		$tempFile = $this->tempManager->getTemporaryFile('footerTemplate.php');
		file_put_contents($tempFile, $this->getTemplate());
		$templates = new Engine($this->tempManager->getTempBaseDir());
		return $templates->render(pathinfo($tempFile, PATHINFO_FILENAME), $this->getTemplateVars());
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

		if ($this->appConfig->getAppValue('write_qrcode_on_footer', '1')) {
			$this->templateVars['qrcode'] = $this->getQrCodeImageBase64($this->templateVars['validationSite']);
		}

		return $this->templateVars;
	}

	private function getTemplate(): string {
		return $this->appConfig->getAppValue('footer_template', file_get_contents(__DIR__ . '/Templates/footer.php'));
	}

	private function getQrCodeImageBase64(string $text): string {
		$this->qrCode = QrCode::create($text)
			->setEncoding(new Encoding('UTF-8'))
			->setErrorCorrectionLevel(new ErrorCorrectionLevelLow())
			->setMargin(4)
			->setRoundBlockSizeMode(new RoundBlockSizeModeMargin())
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
