<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Font;

class BundledMpdfFontLocator extends BundledFontLocator {
	#[\Override]
	public function requireFontFile(string $fontFile): string {
		$fontPath = $this->findFontFile($fontFile);
		if ($fontPath !== null) {
			return $fontPath;
		}

		throw new \RuntimeException(sprintf('Bundled mPDF font not found: %s', $fontFile));
	}
}
