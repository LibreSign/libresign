<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreSign
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Dashboard;

use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\AppInfo\Application;
use OCP\Dashboard\IAPIWidget;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\Model\WidgetButton;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Util;

class PendingSignaturesWidget implements IAPIWidget, IAPIWidgetV2, IButtonWidget, IIconWidget {
	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private SignFileService $signFileService,
		private SignRequestMapper $signRequestMapper,
		private IUserSession $userSession,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'libresign_pending_signatures';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('Pending signatures');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int {
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconClass(): string {
		return 'icon-libresign';
	}

	/**
	 * @inheritDoc
	 */
	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getUrl(): ?string {
		return $this->urlGenerator->linkToRouteAbsolute('libresign.page.index');
	}

	/**
	 * @inheritDoc
	 */
	public function load(): void {

	}

	/**
	 * @inheritDoc
	 */
	public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new WidgetItems([], $this->l10n->t('User not found'));
			}

			$result = $this->signRequestMapper->getFilesAssociatedFilesWithMe(
				$user,
				['status' => [\OCA\Libresign\Db\File::STATUS_ABLE_TO_SIGN, \OCA\Libresign\Db\File::STATUS_PARTIAL_SIGNED]],
				1,
				$limit,
				['sortBy' => 'created_at', 'sortDirection' => 'desc']
			);

			$items = [];

			foreach ($result['data'] as $fileEntity) {
				try {
					$signRequest = $this->getSignRequestForUser($fileEntity, $user);

					if (!$signRequest || $signRequest->getSigned()) {
						continue;
					}

					$item = new WidgetItem(
						$this->getDocumentTitle($fileEntity),
						$this->getSubtitle($signRequest, $fileEntity),
						$this->urlGenerator->linkToRouteAbsolute('libresign.page.signFPath', ['uuid' => $signRequest->getUuid(), 'path' => 'pdf']),
						$this->urlGenerator->getAbsoluteURL(
							$this->urlGenerator->imagePath('core', 'filetypes/application-pdf.svg')
						),
						$this->getTimestamp($fileEntity)
					);

					$items[] = $item;
				} catch (\Exception $e) {
					continue;
				}
			}

			return new WidgetItems(
				$items,
				empty($items) ? $this->l10n->t('No pending signatures') : '',
			);
		} catch (\Exception $e) {
			return new WidgetItems(
				[],
				$this->l10n->t('Error loading pending signatures'),
			);
		}
	}

	private function getSignRequestForUser(\OCA\Libresign\Db\File $fileEntity, \OCP\IUser $user): ?\OCA\Libresign\Db\SignRequest {
		try {
			$signRequests = $this->signRequestMapper->getByFileId($fileEntity->getId());

			foreach ($signRequests as $signRequest) {
				if ($this->signRequestBelongsToUser($signRequest, $user)) {
					return $signRequest;
				}
			}
		} catch (\Exception $e) {
			return null;
		}

		return null;
	}

	private function signRequestBelongsToUser(\OCA\Libresign\Db\SignRequest $signRequest, \OCP\IUser $user): bool {
		try {
			$validSignRequest = $this->signFileService->getSignRequestToSign(
				$this->signFileService->getFile($signRequest->getFileId()),
				$signRequest->getUuid(),
				$user
			);

			return $validSignRequest->getId() === $signRequest->getId();
		} catch (\Exception $e) {
			return false;
		}
	}

	private function getDocumentTitle(\OCA\Libresign\Db\File $fileEntity): string {
		if ($fileEntity->getName()) {
			return $fileEntity->getName();
		}

		try {
			$files = $this->signFileService->getNextcloudFiles($fileEntity);
			if (!empty($files)) {
				$file = current($files);
				return $file->getName();
			}
		} catch (\Exception $e) {
		}

		return $this->l10n->t('Document');
	}

	private function getSubtitle(\OCA\Libresign\Db\SignRequest $signRequest, \OCA\Libresign\Db\File $fileEntity): string {
		$parts = [];

		$displayName = $signRequest->getDisplayName();
		if ($displayName) {
			$parts[] = $this->l10n->t('From: %s', [$displayName]);
		}

		$createdAt = $fileEntity->getCreatedAt();
		if ($createdAt instanceof \DateTime) {
			$date = $createdAt->format('d/m/Y');
			$parts[] = $this->l10n->t('Date: %s', [$date]);
		}

		return implode(' • ', $parts);
	}

	private function getTimestamp(\OCA\Libresign\Db\File $fileEntity): string {
		$createdAt = $fileEntity->getCreatedAt();
		if ($createdAt instanceof \DateTime) {
			return (string)$createdAt->getTimestamp();
		}
		return '';
	}

	/**
	 * @inheritDoc
	 * @deprecated Use getItemsV2 instead
	 */
	public function getItems(string $userId, ?string $since = null, int $limit = 7): array {
		$widgetItems = $this->getItemsV2($userId, $since, $limit);
		return $widgetItems->getItems();
	}

	/**
	 * @inheritDoc
	 */
	public function getWidgetButtons(string $userId): array {
		return [
			new WidgetButton(
				WidgetButton::TYPE_MORE,
				$this->urlGenerator->linkToRouteAbsolute('libresign.page.index'),
				$this->l10n->t('View all documents')
			),
		];
	}
}
