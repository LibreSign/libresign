<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\Helper\ValidateHelper;
use OCP\Http\Client\IClientService;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
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

	public function getNodeFromData(array $data): \OCP\Files\Node {
		if (!$this->folderService->getUserId()) {
			$this->folderService->setUserId($data['userManager']->getUID());
		}
		if (isset($data['file']['fileId'])) {
			$userFolder = $this->folderService->getFolder($data['file']['fileId']);
			return $userFolder->getById($data['file']['fileId'])[0];
		}
		$userFolder = $this->folderService->getFolder();
		$folderName = $this->folderService->getFolderName($data, $data['userManager']);
		if ($userFolder->nodeExists($folderName)) {
			throw new \Exception($this->l10n->t('File already exists'));
		}
		$folderToFile = $userFolder->newFolder($folderName);
		return $folderToFile->newFile($data['name'] . '.pdf', $this->getFileRaw($data));
	}

	/**
	 * @return false|resource|string
	 */
	private function getFileRaw(array $data) {
		if (!empty($data['file']['url'])) {
			if (!filter_var($data['file']['url'], FILTER_VALIDATE_URL)) {
				throw new \Exception($this->l10n->t('Invalid URL file'));
			}
			$response = $this->client->newClient()->get($data['file']['url']);
			$contentType = $response->getHeader('Content-Type');
			if ($contentType !== 'application/pdf') {
				throw new \Exception($this->l10n->t('The URL should be a PDF.'));
			}
			$content = $response->getBody();
			if (!$content) {
				throw new \Exception($this->l10n->t('Empty file'));
			}
			$this->validateHelper->validateBase64($content);
		} else {
			$content = $this->getFileFromBase64($data['file']['base64']);
		}
		$this->validatePdfStringWithFpdi($content);
		return $content;
	}

	private function getFileFromBase64(string $base64): string {
		$withMime = explode(',', $base64);
		if (count($withMime) === 2) {
			$withMime[0] = explode(';', $withMime[0]);
			$base64 = $withMime[1];
		}
		return base64_decode($base64);
	}

	/**
	 * Validates a PDF. Triggers error if invalid.
	 *
	 * @param string $string
	 *
	 * @throws Type\PdfTypeException
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
