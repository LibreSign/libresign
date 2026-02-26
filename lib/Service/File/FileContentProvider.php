<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use OCA\Libresign\Exception\LibresignException;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClientService;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class FileContentProvider {
	public function __construct(
		private IClientService $client,
		private MimeService $mimeService,
		private IRootFolder $root,
		private LoggerInterface $logger,
		private IL10N $l10n,
	) {
	}

	/**
	 * Get file content from a URL
	 *
	 * @throws \Exception if URL is invalid or content cannot be retrieved
	 */
	public function getContentFromUrl(string $url): string {
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			throw new LibresignException($this->l10n->t('Invalid URL file'), 422);
		}

		try {
			$response = $this->client->newClient()->get($url);
		} catch (\Throwable) {
			throw new LibresignException($this->l10n->t('Invalid URL file'), 422);
		}

		$content = (string)$response->getBody();

		if (!$content) {
			throw new LibresignException($this->l10n->t('Empty file'), 422);
		}

		$mimetypeFromHeader = $response->getHeader('Content-Type');
		// Strip parameters like "; charset=utf-8"
		if (str_contains($mimetypeFromHeader, ';')) {
			$mimetypeFromHeader = trim(explode(';', $mimetypeFromHeader)[0]);
		}

		$mimeTypeFromContent = $this->mimeService->getMimeType($content);

		// application/octet-stream is a generic fallback — trust content detection
		if ($mimetypeFromHeader !== 'application/octet-stream' && $mimetypeFromHeader !== $mimeTypeFromContent) {
			throw new LibresignException($this->l10n->t('Invalid URL file'), 422);
		}

		return $content;
	}

	/**
	 * Decode base64 content and validate MIME type
	 *
	 * @throws \Exception if MIME types don't match
	 */
	public function getContentFromBase64(string $base64): string {
		$withMime = explode(',', $base64);

		if (count($withMime) === 2) {
			$withMime[0] = explode(';', $withMime[0]);
			$withMime[0][0] = explode(':', $withMime[0][0]);
			$mimeTypeFromType = $withMime[0][0][1];

			$base64 = $withMime[1];

			$content = base64_decode($base64);
			$mimeTypeFromContent = $this->mimeService->getMimeType($content);

			if ($mimeTypeFromType !== $mimeTypeFromContent) {
				throw new LibresignException($this->l10n->t('Invalid URL file'), 422);
			}

			$this->mimeService->setMimeType($mimeTypeFromContent);
		} else {
			$content = base64_decode($base64);
			$this->mimeService->getMimeType($content);
		}

		return $content;
	}

	/**
	 * Get raw file content from URL or base64 data array
	 *
	 * @param array $data Data array containing 'file' with 'url' or 'base64'
	 * @return string File content
	 * @throws \Exception if data is invalid
	 */
	public function getContentFromData(array $data): string {
		if (!empty($data['file']['url'])) {
			return $this->getContentFromUrl($data['file']['url']);
		}

		if (!empty($data['file']['base64'])) {
			return $this->getContentFromBase64($data['file']['base64']);
		}

		throw new LibresignException($this->l10n->t('No file source provided'), 422);
	}

	/**
	 * Get file content from a LibreSign File entity
	 *
	 * @param \OCA\Libresign\Db\File $file
	 * @throws LibresignException
	 */
	public function getContentFromLibresignFile(\OCA\Libresign\Db\File $file): string {
		try {
			$nodeId = $file->getSignedNodeId();
			if (!$nodeId) {
				$nodeId = $file->getNodeId();
			}

			$fileNode = $this->root->getUserFolder($file->getUserId())->getFirstNodeById($nodeId);

			if (!$fileNode instanceof \OCP\Files\File) {
				throw new LibresignException($this->l10n->t('File not found'), 404);
			}

			return $fileNode->getContent();
		} catch (LibresignException $e) {
			throw $e;
		} catch (\Throwable $e) {
			$this->logger->error('Failed to get file content: ' . $e->getMessage(), [
				'fileId' => $file->getId(),
				'exception' => $e,
			]);
			throw new LibresignException($this->l10n->t('Invalid data to validate file'), 404, $e);
		}
	}
}
