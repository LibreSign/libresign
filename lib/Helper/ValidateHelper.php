<?php

namespace OCA\Libresign\Helper;

use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Service\FolderService;
use OCP\IL10N;

class ValidateHelper {
	/** @var IL10N */
	private $l10n;
	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var FolderService */
	private $folderService;

	public function __construct(
		IL10N $l10n,
		FileUserMapper $fileUserMapper,
		FolderService $folderService
	) {
		$this->l10n = $l10n;
		$this->fileUserMapper = $fileUserMapper;
		$this->folderService = $folderService;
	}
	public function validateFile(array $data) {
		if (empty($data['file'])) {
			throw new \Exception($this->l10n->t('Empty file'));
		}
		if (empty($data['file']['url']) && empty($data['file']['base64']) && empty($data['file']['fileId'])) {
			throw new \Exception($this->l10n->t('Inform URL or base64 or fileID to sign'));
		}
		if (!empty($data['file']['fileId'])) {
			if (!is_numeric($data['file']['fileId'])) {
				throw new \Exception($this->l10n->t('Invalid fileID'));
			}
			$this->validateFileByNodeId((int)$data['file']['fileId']);
		}
		if (!empty($data['file']['base64'])) {
			$input = base64_decode($data['file']['base64']);
			$base64 = base64_encode($input);
			if ($data['file']['base64'] !== $base64) {
				throw new \Exception($this->l10n->t('Invalid base64 file'));
			}
		}
	}

	public function validateFileByNodeId(int $nodeId) {
		try {
			$fileMapper = $this->fileUserMapper->getByNodeId($nodeId);
		} catch (\Throwable $th) {
		}
		if (!empty($fileMapper)) {
			throw new \Exception($this->l10n->t('Already asked to sign this document'));
		}

		try {
			$userFolder = $this->folderService->getFolder($nodeId);
			$node = $userFolder->getById($nodeId);
		} catch (\Throwable $th) {
			throw new \Exception($this->l10n->t('Invalid fileID'));
		}
		if (!$node) {
			throw new \Exception($this->l10n->t('Invalid fileID'));
		}
		$node = $node[0];
		if ($node->getMimeType() !== 'application/pdf') {
			throw new \Exception($this->l10n->t('Must be a fileID of a PDF'));
		}
	}
}
