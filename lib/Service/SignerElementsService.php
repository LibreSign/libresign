<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2024 Vitor Mattos <vitor@php.rio>
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

	/**
	 * @return ((int|string)[]|\DateTime|int|string)[]
	 *
	 * @psalm-return array{id?: int, type?: string, file?: array{url: string, fileId: int}, uid?: string, starred?: 0|1, createdAt?: \DateTime}
	 */
	public function getUserElementByElementId(string $userId, $elementId): array {
		$element = $this->userElementMapper->findOne(['id' => $elementId, 'user_id' => $userId]);
		$exists = $this->signatureFileExists($element);
		if (!$exists) {
			return [];
		}
		return [
			'id' => $element->getId(),
			'type' => $element->getType(),
			'file' => [
				'url' => $this->urlGenerator->linkToRoute('core.Preview.getPreviewByFileId', ['fileId' => $element->getFileId(), 'x' => self::ELEMENT_SIGN_WIDTH, 'y' => self::ELEMENT_SIGN_HEIGHT]),
				'fileId' => $element->getFileId()
			],
			'uid' => $element->getUserId(),
			'starred' => $element->getStarred() ? 1 : 0,
			'createdAt' => $element->getCreatedAt()
		];
	}

	/**
	 * @return ((int|string)[]|\DateTime|int|string)[][]
	 *
	 * @psalm-return list<array{id: int, type: string, file: array{url: string, fileId: int}, starred: 0|1, createdAt: \DateTime}>
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
					'url' => $this->urlGenerator->linkToRoute('core.Preview.getPreviewByFileId', ['fileId' => $element->getFileId(), 'x' => self::ELEMENT_SIGN_WIDTH, 'y' => self::ELEMENT_SIGN_HEIGHT]),
					'fileId' => $element->getFileId()
				],
				'starred' => $element->getStarred() ? 1 : 0,
				'createdAt' => $element->getCreatedAt()->format('Y-m-d H:i:s'),
			];
		}
		return $return;
	}

	private function signatureFileExists(UserElement $userElement): bool {
		try {
			$this->folderService->getFolder($userElement->getFileId());
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
			$return[] = [
				'type' => $type,
				'file' => [
					'url' => $this->urlGenerator->linkToRoute('ocs.libresign.account.getSignatureElementPreview', [
						'apiVersion' => 'v1',
						'fileId' => $fileElement->getId(),
					]),
					'fileId' => $fileElement->getId(),
				],
				'starred' => 0,
				'createdAt' => (new \DateTime())->setTimestamp((int) $timestamp)->format('Y-m-d H:i:s'),
			];
		}
		return $return;
	}
}
