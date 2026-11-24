<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignatureStampPreview;

use OCA\Libresign\Vendor\LibreSign\XObjectTemplate\Dto\CompileRequest;
use OCA\Libresign\Vendor\LibreSign\XObjectTemplate\Layout\LayoutLine;
use OCA\Libresign\Vendor\LibreSign\XObjectTemplate\Layout\LayoutResult;
use OCA\Libresign\Vendor\LibreSign\XObjectTemplate\Pdf\PdfEscaper;
use OCA\Libresign\Vendor\LibreSign\XObjectTemplate\Pdf\SignerPhpCoreXObjectResourceBuilder;
use OCA\Libresign\Vendor\LibreSign\XObjectTemplate\Pdf\TemplateDocumentBuilder;
use OCA\Libresign\Vendor\SignerPHP\PdfCore\PdfDocument;
use OCA\Libresign\Vendor\SignerPHP\PdfCore\PdfValue\PDFValueReference;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SignatureAppearanceXObjectDto;

final class SignatureStampPdfLayout {
	private const BASE_FONT = [
		'Type' => '/Font',
		'Subtype' => '/Type1',
		'BaseFont' => '/Helvetica',
	];

	/** @var list<LayoutLine> */
	private array $lines = [];

	public function addLine(string $text, float $x, float $y, float $fontSize): void {
		$this->lines[] = new LayoutLine($text, $x, $y, $fontSize, 'F1', '#000000');
	}

	public function build(int $width, int $height): SignatureAppearanceXObjectDto {
		// The existing layout contains literal text, not HTML. Let the XObject
		// library encode glyphs and build the font resources for that layout.
		$documentBuilder = (new TemplateDocumentBuilder())->withFontResources(['F1' => self::BASE_FONT]);
		foreach ($this->lines as $line) {
			if (preg_match('/[^\x00-\x7F]/', $line->text) === 1) {
				$documentBuilder = $documentBuilder->withUnicodeFontFiles([
					dirname(__DIR__, 3) . '/3rdparty/composer/mpdf/mpdf/ttfonts/DejaVuSansCondensed.ttf',
				]);
				break;
			}
		}
		$result = $documentBuilder->build(
			new CompileRequest('', (float)$width, (float)$height),
			new LayoutResult($this->lines, []),
			hrtime(true),
		);

		$resources = $result->resources;
		// PdfCore serializes an empty array as a PDF list, not a dictionary.
		// Leave the XObject key absent so the native engine can add the drawn image.
		unset($resources['XObject']);

		return new SignatureAppearanceXObjectDto(
			stream: $result->contentStream,
			resources: $resources,
			resourcesBuilder: $this->materializeResources(...),
		);
	}

	/**
	 * @param array<string, mixed> $resources
	 * @return array<string, mixed>
	 */
	private function materializeResources(PdfDocument $document, array $resources): array {
		$resources = (new SignerPhpCoreXObjectResourceBuilder())->materialize($document, $resources);
		$fonts = $resources['Font'];
		if (!is_array($fonts)) {
			throw new \UnexpectedValueException('Invalid signature stamp font resources.');
		}
		// Make the standard font indirect as well so readers can resolve its object ID.
		$fonts['F1'] = new PDFValueReference($document->createObject(self::BASE_FONT)->getOid());
		$resources['Font'] = $fonts;
		return $resources;
	}

	public static function escapePdfText(string $value): string {
		return (new PdfEscaper())->escapeLiteralString($value);
	}
}
