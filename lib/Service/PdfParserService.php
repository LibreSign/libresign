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
	private string $content = '';
	public function __construct(
		private ITempManager $tempManager,
		private LoggerInterface $logger,
	) {
	}

	public function setFile(File|string $file): self {
		try {
			if ($file instanceof File) {
				$this->content = $file->getContent();
			} else {
				$this->content = $file;
			}
		} catch (\Throwable $th) {
		}
		if (!$this->content) {
			throw new LibresignException('Empty file.');
		}
		return $this;
	}

	/**
	 * @return (array[]|int)[]
	 * @throws LibresignException
	 * @psalm-return array{p: int, d?: non-empty-list<array{w: mixed, h: mixed}>}
	 */
	public function toArray(): array {
		try {
			$output = $this->parsePdfOnlyWithPhp();
		} catch (LibresignException $e) {
			$this->logger->error('Impossible get metadata from this file: ' . $e->getMessage());
			throw new LibresignException('Impossible get metadata from this file.');
		} catch (\Throwable $th) {
			if ($th->getMessage() === 'Secured pdf file are currently not supported.') {
				throw new LibresignException('Secured pdf file are currently not supported.');
			}
			$this->logger->error('Impossible get metadata from this file: ' . $th->getMessage());
			throw new LibresignException('Impossible get metadata from this file.');
		}
		return $output;
	}

	private function parsePdfOnlyWithPhp(): array {
		$parser = new \Smalot\PdfParser\Parser();
		$pdf = $parser->parseContent($this->content);

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
				throw new LibresignException('Error to get page width and height. If possible, open an issue at github.com/libresign/libresign with the file that you used.');
			}
			$output['d'][] = $widthAndHeight;
		}
		return $output;
	}
}
