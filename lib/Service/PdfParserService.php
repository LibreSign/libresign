<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Exception\LibresignException;
use OCP\Files\File;
use OCP\ITempManager;
use Psr\Log\LoggerInterface;

class PdfParserService {
	public function __construct(
		private ITempManager $tempManager,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @return (array[]|int)[]
	 * @throws LibresignException
	 * @psalm-return array{p: int, d?: non-empty-list<array{w: mixed, h: mixed}>}
	 */
	public function getMetadata(File $node): array {
		try {
			$content = $node->getContent();
		} catch (\Throwable $th) {
		}
		if (!$content) {
			throw new LibresignException('Empty file.');
		}

		/**
		 * Generate temporary file to prevent error when get path of
		 * shared file
		 */
		$tempFile = $this->tempManager->getTemporaryFile('.pdf');
		file_put_contents($tempFile, $content);
		try {
			$output = $this->parsePdfOnlyWithPhp($tempFile);
		} catch (\Throwable $th) {
			if ($th->getMessage() === 'Secured pdf file are currently not supported.') {
				throw new LibresignException('Secured pdf file are currently not supported.');
			}
			$this->logger->error('Impossible get metadata from this file: ' . $th->getMessage());
			throw new LibresignException('Impossible get metadata from this file.');
		}
		return $output;
	}

	private function parsePdfOnlyWithPhp(string $filename): array {
		$parser = new \Smalot\PdfParser\Parser();
		$pdf = $parser->parseFile($filename);

		$pages = $pdf->getPages();
		$output = [
			'p' => count($pages),
		];
		foreach ($pages as $page) {
			$details = $page->getDetails();
			if (!isset($details['MediaBox'])) {
				$pages = $pdf->getObjectsByType('Pages');
				$details = reset($pages)->getHeader()->getDetails();
			}
			$widthAndHeight = [
				'w' => $details['MediaBox'][2],
				'h' => $details['MediaBox'][3]
			];
			if (!is_numeric($widthAndHeight['w']) || !is_numeric($widthAndHeight['h'])) {
				$this->logger->error('Impossible get metadata from this file: ' . $filename . '. Error to get page width and height.');
				throw new LibresignException('Impossible get metadata from this file: ' . $filename . '. Error to get page width and height. If possible, open an issue at github.com/libresign/libresign with the file that you used.');
			}
			$output['d'][] = $widthAndHeight;
		}
		return $output;
	}
}
