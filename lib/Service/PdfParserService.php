<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
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
			$output['d'][] = [
				'w' => $details['MediaBox'][2],
				'h' => $details['MediaBox'][3]
			];
		}
		return $output;
	}
}
