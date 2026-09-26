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
use OCA\Libresign\Service\Validation\FileInputValidator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\Http\Client\IClientService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use Sabre\DAV\UUIDUtil;

/**
 * @psalm-import-type LibresignUserElement from ResponseDefinitions
 */
class SignerElementsService {
	public const RENDER_MODE_DESCRIPTION_ONLY = 'DESCRIPTION_ONLY';
	public const RENDER_MODE_SIGNAME_AND_DESCRIPTION = 'SIGNAME_AND_DESCRIPTION';
	public const RENDER_MODE_GRAPHIC_AND_DESCRIPTION = 'GRAPHIC_AND_DESCRIPTION';
	public const RENDER_MODE_GRAPHIC_ONLY = 'GRAPHIC_ONLY';
	public const RENDER_MODE_DEFAULT = 'GRAPHIC_AND_DESCRIPTION';
	public function __construct(
		private FolderService $folderService,
		private SessionService $sessionService,
		private IURLGenerator $urlGenerator,
		private UserElementMapper $userElementMapper,
		private SignatureBackgroundService $signatureBackgroundService,
		private SignatureTextService $signatureTextService,
		private FileInputValidator $fileInputValidator,
		private IClientService $clientService,
		private ITimeFactory $timeFactory,
		private IL10N $l10n,
	) {
	}

