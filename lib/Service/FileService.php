<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Http\Client\IClientService;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type LibresignValidateFile from ResponseDefinitions
 */
class FileService {
	use TFile;

	/** @var bool */
	private $showSigners = false;
	/** @var bool */
	private $showSettings = false;
	/** @var bool */
	private $showVisibleElements = false;
	/** @var bool */
	private $showMessages = false;
	/** @var File|null */
	private $file;
	private ?SignRequest $signRequest = null;
	/** @var IUser|null */
	private $me;
	private ?int $identifyMethodId = null;
	/** @var array */
	private $signers = [];
	/** @var array */
	private $settings = [
		'canSign' => false,
		'canRequestSign' => false,
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
		private IDateTimeFormatter $dateTimeFormatter,
		private IAppConfig $appConfig,
		private IRootFolder $rootFolder,
		private IURLGenerator $urlGenerator,
		protected IMimeTypeDetector $mimeTypeDetector,
		protected LoggerInterface $logger,
		protected IL10N $l10n,
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

	public function setIdentifyMethodId(?int $id): self {
		$this->identifyMethodId = $id;
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
		if ($this->signers) {
			return $this->signers;
		}
		$signers = $this->signRequestMapper->getByFileId($this->file->getId());
		foreach ($signers as $signer) {
			$signatureToShow = [
				'signed' => $signer->getSigned() ?
					$this->dateTimeFormatter->formatDateTime($signer->getSigned())
					: null,
				'displayName' => $signer->getDisplayName(),
				'me' => false,
				'signRequestId' => $signer->getId(),
				'description' => $signer->getDescription(),
				'identifyMethods' => $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signer->getId()),
				'visibleElements' => $this->getVisibleElements($signer->getId()),
				'request_sign_date' => (new \DateTime())
					->setTimestamp($signer->getCreatedAt())
					->format('Y-m-d H:i:s'),
			];
			if ($signer->getSigned()) {
				$data['sign_date'] = (new \DateTime())
					->setTimestamp($signer->getSigned())
					->format('Y-m-d H:i:s');
			}
			// @todo refactor this code
			if ($this->me || $this->identifyMethodId) {
				$signatureToShow['sign_uuid'] = $signer->getUuid();
				$identifyMethodServices = $signatureToShow['identifyMethods'];
				// Identifi if I'm file owner
				if ($this->me?->getUID() === $this->file->getUserId()) {
					$email = array_reduce($identifyMethodServices[IdentifyMethodService::IDENTIFY_EMAIL] ?? [], function (?string $carry, IIdentifyMethod $identifyMethod): ?string {
						if ($identifyMethod->getEntity()->getIdentifierKey() === IdentifyMethodService::IDENTIFY_EMAIL) {
							$carry = $identifyMethod->getEntity()->getIdentifierValue();
						}
						return $carry;
					}, '');
					$signatureToShow['email'] = $email;
					$user = $this->userManager->getByEmail($email);
					if ($user && count($user) === 1) {
						$signatureToShow['userId'] = $user[0]->getUID();
					}
				}
				// Identify if I'm signer
				foreach ($identifyMethodServices as $methods) {
					foreach ($methods as $identifyMethod) {
						$entity = $identifyMethod->getEntity();
						if ($this->identifyMethodId === $entity->getId()
							|| $this->me?->getUID() === $entity->getIdentifierValue()
							|| $this->me?->getEMailAddress() === $entity->getIdentifierValue()
						) {
							$signatureToShow['me'] = true;
							if (!$signer->getSigned()) {
								$this->settings['canSign'] = true;
								$this->settings['signerFileUuid'] = $signer->getUuid();
							}
						}
					}
				}
			}
			if ($signatureToShow['me'] && !is_null($this->signRequest)) {
				$signatureToShow['signatureMethods'] = $this->identifyMethodService->getSignMethodsOfIdentifiedFactors($this->signRequest->getId());
			}
			$signatureToShow['identifyMethods'] = array_reduce($signatureToShow['identifyMethods'], function ($carry, $list) {
				foreach ($list as $identifyMethod) {
					$carry[] = [
						'method' => $identifyMethod->getEntity()->getIdentifierKey(),
						'value' => $identifyMethod->getEntity()->getIdentifierValue(),
						'mandatory' => $identifyMethod->getEntity()->getMandatory(),
					];
				}
				return $carry;
			}, []);
			ksort($signatureToShow);
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

		$metadata = $this->file->getMetadata();
		for ($page = 1; $page <= $metadata['p']; $page++) {
			$return[] = [
				'url' => $this->urlGenerator->linkToRoute('ocs.libresign.File.getPage', [
					'apiVersion' => 'v1',
					'uuid' => $this->file->getUuid(),
					'page' => $page,
				]),
				'resolution' => $metadata['d'][$page - 1]
			];
		}
		return $return;
	}

	private function getVisibleElements(int $signRequestId): array {
		$return = [];
		if (!$this->showVisibleElements) {
			return $return;
		}
		try {
			$visibleElements = $this->fileElementMapper->getByFileIdAndSignRequestId($this->file->getId(), $signRequestId);
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
			$this->settings['phoneNumber'] = $this->getPhoneNumber();
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

	public function getIdentificationDocumentsStatus(?string $userId): int {
		if (!$this->appConfig->getAppValue('identification_documents', '')) {
			return self::IDENTIFICATION_DOCUMENTS_DISABLED;
		}

		if (!empty($userId)) {
			$files = $this->fileMapper->getFilesOfAccount($userId);
		}
		if (empty($files) || !count($files)) {
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
		$return['uuid'] = $this->file->getUuid();
		$return['name'] = $this->file->getName();
		$return['status'] = $this->file->getStatus();
		$return['request_date'] = (new \DateTime())
			->setTimestamp($this->file->getCreatedAt())
			->format('Y-m-d H:i:s');
		$return['statusText'] = $this->fileMapper->getTextOfStatus($this->file->getStatus());
		$return['nodeId'] = $this->file->getNodeId();

		$return['requested_by'] = [
			'userId' => $this->file->getUserId(),
			'displayName' => $this->userManager->get($this->file->getUserId())->getDisplayName(),
		];
		$return['file'] = $this->urlGenerator->linkToRoute('libresign.page.getPdf', ['uuid' => $this->file->getUuid()]);
		foreach ($this->getSigners() as $signer) {
			if ($signer['me']) {
				$return['url'] = $this->urlGenerator->linkToRoute('libresign.page.getPdfFile', ['uuid' => $signer['sign_uuid']]);
				break;
			}
		}
		if ($this->showSigners) {
			$return['signers'] = $this->getSigners();
		}
		ksort($return);
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
	 * @return LibresignValidateFile
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
	public function listAssociatedFilesOfSignFlow(
		$page = null,
		$length = null,
		array $filter = [],
		array $sort = [],
	): array {
		$page = $page ?? 1;
		$length = $length ?? (int)$this->appConfig->getAppValue('length_of_page', '100');

		$return = $this->signRequestMapper->getFilesAssociatedFilesWithMeFormatted(
			$this->me,
			$filter,
			$page,
			$length,
			$sort,
		);

		$signers = $this->signRequestMapper->getByMultipleFileId(array_column($return['data'], 'id'));
		$identifyMethods = $this->signRequestMapper->getIdentifyMethodsFromSigners($signers);
		$visibleElements = $this->signRequestMapper->getVisibleElementsFromSigners($signers);
		$return['data'] = $this->associateAllAndFormat($this->me, $return['data'], $signers, $identifyMethods, $visibleElements);

		$return['pagination']->setRouteName('ocs.libresign.File.list');
		return [
			'data' => $return['data'],
			'pagination' => $return['pagination']->getPagination($page, $length, $filter)
		];
	}

	private function associateAllAndFormat(IUser $user, array $files, array $signers, array $identifyMethods, array $visibleElements): array {
		foreach ($files as $key => $file) {
			$totalSigned = 0;
			foreach ($signers as $signerKey => $signer) {
				if ($signer->getFileId() === $file['id']) {
					/** @var array<IdentifyMethod> */
					$identifyMethodsOfSigner = $identifyMethods[$signer->getId()] ?? [];
					$data = [
						'email' => array_reduce($identifyMethodsOfSigner, function (string $carry, IdentifyMethod $identifyMethod): string {
							if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_EMAIL) {
								return $identifyMethod->getIdentifierValue();
							}
							return $carry;
						}, ''),
						'description' => $signer->getDescription(),
						'displayName' =>
							array_reduce($identifyMethodsOfSigner, function (string $carry, IdentifyMethod $identifyMethod): string {
								if (!$carry && $identifyMethod->getMandatory()) {
									return $identifyMethod->getIdentifierValue();
								}
								return $carry;
							}, $signer->getDisplayName()),
						'request_sign_date' => (new \DateTime())
							->setTimestamp($signer->getCreatedAt())
							->format('Y-m-d H:i:s'),
						'signed' => null,
						'signRequestId' => $signer->getId(),
						'me' => array_reduce($identifyMethodsOfSigner, function (bool $carry, IdentifyMethod $identifyMethod) use ($user): bool {
							if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_ACCOUNT) {
								if ($user->getUID() === $identifyMethod->getIdentifierValue()) {
									return true;
								}
							} elseif ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_EMAIL) {
								if (!$user->getEMailAddress()) {
									return false;
								}
								if ($user->getEMailAddress() === $identifyMethod->getIdentifierValue()) {
									return true;
								}
							}
							return $carry;
						}, false),
						'visibleElements' => array_map(function (FileElement $visibleElement) use ($file) {
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
							$metadata = json_decode($file['metadata'], true);
							$dimension = $metadata['d'][$element['coordinates']['page'] - 1];

							$element['coordinates']['left'] = $element['coordinates']['llx'];
							$element['coordinates']['height'] = abs($element['coordinates']['ury'] - $element['coordinates']['lly']);
							$element['coordinates']['top'] = $dimension['h'] - $element['coordinates']['ury'];
							$element['coordinates']['width'] = $element['coordinates']['urx'] - $element['coordinates']['llx'];

							return $element;
						}, $visibleElements[$signer->getId()] ?? []),
						'identifyMethods' => array_map(function (IdentifyMethod $identifyMethod) use ($signer): array {
							return [
								'method' => $identifyMethod->getIdentifierKey(),
								'value' => $identifyMethod->getIdentifierValue(),
								'mandatory' => $identifyMethod->getMandatory(),
							];
						}, array_values($identifyMethodsOfSigner)),
					];

					if ($data['me']) {
						$temp = array_map(function (IdentifyMethod $identifyMethodEntity) use ($signer): array {
							$this->identifyMethodService->setCurrentIdentifyMethod($identifyMethodEntity);
							$identifyMethod = $this->identifyMethodService->getInstanceOfIdentifyMethod(
								$identifyMethodEntity->getIdentifierKey(),
								$identifyMethodEntity->getIdentifierValue(),
							);
							$signatureMethods = $identifyMethod->getSignatureMethods();
							$return = [];
							foreach ($signatureMethods as $signatureMethod) {
								if (!$signatureMethod->isEnabled()) {
									continue;
								}
								$signatureMethod->setEntity($identifyMethod->getEntity());
								$return[$signatureMethod->getName()] = $signatureMethod->toArray();
							}
							return $return;
						}, array_values($identifyMethodsOfSigner));
						$data['signatureMethods'] = [];
						foreach ($temp as $methods) {
							$data['signatureMethods'] = array_merge($data['signatureMethods'], $methods);
						}
						$data['sign_uuid'] = $signer->getUuid();
						$files[$key]['url'] = $this->urlGenerator->linkToRoute('libresign.page.getPdfFile', ['uuid' => $signer->getuuid()]);
					}

					if ($signer->getSigned()) {
						$data['signed'] = $this->dateTimeFormatter->formatDateTime($signer->getSigned());
						$totalSigned++;
					}
					ksort($data);
					$files[$key]['signers'][] = $data;
					unset($signers[$signerKey]);
				}
			}
			if (empty($files[$key]['signers'])) {
				$files[$key]['signers'] = [];
				$files[$key]['statusText'] = $this->l10n->t('no signers');
			} else {
				$files[$key]['statusText'] = $this->fileMapper->getTextOfStatus((int)$files[$key]['status']);
			}
			unset($files[$key]['id']);
			ksort($files[$key]);
		}
		return $files;
	}

	public function getMyLibresignFile(int $nodeId): File {
		return $this->signRequestMapper->getMyLibresignFile(
			userId: $this->me->getUID(),
			filter: [
				'email' => $this->me->getEMailAddress(),
				'nodeId' => $nodeId,
			],
		);
	}

	public function delete(int $fileId): void {
		$file = $this->fileMapper->getByFileId($fileId);
		$this->fileElementService->deleteVisibleElements($file->getId());
		$list = $this->signRequestMapper->getByFileId($file->getId());
		foreach ($list as $signRequest) {
			$this->signRequestMapper->delete($signRequest);
		}
		$this->fileMapper->delete($file);
		if ($file->getSignedNodeId()) {
			$signedNextcloudFile = $this->folderService->getFileById($file->getSignedNodeId());
			$signedNextcloudFile->delete();
		}
		try {
			$nextcloudFile = $this->folderService->getFileById($fileId);
			$nextcloudFile->delete();
		} catch (NotFoundException $e) {
		}
	}
}
