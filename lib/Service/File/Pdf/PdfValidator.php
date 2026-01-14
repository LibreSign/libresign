<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File\Pdf;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\DocMdpHandler;
use OCP\IL10N;

class PdfValidator {
	public function __construct(
		private PdfParser $pdfParser,
		private DocMdpHandler $docMdpHandler,
		private IL10N $l10n,
	) {
	}

	/**
	 * Validate PDF content and DocMDP restrictions.
	 *
	 * @param string $content The PDF content to validate
	 * @param string $fileName File name for error messages
	 * @throws LibresignException
	 */
	public function validate(string $content, string $fileName): void {
		$this->pdfParser->parse($content, $fileName);

		// Validate DocMDP restrictions
		$resource = fopen('php://memory', 'r+');
		if (!is_resource($resource)) {
			return;
		}

		try {
			fwrite($resource, $content);
			rewind($resource);

			if (!$this->docMdpHandler->allowsAdditionalSignatures($resource)) {
				// TRANSLATORS %s is the file name of the PDF with DocMDP restrictions
				throw new LibresignException($this->l10n->t('This document has been certified with no changes allowed, so no additional signatures can be added: %s', [$fileName]));
			}
		} finally {
			fclose($resource);
		}
	}
}
