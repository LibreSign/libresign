<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignatureStampPreview;

use OCA\Libresign\Service\SignatureStampPreview\SignatureStampAppearanceBuilder;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;

final class SignatureStampAppearanceBuilderTest extends \OCA\Libresign\Tests\Unit\TestCase {
	public function testBuildXObjectEncodesTextAsWinAnsi(): void {
		$signatureTextService = $this->createMock(SignatureTextService::class);
		$signatureTextService->method('parse')->willReturn([
			'parsed' => 'Signé par Renée',
			'templateFontSize' => 10.0,
		]);
		$builder = new SignatureStampAppearanceBuilder($signatureTextService);

		$xObject = $builder->buildXObject(
			100,
			50,
			SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY,
		);

		$this->assertSame('/WinAnsiEncoding', $xObject->resources['Font']['F1']['Encoding']);
		$this->assertStringContainsString("(Sign\xE9 par Ren\xE9e) Tj", $xObject->stream);
		$this->assertStringNotContainsString('Signé par Renée', $xObject->stream);
	}

	public function testWrapTextForPdfDoesNotSplitMultibyteCharacters(): void {
		$signatureTextService = $this->createMock(SignatureTextService::class);
		$builder = new SignatureStampAppearanceBuilder($signatureTextService);

		$this->assertSame(
			['éé', 'éé'],
			$builder->wrapTextForPdf('éééé', 15.0, 10.0),
		);
	}

	public function testEscapePdfTextRejectsCharactersOutsideWinAnsi(): void {
		$signatureTextService = $this->createMock(SignatureTextService::class);
		$builder = new SignatureStampAppearanceBuilder($signatureTextService);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('signature stamp contains characters that cannot be represented by WinAnsiEncoding');

		$builder->escapePdfText('Signed by 😀');
	}
}
