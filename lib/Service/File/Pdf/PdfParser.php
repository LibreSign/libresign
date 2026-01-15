<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File\Pdf;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Vendor\Smalot\PdfParser\Document;
use OCA\Libresign\Vendor\Smalot\PdfParser\Parser;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class PdfParser {
	public function __construct(
		private LoggerInterface $logger,
		private IL10N $l10n,
	) {
	}

	/**
	 * Parse PDF content and return Document object
	 *
	 * @param string $content PDF content
	 * @param string $fileName File name for error messages
	 * @return Document
	 * @throws LibresignException
	 */
	public function parse(string $content, string $fileName): Document {
		try {
			$parser = new Parser();
			return $parser->parseContent($content);
		} catch (\Throwable $th) {
			$this->throwIfEncryptedPdf($th, $fileName);

			$this->logger->error('PDF parsing failed: ' . $th->getMessage());

			// TRANSLATORS %s is the file name that could not be processed as PDF
			throw new LibresignException($this->l10n->t('Unable to process the file as a PDF document: %s', [$fileName]));
		}
	}

	/**
	 * @throws LibresignException Only if the PDF is encrypted/password-protected
	 */
	private function throwIfEncryptedPdf(\Throwable $th, string $fileName): void {
		if ($th->getMessage() !== 'Secured pdf file are currently not supported.') {
			return;
		}

		// TRANSLATORS %s is the name of the password-protected PDF file
		throw new LibresignException($this->l10n->t('The file "%s" is password-protected. Please remove the protection and try again.', [$fileName]));
	}
}
