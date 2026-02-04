<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreSign
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Dashboard;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCP\Dashboard\IAPIWidget;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IConditionalWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\Model\WidgetButton;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserSession;
use Override;

class PendingSignaturesWidget implements IAPIWidget, IAPIWidgetV2, IButtonWidget, IConditionalWidget, IIconWidget {
	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private SignRequestMapper $signRequestMapper,
		private IUserSession $userSession,
		private CertificateEngineFactory $certificateEngineFactory,
	) {
	}

	#[Override]
	public function getId(): string {
		return 'libresign_pending_signatures';
	}

	#[Override]
	public function getTitle(): string {
		return $this->l10n->t('Pending signatures');
	}

	#[Override]
	public function getOrder(): int {
		return 10;
	}

	#[Override]
	public function getIconClass(): string {
		return 'icon-libresign';
	}

	#[Override]
	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
		);
	}

	#[Override]
	public function getUrl(): ?string {
		return $this->urlGenerator->linkToRouteAbsolute('libresign.page.index');
	}

	#[Override]
	public function load(): void {
		// No special loading required
	}

	#[Override]
	public function isEnabled(): bool {
		return $this->certificateEngineFactory->getEngine()->isSetupOk();
	}

	#[Override]
	public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		$user = $this->userSession->getUser();
		if (!$user) {
			return new WidgetItems([], $this->l10n->t('User not found'));
		}

		$result = $this->signRequestMapper->getFilesAssociatedFilesWithMe(
			$user,
			['status' => [FileStatus::ABLE_TO_SIGN->value, FileStatus::PARTIAL_SIGNED->value]],
			1,
			$limit,
			['sortBy' => 'created_at', 'sortDirection' => 'desc']
		);

		$items = [];

		foreach ($result['data'] as $fileEntity) {
			$signRequests = $this->signRequestMapper->getByFileId($fileEntity->getId());

			foreach ($signRequests as $signRequest) {
				if ($signRequest->getSigned()) {
					continue;
				}

				$item = new WidgetItem(
					$fileEntity->getName(),
					$this->getSubtitle($signRequest, $fileEntity),
					$this->urlGenerator->linkToRouteAbsolute('libresign.page.signFPath', ['uuid' => $signRequest->getUuid(), 'path' => 'pdf']),
					$this->urlGenerator->getAbsoluteURL(
						$this->urlGenerator->imagePath('core', 'filetypes/application-pdf.svg')
					),
					$this->getTimestamp($fileEntity)
				);

				$items[] = $item;
			}
		}

		return new WidgetItems(
			$items,
			empty($items) ? $this->l10n->t('No pending signatures') : '',
		);
	}

	private function getSubtitle(\OCA\Libresign\Db\SignRequest $signRequest, \OCA\Libresign\Db\File $fileEntity): string {
		$displayName = $signRequest->getDisplayName();
		$createdAt = $fileEntity->getCreatedAt();
		if ($createdAt instanceof \DateTime) {
			$date = $createdAt->format('d/m/Y');
			// TRANSLATORS %s is the sender name, %s is the date
			return $this->l10n->t('From: %s • Date: %s', [$displayName, $date]);
		}
		// TRANSLATORS %s is the sender name
		return $this->l10n->t('From: %s', [$displayName]);
	}

	private function getTimestamp(\OCA\Libresign\Db\File $fileEntity): string {
		$createdAt = $fileEntity->getCreatedAt();
		if ($createdAt instanceof \DateTime) {
			return (string)$createdAt->getTimestamp();
		}
		return '';
	}

	#[Override]
	public function getItems(string $userId, ?string $since = null, int $limit = 7): array {
		$widgetItems = $this->getItemsV2($userId, $since, $limit);
		return $widgetItems->getItems();
	}

	#[Override]
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
