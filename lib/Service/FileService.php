<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
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

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\TCPDILibresign;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCP\Accounts\IAccountManager;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class FileService {
	use TFile;

	/** @var bool */
	private $showSigners = false;
	/** @var bool */
	private $showSettings = false;
	/** @var bool */
	private $showPages = false;
	/** @var bool */
	private $showVisibleElements = false;
	/** @var bool */
	private $showMessages = false;
	/** @var File|null */
	private $file;
	private ?SignRequest $signRequest = null;
	/** @var IUser|null */
	private $me;
	/** @var array */
	private $signers = [];
	/** @var array */
	private $settings = [
		'canSign' => false,
		'canRequestSign' => false,
		'hasSignatureFile' => false,
		'signerFileUuid' => null,
		'phoneNumber' => '',
	];
	public const IDENTIFICATION_DOCUMENTS_DISABLED = 0;
	public const IDENTIFICATION_DOCUMENTS_NEED_SEND = 1;
	public const IDENTIFICATION_DOCUMENTS_NEED_APPROVAL = 2;
	public const IDENTIFICATION_DOCUMENTS_APPROVED = 3;
	public function __construct(
		protected FileMapper $fileMapper,
		protected SignRequestMapper $signRequestMapper,
		protected FileElementMapper $fileElementMapper,
		protected FileElementService $fileElementService,
		protected FolderService $folderService,
		protected ValidateHelper $validateHelper,
		private AccountService $accountService,
		private IdentifyMethodService $identifyMethodService,
		private IUserSession $userSession,
		private IUserManager $userManager,
		private IAccountManager $accountManager,
		protected IClientService $client,
		private IConfig $config,
		private IRootFolder $rootFolder,
		private IURLGenerator $urlGenerator,
		protected IMimeTypeDetector $mimeTypeDetector,
		protected LoggerInterface $logger,
		protected IL10N $l10n
	) {
	}

	/**
	 * @return static
	 */
	public function showSigners(bool $show = true): self {
		$this->showSigners = $show;
		return $this;
	}

	/**
	 * @return static
	 */
	public function showSettings(bool $show = true): self {
		$this->showSettings = $show;
		return $this;
	}

	/**
	 * @return static
	 */
	public function showPages(bool $show = true): self {
		$this->showPages = $show;
		return $this;
	}

	/**
	 * @return static
	 */
	public function showVisibleElements(bool $show = true): self {
		$this->showVisibleElements = $show;
		return $this;
	}

	/**
	 * @return static
	 */
	public function showMessages(bool $show = true): self {
		$this->showMessages = $show;
		return $this;
	}

	/**
	 * @return static
	 */
	public function setMe(?IUser $user): self {
		$this->me = $user;
		return $this;
	}

	/**
	 * @return static
	 */
	public function setFile(File $file): self {
		$this->file = $file;
		return $this;
	}

	public function setSignRequest(SignRequest $signRequest): self {
		$this->signRequest = $signRequest;
		return $this;
	}

	/**
	 * @return static
	 */
	public function setFileByType(string $type, $identifier): self {
		try {
			/** @var File */
			$file = call_user_func(
				[$this->fileMapper, 'getBy' . $type],
				$identifier
			);
		} catch (\Throwable $th) {
			throw new LibresignException($this->l10n->t('Invalid data to validate file'), 404);
		}
		if (!$file) {
			throw new LibresignException($this->l10n->t('Invalid file identifier'), 404);
		}
		$this->setFile($file);
		return $this;
	}

	private function getSigners(): array {
		if (!$this->file) {
			return $this->signers;
		}
		$signers = $this->signRequestMapper->getByFileId($this->file->getId());
		foreach ($signers as $signer) {
			$signatureToShow = [
				'signed' => $signer->getSigned(),
				'displayName' => $signer->getDisplayName(),
				'fullName' => $signer->getFullName(),
				'me' => false,
				'signRequestId' => $signer->getId(),
				'identifyMethods' => $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signer->getId()),
			];
			// @todo refactor this code
			if ($this->me) {
				$identifyMethodServices = $signatureToShow['identifyMethods'];
				// Identifi if I'm file owner
				if ($this->me?->getUID() === $this->file->getUserId()) {
					$email = array_reduce($identifyMethodServices[IdentifyMethodService::IDENTIFY_EMAIL] ?? [], function (?string $carry, IIdentifyMethod $identifyMethod): ?string {
						if ($identifyMethod->getEntity()->getIdentifierKey() === IdentifyMethodService::IDENTIFY_EMAIL) {
							$carry = $identifyMethod->getEntity()->getIdentifierValue();
						}
						return $carry;
					});
					$signatureToShow['email'] = $email;
					$user = $this->userManager->getByEmail($email);
					if ($user && count($user) === 1) {
						$signatureToShow['uid'] = $user[0]->getUID();
					}
				}
				// Identify if I'm signer
				foreach ($identifyMethodServices as $methods) {
					foreach ($methods as $identifyMethod) {
						$entity = $identifyMethod->getEntity();
						$signatureToShow['me'] =
							$this->me->getEMailAddress() === $entity->getIdentifierValue()
							|| $this->me->getUID() === $entity->getIdentifierValue();
						if ($signatureToShow['me']) {
							if (!$signer->getSigned()) {
								$this->settings['canSign'] = true;
								$this->settings['signerFileUuid'] = $signer->getUuid();
							}
						}
					}
				}
			}
			$signatureToShow['identifyMethods'] = array_reduce($signatureToShow['identifyMethods'], function ($carry, $list) {
				foreach ($list as $identifyMethod) {
					$carry[] = [
						'method' => $identifyMethod->getEntity()->getIdentifierKey(),
						'value' => $identifyMethod->getEntity()->getIdentifierValue(),
					];
				}
				return $carry;
			}, []);
			$this->signers[] = $signatureToShow;
		}
		return $this->signers;
	}

	/**
	 * @return (mixed|string)[][]
	 *
	 * @psalm-return list<array{url: string, resolution: mixed}>
	 */
	private function getPages(): array {
		$return = [];

		$metadata = json_decode($this->file->getMetadata());
		for ($page = 1; $page <= $metadata->p; $page++) {
			$return[] = [
				'url' => $this->urlGenerator->linkToRoute('ocs.libresign.File.getPage', [
					'apiVersion' => 'v1',
					'uuid' => $this->file->getUuid(),
					'page' => $page,
				]),
				'resolution' => $metadata->d[$page - 1]
			];
		}
		return $return;
	}

	/**
	 * @psalm-return list<array{elementId: int, signRequestId: int, type: string, coordinates: array{page: int, urx: int, ury: int, llx: int, lly: int}, uid?: string, email?: string}>
	 */
	private function getVisibleElements(): array {
		$return = [];
		try {
			if ($this->me) {
				$uid = $this->me->getUID();
			}
			if (is_object($this->signRequest)) {
				$visibleElements = $this->fileElementMapper->getByFileIdAndSignRequestId($this->file->getId(), $this->signRequest->getId());
			} else {
				$visibleElements = $this->fileElementMapper->getByFileId($this->file->getId());
			}
			foreach ($visibleElements as $visibleElement) {
				$element = [
					'elementId' => $visibleElement->getId(),
					'signRequestId' => $visibleElement->getSignRequestId(),
					'type' => $visibleElement->getType(),
					'coordinates' => [
						'page' => $visibleElement->getPage(),
						'urx' => $visibleElement->getUrx(),
						'ury' => $visibleElement->getUry(),
						'llx' => $visibleElement->getLlx(),
						'lly' => $visibleElement->getLly()
					]
				];
				if (!empty($uid) && $uid === $this->file->getUserId()) {
					$signRequest = $this->signRequestMapper->getById($visibleElement->getSignRequestId());
					$userAssociatedToVisibleElement = $this->userManager->getByEmail($signRequest->getEmail());
					if ($userAssociatedToVisibleElement) {
						$element['uid'] = $userAssociatedToVisibleElement[0]->getUID();
					}
					$element['email'] = $signRequest->getEmail();
				}
				$element['coordinates'] = array_merge(
					$element['coordinates'],
					$this->fileElementService->translateCoordinatesFromInternalNotation($element, $this->file)
				);
				$return[] = $element;
			}
		} catch (\Throwable $th) {
		}
		return $return;
	}

	private function getPhoneNumber(): string {
		if (!$this->me) {
			return '';
		}
		$userAccount = $this->accountManager->getAccount($this->me);
		return $userAccount->getProperty(IAccountManager::PROPERTY_PHONE)->getValue();
	}

	private function getSettings(): array {
		if ($this->me) {
			$this->settings = array_merge($this->settings, $this->accountService->getSettings($this->me));
			$this->settings['phoneNumber'] = $this->getPhoneNumber($this->me);
			$status = $this->getIdentificationDocumentsStatus($this->me->getUID());
			if ($status === self::IDENTIFICATION_DOCUMENTS_NEED_SEND) {
				$this->settings['needIdentificationDocuments'] = true;
				$this->settings['identificationDocumentsWaitingApproval'] = false;
			} elseif ($status === self::IDENTIFICATION_DOCUMENTS_NEED_APPROVAL) {
				$this->settings['needIdentificationDocuments'] = true;
				$this->settings['identificationDocumentsWaitingApproval'] = true;
			}
		}
		return $this->settings;
	}

	public function getIdentificationDocumentsStatus(string $userId): int {
		if (!$this->config->getAppValue(Application::APP_ID, 'identification_documents', '')) {
			return self::IDENTIFICATION_DOCUMENTS_DISABLED;
		}

		$files = $this->fileMapper->getFilesOfAccount($userId);
		if (!count($files)) {
			return self::IDENTIFICATION_DOCUMENTS_NEED_SEND;
		}
		$deleted = array_filter($files, function (File $file) {
			return $file->getStatus() === File::STATUS_DELETED;
		});
		if (count($deleted) === count($files)) {
			return self::IDENTIFICATION_DOCUMENTS_NEED_SEND;
		}

		$signed = array_filter($files, function (File $file) {
			return $file->getStatus() === File::STATUS_SIGNED;
		});
		if (count($signed) !== count($files)) {
			return self::IDENTIFICATION_DOCUMENTS_NEED_APPROVAL;
		}

		return self::IDENTIFICATION_DOCUMENTS_APPROVED;
	}

	private function getFile(): array {
		$return = [];
		if (!$this->file) {
			return $return;
		}
		$return['status'] = $this->file->getStatus();
		$return['statusText'] = $this->fileMapper->getTextOfStatus($this->file->getStatus());
		$return['fileId'] = $this->file->getNodeId();
		$return['uuid'] = $this->file->getUuid();
		$return['name'] = $this->file->getName();
		$return['file'] = $this->urlGenerator->linkToRoute('libresign.page.getPdf', ['uuid' => $this->file->getUuid()]);

		$return['requested_by'] = [
			'uid' => $this->file->getUserId(),
			'displayName' => $this->userManager->get($this->file->getUserId())->getDisplayName(),
		];
		$return['request_date'] = (new \DateTime())
			->setTimestamp($this->file->getCreatedAt())
			->format('Y-m-d H:i:s');
		if ($this->showSigners) {
			$return['signers'] = $this->getSigners();
		}
		if ($this->showPages) {
			$return['pages'] = $this->getPages();
		}
		if ($this->showVisibleElements) {
			$visibleElements = $this->getVisibleElements();
			if ($visibleElements) {
				$return['visibleElements'] = $visibleElements;
			}
		}
		return $return;
	}

	/**
	 * @return string[][]
	 *
	 * @psalm-return list<array{type: 'info', message: string}>
	 */
	private function getMessages(): array {
		$messages = [];
		if ($this->settings['canSign']) {
			$messages[] = [
				'type' => 'info',
				'message' => $this->l10n->t('You need to sign this document')
			];
		}
		if (!$this->settings['canRequestSign'] && empty($this->signers)) {
			$messages[] = [
				'type' => 'info',
				'message' => $this->l10n->t('You cannot request signature for this document, please contact your administrator')
			];
		}
		return $messages;
	}

	/**
	 * @return ((mixed|string[])[]|int|mixed|string)[]
	 *
	 * @psalm-return array{status: int, statusText: mixed, fileId: int, uuid: int, name: string, file: string, signers?: array, pages?: array, visibleElements?: array, settings?: array, messages?: non-empty-list<array{type: 'info', message: string}>}
	 */
	public function formatFile(): array {
		$return = $this->getFile();
		if ($this->showSettings) {
			$return['settings'] = $this->getSettings();
		}
		if ($this->showMessages) {
			$messages = $this->getMessages();
			if ($messages) {
				$return['messages'] = $messages;
			}
		}
		return $return;
	}

	public function getPage(string $uuid, int $page, string $uid): string {
		$canPreviewAsImage = (bool) $this->config->getAppValue(Application::APP_ID, 'page_preview_as_image', '0');
		if (!$canPreviewAsImage) {
			throw new LibresignException($this->l10n->t('Page not found.'));
		}
		$libreSignFile = $this->fileMapper->getByUuid($uuid);
		$uid = $this->userSession->getUser()->getUID();
		if ($libreSignFile->getUserId() !== $uid) {
			$signers = $this->signRequestMapper->getByFileId($libreSignFile->id);
			if (!$signers) {
				throw new LibresignException($this->l10n->t('No signers.'));
			}
			$iNeedSign = false;
			foreach ($signers as $signer) {
				if ($signer->getUserId() === $uid) {
					$iNeedSign = true;
					break;
				}
			}
			if (!$iNeedSign) {
				throw new LibresignException($this->l10n->t('You must not sign this file.'));
			}
		}
		$userFolder = $this->rootFolder->getUserFolder($libreSignFile->getUserId());
		$file = $userFolder->getById($libreSignFile->getNodeId());
		$pdf = new TCPDILibresign();
		$pageCount = $pdf->setSourceData($file[0]->getContent());
		if ($page > $pageCount || $page < 1) {
			throw new LibresignException($this->l10n->t('Page not found.'));
		}
		$templateId = $pdf->importPage($page);
		$pdf->AddPage();
		$pdf->useTemplate($templateId);
		$blob = $pdf->Output('', 'S');
		$imagick = new \Imagick();
		$imagick->setResolution(100, 100);
		$imagick->readImageBlob($blob);
		$imagick->setImageFormat('png');
		return $imagick->getImageBlob();
	}

	public function setFileByPath(string $path): self {
		$node = $this->folderService->getFileByPath($path);
		$this->setFileByType('FileId', $node->getId());
		return $this;
	}

	/**
	 * @return array[]
	 *
	 * @psalm-return array{data: array, pagination: array}
	 */
	public function listAssociatedFilesOfSignFlow(IUser $user, $page = null, $length = null): array {
		$page = $page ?? 1;
		$length = $length ?? $this->config->getAppValue(Application::APP_ID, 'length_of_page', 100);

		$url = $this->urlGenerator->linkToRoute('libresign.page.getPdfUser', ['uuid' => '_replace_']);
		$url = str_replace('_replace_', '', $url);

		$data = $this->signRequestMapper->getFilesAssociatedFilesWithMeFormatted(
			$user,
			$url,
			$page,
			$length
		);
		$data['pagination']->setRootPath('/file/list');
		return [
			'data' => $data['data'],
			'pagination' => $data['pagination']->getPagination($page, $length)
		];
	}
}
