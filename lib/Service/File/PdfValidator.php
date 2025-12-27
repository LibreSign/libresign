<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\DocMdpHandler;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class PdfValidator {
	public function __construct(
		private DocMdpHandler $docMdpHandler,
		private LoggerInterface $logger,
		private IL10N $l10n,
	) {
	}

	/**
	 * Validate PDF content and DocMDP restrictions.
	 *
	 * @throws \Exception
	 * @throws LibresignException
	 */
	public function validate(string $content): void {
		try {
			$parser = new \OCA\Libresign\Vendor\Smalot\PdfParser\Parser();
			$parser->parseContent($content);
		} catch (\Throwable $th) {
			$this->logger->error($th->getMessage());
			throw new \Exception($this->l10n->t('Invalid PDF'));
		}

		$resource = fopen('php://memory', 'r+');
		if (!is_resource($resource)) {
			return;
		}

		try {
			fwrite($resource, $content);
			rewind($resource);

			if (!$this->docMdpHandler->allowsAdditionalSignatures($resource)) {
				throw new LibresignException($this->l10n->t('This document has been certified with no changes allowed, so no additional signatures can be added.'));
			}
		} finally {
			fclose($resource);
		}
	}
}
