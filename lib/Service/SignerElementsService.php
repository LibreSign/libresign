<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\UserElement;
use OCA\Libresign\Db\UserElementMapper;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\IURLGenerator;

class SignerElementsService {
	public const ELEMENT_SIGN_WIDTH = 350;
	public const ELEMENT_SIGN_HEIGHT = 100;

	public function __construct(
		private FolderService $folderService,
		private SessionService $sessionService,
		private IURLGenerator $urlGenerator,
		private UserElementMapper $userElementMapper,
	) {
	}

	public function getUserElementByNodeId(string $userId, int $nodeId): UserElement {
		$element = $this->userElementMapper->findOne(['file_id' => $nodeId, 'user_id' => $userId]);
		$exists = $this->signatureFileExists($element);
		if (!$exists) {
			throw new NotFoundException();
		}
		$userElement = new UserElement();
		$userElement->fromRow([
			'id' => $element->getId(),
			'type' => $element->getType(),
			'file' => [
				'url' => $this->urlGenerator->linkToRoute('ocs.libresign.SignatureElements.getSignatureElementPreview', [
					'apiVersion' => 'v1',
					'nodeId' => $element->getFileId(),
				]),
				'nodeId' => $element->getFileId()
			],
			'userId' => $element->getUserId(),
			'starred' => $element->getStarred() ? 1 : 0,
			'createdAt' => $element->getCreatedAt()
		]);
		return $userElement;
	}

	public function getUserElements(string $userId): array {
		$elements = $this->userElementMapper->findMany(['user_id' => $userId]);
		$return = [];
		foreach ($elements as $element) {
			$exists = $this->signatureFileExists($element);
			if (!$exists) {
				continue;
			}
			$return[] = (new UserElement())->fromRow([
				'id' => $element->getId(),
				'type' => $element->getType(),
				'file' => [
					'url' => $this->urlGenerator->linkToRoute('ocs.libresign.SignatureElements.getSignatureElementPreview', [
						'apiVersion' => 'v1',
						'nodeId' => $element->getFileId(),
					]),
					'nodeId' => $element->getFileId()
				],
				'starred' => $element->getStarred() ? 1 : 0,
				'createdAt' => $element->getCreatedAt()->format('Y-m-d H:i:s'),
			]);
		}
		return $return;
	}

	private function signatureFileExists(UserElement $userElement): bool {
		try {
			$this->folderService->getFileById($userElement->getFileId());
		} catch (\Exception $e) {
			$this->userElementMapper->delete($userElement);
			return false;
		}
		return true;
	}

	public function getElementsFromSession(): array {
		$folder = $this->folderService->getFolder();
		try {
			/** @var Folder $signerFolder */
			$signerFolder = $folder->get($this->sessionService->getSessionId());
		} catch (NotFoundException $th) {
			return [];
		}
		$fileList = $signerFolder->getDirectoryListing();
		return $fileList;
	}

	public function getElementsFromSessionAsArray(): array {
		$return = [];
		$fileList = $this->getElementsFromSession();
		foreach ($fileList as $fileElement) {
			list($type, $timestamp) = explode('_', pathinfo($fileElement->getName(), PATHINFO_FILENAME));
			$return[] = (new UserElement())->fromRow([
				'type' => $type,
				'file' => [
					'url' => $this->urlGenerator->linkToRoute('ocs.libresign.SignatureElements.getSignatureElementPreview', [
						'apiVersion' => 'v1',
						'nodeId' => $fileElement->getId(),
						'mtime' => $fileElement->getMTime(),
					]),
					'nodeId' => $fileElement->getId(),
				],
				'starred' => 0,
				'createdAt' => (new \DateTime())->setTimestamp((int) $timestamp)->format('Y-m-d H:i:s'),
			]);
		}
		return $return;
	}
}
