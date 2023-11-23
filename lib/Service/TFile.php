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

use OCP\Files\Node;
use OCP\Http\Client\IClientService;
use setasign\Fpdi\PdfParserService\Type\PdfTypeException;
use TCPDF_PARSER;

trait TFile {
	/** @var ?string */
	private $mimetype = null;
	protected IClientService $client;

	public function getNodeFromData(array $data): Node {
		if (!$this->folderService->getUserId()) {
			$this->folderService->setUserId($data['userManager']->getUID());
		}
		if (isset($data['file']['fileNode']) && $data['file']['fileNode'] instanceof Node) {
			return $data['file']['fileNode'];
		}
		if (isset($data['file']['fileId'])) {
			$userFolder = $this->folderService->getFolder($data['file']['fileId']);
			return $userFolder->getById($data['file']['fileId'])[0];
		}

		$content = $this->getFileRaw($data);

		$extension = $this->getExtension($content);
		if ($extension === 'pdf') {
			$this->validatePdfStringWithFpdi($content);
		}

		$userFolder = $this->folderService->getFolder();
		$folderName = $this->folderService->getFolderName($data, $data['userManager']);
		if ($userFolder->nodeExists($folderName)) {
			throw new \Exception($this->l10n->t('File already exists'));
		}
		$folderToFile = $userFolder->newFolder($folderName);
		return $folderToFile->newFile($data['name'] . '.' . $extension, $content);
	}

	private function setMimeType(string $mimetype): void {
		$this->validateHelper->validateMimeTypeAcceptedByMime($mimetype);
		$this->mimetype = $mimetype;
	}

	private function getMimeType(string $content): ?string {
		if (!$this->mimetype) {
			$this->setMimeType($this->mimeTypeDetector->detectString($content));
		}
		return $this->mimetype;
	}

	private function getExtension(string $content): string {
		$mimetype = $this->getMimeType($content);
		$mappings = $this->mimeTypeDetector->getAllMappings();
		foreach ($mappings as $ext => $mimetypes) {
			if ($ext[0] === '_') {
				// comment
				continue;
			}
			if (in_array($mimetype, $mimetypes)) {
				return $ext;
			}
		}
		return '';
	}

	/**
	 * @return resource|string
	 */
	private function getFileRaw(array $data) {
		if (!empty($data['file']['url'])) {
			if (!filter_var($data['file']['url'], FILTER_VALIDATE_URL)) {
				throw new \Exception($this->l10n->t('Invalid URL file'));
			}
			$response = $this->client->newClient()->get($data['file']['url']);
			$mimetypeFromHeader = $response->getHeader('Content-Type');
			$content = (string) $response->getBody();
			if (!$content) {
				throw new \Exception($this->l10n->t('Empty file'));
			}
			$mimeTypeFromContent = $this->getMimeType($content);
			if ($mimetypeFromHeader !== $mimeTypeFromContent) {
				throw new \Exception($this->l10n->t('Invalid URL file'));
			}
		} else {
			$content = $this->getFileFromBase64($data['file']['base64']);
		}
		return $content;
	}

	private function getFileFromBase64(string $base64): string {
		$withMime = explode(',', $base64);
		if (count($withMime) === 2) {
			$withMime[0] = explode(';', $withMime[0]);
			$withMime[0][0] = explode(':', $withMime[0][0]);
			$mimeTypeFromType = $withMime[0][0][1];

			$base64 = $withMime[1];

			$content = base64_decode($base64);
			$mimeTypeFromContent = $this->getMimeType($content);
			if ($mimeTypeFromType !== $mimeTypeFromContent) {
				throw new \Exception($this->l10n->t('Invalid URL file'));
			}
			$this->setMimeType($mimeTypeFromContent);
		} else {
			$content = base64_decode($base64);
			$this->getMimeType($content);
		}
		return $content;
	}

	/**
	 * Validates a PDF. Triggers error if invalid.
	 *
	 * @param string $string
	 *
	 * @throws PdfTypeException
	 */
	private function validatePdfStringWithFpdi($string): void {
		try {
			new TCPDF_PARSER($string);
		} catch (\Throwable $th) {
			$this->logger->error($th->getMessage());
			throw new \Exception($this->l10n->t('Invalid PDF'));
		}
	}
}
