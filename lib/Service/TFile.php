<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\ValidateHelper;
use OCP\Files\IMimeTypeDetector;
use OCP\Http\Client\IClientService;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use setasign\Fpdi\PdfParserService\Type\PdfTypeException;
use TCPDF_PARSER;

trait TFile {
	/** @var FolderService */
	private $folderService;
	/** @var IClientService */
	private $client;
	/** @var ValidateHelper */
	private $validateHelper;
	/** @var LoggerInterface */
	private $logger;
	/** @var IL10N */
	private $l10n;
	/** @var IMimeTypeDetector */
	private $mimeTypeDetector;
	/** @var ?string */
	private $mimetype = null;

	public function getNodeFromData(array $data): \OCP\Files\Node {
		if (!$this->folderService->getUserId()) {
			$this->folderService->setUserId($data['userManager']->getUID());
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
		$data['name'] = $this->sanitizeName($data['name'], $extension);

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

	private function sanitizeName(?string $name, string $extension): ?string {
		//MimeTypeDetector
		if (!empty($name)) {
			$extensionWithDot = substr($name, strlen($name) - strlen($extension) - 1);
			if ($extensionWithDot[0] === '.') {
				if (ltrim($extensionWithDot, '.') !== $extension) {
					throw new LibresignException($this->l10n->t('Invalid file type.'));
				}
				return rtrim($name, '.' . $extension);
			}
		}
		return $name;
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
			$content = $response->getBody();
			if (!$content) {
				throw new \Exception($this->l10n->t('Empty file'));
			}
			$this->validateHelper->validateBase64($content);
			$mimeTypeFromContent = $this->getMimeType($content);
			if ($mimetypeFromHeader !== $mimeTypeFromContent) {
				throw new \Exception($this->l10n->t('Invalid URL file'));
			}
			$this->setMimeType($mimeTypeFromContent);
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