	/**
	 * @return LibresignUserElement
	 */
	public function getUserElementByNodeId(string $userId, int $nodeId): array {
		$element = $this->userElementMapper->findOne(['node_id' => $nodeId, 'user_id' => $userId]);
		$exists = $this->signatureFileExists($element);
		if (!$exists) {
			throw new NotFoundException();
		}
		return [
			'id' => $element->getId(),
			'type' => $element->getType(),
			'file' => [
				'url' => $this->urlGenerator->linkToRoute('ocs.libresign.SignatureElements.previewSignatureElement', [
					'apiVersion' => 'v1',
					'nodeId' => $element->getNodeId(),
				]),
				'nodeId' => $element->getNodeId()
			],
			'userId' => $element->getUserId(),
			'starred' => (bool)$element->getStarred(),
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
					'url' => $this->urlGenerator->linkToRoute('ocs.libresign.SignatureElements.previewSignatureElement', [
						'apiVersion' => 'v1',
						'nodeId' => $element->getNodeId(),
					]),
					'nodeId' => $element->getNodeId()
				],
				'starred' => (bool)$element->getStarred(),
				'userId' => $element->getUserId(),
				'createdAt' => $element->getCreatedAt()->format('Y-m-d H:i:s'),
			];
		}
		return $return;
	}

	private function signatureFileExists(UserElement $userElement): bool {
		try {
			$this->folderService->getFileByNodeId($userElement->getNodeId());
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
					'url' => $this->urlGenerator->linkToRoute('ocs.libresign.SignatureElements.previewSignatureElement', [
						'apiVersion' => 'v1',
						'nodeId' => $fileElement->getId(),
						'mtime' => $fileElement->getMTime(),
					]),
					'nodeId' => $fileElement->getId(),
				],
				'starred' => false,
				'createdAt' => (new \DateTime())->setTimestamp((int)$timestamp)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
			];
		}
		return $return;
	}

	public function saveVisibleElements(array $elements, string $sessionId, ?IUser $user): void {
		foreach ($elements as $element) {
			$this->saveVisibleElement($element, $sessionId, $user);
		}
	}

	public function saveVisibleElement(array $data, string $sessionId, ?IUser $user): void {
		if (isset($data['elementId'])) {
			$this->updateFileOfVisibleElement($data);
			$this->updateDataOfVisibleElement($data);
		} elseif ($user instanceof IUser) {
			$file = $this->saveFileOfVisibleElementUsingUser($data, $user);
			$this->insertVisibleElement($data, $user, $file);
		} else {
			$file = $this->saveFileOfVisibleElementUsingSession($data, $sessionId);
		}
	}

	private function updateFileOfVisibleElement(array $data): void {
		if (!isset($data['file'])) {
			return;
		}
		$userElement = $this->userElementMapper->findOne(['id' => $data['elementId']]);
		$file = $this->folderService->getFileByNodeId($userElement->getNodeId());
		$file->putContent($this->getFileRaw($data));
	}

	private function updateDataOfVisibleElement(array $data): void {
		if (!isset($data['starred'])) {
			return;
		}
		$userElement = $this->userElementMapper->findOne(['id' => $data['elementId']]);
		$userElement->setStarred($data['starred'] ? 1 : 0);
		$this->userElementMapper->update($userElement);
	}

	private function saveFileOfVisibleElementUsingUser(array $data, IUser $user): File {
		$rootSignatureFolder = $this->folderService->getFolder();
		$folderName = $this->folderService->getFolderName($data, $user);
		$folderToFile = $rootSignatureFolder->newFolder($folderName);
		return $folderToFile->newFile(UUIDUtil::getUUID() . '.png', $this->getFileRaw($data));
	}

	private function saveFileOfVisibleElementUsingSession(array $data, string $sessionId): File {
		if (!empty($data['nodeId'])) {
			return $this->updateFileOfVisibleElementUsingSession($data, $sessionId);
		}
		return $this->createFileOfVisibleElementUsingSession($data, $sessionId);
	}

	private function updateFileOfVisibleElementUsingSession(array $data, string $sessionId): File {
		$fileList = $this->getElementsFromSession();
		$element = array_filter($fileList, fn (File $element) => $element->getId() === $data['nodeId']);
		$element = current($element);
		if (!$element instanceof File) {
			// TRANSLATORS Error when loading a LibreSign file for the account flow and the file cannot be found.
			throw new \Exception($this->l10n->t('File not found'));
		}
		$element->putContent($this->getFileRaw($data));
		return $element;
	}

	private function createFileOfVisibleElementUsingSession(array $data, string $sessionId): File {
		$rootSignatureFolder = $this->folderService->getFolder();
		$folderName = $sessionId;
		$folderToFile = $rootSignatureFolder->newFolder($folderName);
		$filename = implode(
			'_',
			[
				$data['type'],
				$this->timeFactory->getDateTime()->getTimestamp(),
			]
		) . '.png';
		return $folderToFile->newFile($filename, $this->getFileRaw($data));
	}

	private function insertVisibleElement(array $data, IUser $user, File $file): void {
		$userElement = new UserElement();
		$userElement->setType($data['type']);
		$userElement->setNodeId($file->getId());
		$userElement->setUserId($user->getUID());
		$userElement->setStarred(isset($data['starred']) && $data['starred'] ? 1 : 0);
		$userElement->setCreatedAt($this->timeFactory->getDateTime());
		$this->userElementMapper->insert($userElement);
	}

	private function getFileRaw(array $data): string {
		if (!empty($data['file']['url'])) {
			if (!filter_var($data['file']['url'], FILTER_VALIDATE_URL)) {
				// TRANSLATORS Error when a visible signature element is provided via URL and the URL is invalid.
				throw new \Exception($this->l10n->t('Invalid URL file'));
			}
			$response = $this->clientService->newClient()->get($data['file']['url']);
			$contentType = $response->getHeader('Content-Type');
			if ($contentType !== 'image/png') {
				// TRANSLATORS Error when uploading a visible signature or initials image that is not a PNG file.
				throw new \Exception($this->l10n->t('Visible element file must be png.'));
			}
			$content = (string)$response->getBody();
			if (empty($content)) {
				// TRANSLATORS Error when uploading a visible signature element file that is empty.
				throw new \Exception($this->l10n->t('Empty file'));
			}
			$this->fileInputValidator->validateBase64($content, FileInputValidator::TYPE_VISIBLE_ELEMENT_USER);
			return $content;
		}
		$this->fileInputValidator->validateBase64($data['file']['base64'], FileInputValidator::TYPE_VISIBLE_ELEMENT_USER);
		$withMime = explode(',', (string)$data['file']['base64']);
		if (count($withMime) === 2) {
			$content = base64_decode($withMime[1]);
		} else {
			$content = base64_decode((string)$data['file']['base64']);
		}
		if (!$content) {
			return '';
		}
		return $content;
	}

	public function deleteSignatureElement(?IUser $user, string $sessionId, int $nodeId): void {
		if ($user instanceof IUser) {
			$element = $this->userElementMapper->findOne([
				'node_id' => $nodeId,
				'user_id' => $user->getUID(),
			]);
			$this->userElementMapper->delete($element);
			try {
				$file = $this->folderService->getFileByNodeId($element->getNodeId());
				$file->delete();
			} catch (\Throwable) {
			}
			return;
		}

		$this->deleteSignatureElementFromSession($sessionId, $nodeId);
	}

	private function deleteSignatureElementFromSession(string $sessionId, int $nodeId): void {
		$rootSignatureFolder = $this->folderService->getFolder();
		try {
			/** @var \OCP\Files\Folder $sessionFolder */
			$sessionFolder = $rootSignatureFolder->get($sessionId);
		} catch (NotFoundException) {
			// TRANSLATORS Error when a visible signature element linked to the user account cannot be found.
			throw new DoesNotExistException($this->l10n->t('Element not found'));
		}

		$element = $sessionFolder->getFirstNodeById($nodeId);
		if (!$element instanceof File) {
			// TRANSLATORS Error when a visible signature element linked to the user account cannot be found.
			throw new DoesNotExistException($this->l10n->t('Element not found'));
		}
		$element->delete();

		// Clean up empty session folder
		if (count($sessionFolder->getDirectoryListing()) === 0) {
			$sessionFolder->delete();
		}
	}

	public function isSignElementsAvailable(): bool {
		return $this->signatureBackgroundService->isEnabled()
			|| $this->signatureTextService->isEnabled()
			|| $this->signatureTextService->getRenderMode() !== self::RENDER_MODE_DESCRIPTION_ONLY;
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
