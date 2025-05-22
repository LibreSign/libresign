<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\UserElement;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\ResponseDefinitions;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\IURLGenerator;

/**
 * @psalm-import-type LibresignUserElement from ResponseDefinitions
 */
class SignerElementsService {
	public const RENDER_MODE_DESCRIPTION_ONLY = 'DESCRIPTION_ONLY';
	public const RENDER_MODE_SIGNAME_AND_DESCRIPTION = 'SIGNAME_AND_DESCRIPTION';
	public const RENDER_MODE_GRAPHIC_AND_DESCRIPTION = 'GRAPHIC_AND_DESCRIPTION';
	public const RENDER_MODE_DEFAULT = 'GRAPHIC_AND_DESCRIPTION';
	public function __construct(
		private FolderService $folderService,
		private SessionService $sessionService,
		private IURLGenerator $urlGenerator,
		private UserElementMapper $userElementMapper,
		private SignatureBackgroundService $signatureBackgroundService,
		private SignatureTextService $signatureTextService,
	) {
	}

	/**
	 * @return LibresignUserElement
	 */
	public function getUserElementByNodeId(string $userId, int $nodeId): array {
		$element = $this->userElementMapper->findOne(['file_id' => $nodeId, 'user_id' => $userId]);
		$exists = $this->signatureFileExists($element);
		if (!$exists) {
			throw new NotFoundException();
		}
		return [
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
			'createdAt' => $element->getCreatedAt()->format('Y-m-d H:i:s'),
		];
	}

	/**
	 * @return LibresignUserElement[]
	 */
	public function getUserElements(string $userId): array {
		$elements = $this->userElementMapper->findMany(['user_id' => $userId]);
		$return = [];
		foreach ($elements as $element) {
			$exists = $this->signatureFileExists($element);
			if (!$exists) {
				continue;
			}
			$return[] = [
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
				'userId' => $element->getUserId(),
				'createdAt' => $element->getCreatedAt()->format('Y-m-d H:i:s'),
			];
		}
		return $return;
	}

	private function signatureFileExists(UserElement $userElement): bool {
		try {
			$this->folderService->getFileById($userElement->getFileId());
		} catch (\Exception) {
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
		} catch (NotFoundException) {
			return [];
		}
		$fileList = $signerFolder->getDirectoryListing();
		return $fileList;
	}

	/**
	 * @return LibresignUserElement[]
	 */
	public function getElementsFromSessionAsArray(): array {
		$return = [];
		$fileList = $this->getElementsFromSession();
		foreach ($fileList as $fileElement) {
			[$type, $timestamp] = explode('_', pathinfo((string)$fileElement->getName(), PATHINFO_FILENAME));
			$return[] = [
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
				'createdAt' => (new \DateTime())->setTimestamp((int)$timestamp)->format('Y-m-d H:i:s'),
			];
		}
		return $return;
	}

	public function isSignElementsAvailable(): bool {
		return $this->signatureBackgroundService->isEnabled() || $this->signatureTextService->isEnabled();
	}

	public function canCreateSignature(): bool {
		return !in_array(
			$this->signatureTextService->getRenderMode(),
			[
				self::RENDER_MODE_DESCRIPTION_ONLY,
				self::RENDER_MODE_SIGNAME_AND_DESCRIPTION,
			]
		);
	}
}
