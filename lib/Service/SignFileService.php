<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use InvalidArgumentException;
use mikehaertl\pdftk\Command;
use OC\AppFramework\Http as AppFrameworkHttp;
use OCA\Libresign\DataObjects\VisibleElementAssoc;
use OCA\Libresign\Db\AccountFile;
use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Events\SignedEvent;
use OCA\Libresign\Exception\EmptyCertificateException;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Handler\PdfTk\Pdf;
use OCA\Libresign\Handler\Pkcs12Handler;
use OCA\Libresign\Handler\Pkcs7Handler;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\EmailToken;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotPermittedException;
use OCP\Http\Client\IClientService;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Sabre\DAV\UUIDUtil;
use TypeError;

class SignFileService {
	/** @var SignRequestEntity */
	private $signRequest;
	/** @var string */
	private $password;
	/** @var FileEntity */
	private $libreSignFile;
	/** @var VisibleElementAssoc[] */
	private $elements = [];
	/** @var bool */
	private $signWithoutPassword = false;
	private ?Node $fileToSign = null;
	private string $userUniqueIdentifier = '';
	private string $friendlyName = '';
	private array $signers = [];
	private ?IUser $user;

	public function __construct(
		protected IL10N $l10n,
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
		private AccountFileMapper $accountFileMapper,
		private Pkcs7Handler $pkcs7Handler,
		private Pkcs12Handler $pkcs12Handler,
		private FooterHandler $footerHandler,
		protected FolderService $folderService,
		private IClientService $client,
		private IUserManager $userManager,
		protected LoggerInterface $logger,
		private IAppConfig $appConfig,
		protected ValidateHelper $validateHelper,
		private SignerElementsService $signerElementsService,
		private IRootFolder $root,
		private IUserSession $userSession,
		private IUserMountCache $userMountCache,
		private FileElementMapper $fileElementMapper,
		private UserElementMapper $userElementMapper,
		private IEventDispatcher $eventDispatcher,
		private IURLGenerator $urlGenerator,
		private IdentifyMethodMapper $identifyMethodMapper,
		private ITempManager $tempManager,
		private IdentifyMethodService $identifyMethodService,
		private ITimeFactory $timeFactory,
	) {
	}

	/**
	 * Can delete sing request
	 */
	public function canDeleteRequestSignature(array $data): void {
		if (!empty($data['uuid'])) {
			$signatures = $this->signRequestMapper->getByFileUuid($data['uuid']);
		} elseif (!empty($data['file']['fileId'])) {
			$signatures = $this->signRequestMapper->getByNodeId($data['file']['fileId']);
		} else {
			throw new \Exception($this->l10n->t('Inform or UUID or a File object'));
		}
		$signed = array_filter($signatures, fn ($s) => $s->getSigned());
		if ($signed) {
			throw new \Exception($this->l10n->t('Document already signed'));
		}
		array_walk($data['users'], function ($user) use ($signatures) {
			$exists = array_filter($signatures, function (SignRequestEntity $signRequest) use ($user) {
				$identifyMethod = $this->identifyMethodService->getIdentifiedMethod($signRequest->getId());
				if ($identifyMethod->getName() === 'email') {
					return $identifyMethod->getEntity()->getIdentifierValue() === $user['email'];
				}
				return false;
			});
			if (!$exists) {
				throw new \Exception($this->l10n->t('No signature was requested to %s', $user['email']));
			}
		});
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 * @psalm-suppress MixedMethodCall
	 */
	public function notifyCallback(File $file): void {
		$uri = $this->libreSignFile->getCallback();
		if (!$uri) {
			$uri = $this->appConfig->getAppValue('webhook_sign_url');
			if (!$uri) {
				return;
			}
		}
		$options = [
			'multipart' => [
				[
					'name' => 'uuid',
					'contents' => $this->libreSignFile->getUuid(),
				],
				[
					'name' => 'status',
					'contents' => $this->libreSignFile->getStatus(),
				],
				[
					'name' => 'file',
					'contents' => $file->fopen('r'),
					'filename' => $file->getName()
				]
			]
		];
		$this->client->newClient()->post($uri, $options);
	}

	/**
	 * @return static
	 */
	public function setLibreSignFile(FileEntity $libreSignFile): self {
		$this->libreSignFile = $libreSignFile;
		return $this;
	}

	public function setUserUniqueIdentifier(string $identifier): self {
		$this->userUniqueIdentifier = $identifier;
		return $this;
	}

	public function setFriendlyName(string $friendlyName): self {
		$this->friendlyName = $friendlyName;
		return $this;
	}

	/**
	 * @return static
	 */
	public function setSignRequest(SignRequestEntity $signRequest): self {
		$this->signRequest = $signRequest;
		return $this;
	}

	/**
	 * @return static
	 */
	public function setSignWithoutPassword(bool $signWithoutPassword): self {
		$this->signWithoutPassword = $signWithoutPassword;
		return $this;
	}

	/**
	 * @return static
	 */
	public function setPassword(?string $password = null): self {
		$this->password = $password;
		return $this;
	}

	public function setCurrentUser(?IUser $user): self {
		$this->user = $user;
		return $this;
	}

	/**
	 * @return static
	 */
	public function setVisibleElements(array $list): self {
		$fileElements = $this->fileElementMapper->getByFileIdAndSignRequestId($this->signRequest->getFileId(), $this->signRequest->getId());
		foreach ($fileElements as $fileElement) {
			$element = array_filter($list, function (array $element) use ($fileElement): bool {
				return $element['documentElementId'] === $fileElement->getId();
			});
			if ($element) {
				$c = current($element);
				if (!empty($c['profileNodeId'])) {
					$nodeId = $c['profileNodeId'];
				} else {
					throw new LibresignException($this->l10n->t('Invalid data to sign file'), 1);
				}
			} else {
				$userElement = $this->userElementMapper->findOne([
					'user_id' => $this->user->getUID(),
					'type' => $fileElement->getType(),
				]);
				$nodeId = $userElement->getFileId();
			}
			try {
				if ($this->user instanceof IUser) {
					$node = $this->folderService->getFileById($nodeId);
				} else {
					$filesOfElementes = $this->signerElementsService->getElementsFromSession();
					$node = array_filter($filesOfElementes, fn ($file) => $file->getId() === $nodeId);
					$node = current($node);
				}
				if (!$node) {
					throw new \Exception('empty');
				}
			} catch (\Throwable $th) {
				throw new LibresignException($this->l10n->t('You need to define a visible signature or initials to sign this document.'));
			}
			$tempFile = $this->tempManager->getTemporaryFile('.png');
			file_put_contents($tempFile, $node->getContent());
			$visibleElements = new VisibleElementAssoc(
				$fileElement,
				$tempFile
			);
			$this->elements[] = $visibleElements;
		}
		return $this;
	}

	public function sign(): File {
		$fileToSign = $this->getFileToSing($this->libreSignFile);
		$pfxFileContent = $this->getPfxFile();
		switch (strtolower($fileToSign->getExtension())) {
			case 'pdf':
				$signedFile = $this->pkcs12Handler
					->setInputFile($fileToSign)
					->setCertificate($pfxFileContent)
					->setVisibleElements($this->elements)
					->setPassword($this->password)
					->sign();
				break;
			default:
				$signedFile = $this->pkcs7Handler
					->setInputFile($fileToSign)
					->setCertificate($pfxFileContent)
					->setPassword($this->password)
					->sign();
		}

		$this->signRequest->setSigned(time());
		if ($this->signRequest->getId()) {
			$this->signRequestMapper->update($this->signRequest);
		} else {
			$this->signRequestMapper->insert($this->signRequest);
		}

		$this->libreSignFile->setSignedNodeId($signedFile->getId());
		$allSigned = $this->updateStatus();
		$this->fileMapper->update($this->libreSignFile);

		$this->eventDispatcher->dispatchTyped(new SignedEvent($this, $signedFile, $allSigned));

		return $signedFile;
	}

	public function storeUserMetadata(array $metadata = []): self {
		$collectMetadata = $this->appConfig->getAppValue('collect_metadata') ? true : false;
		if (!$collectMetadata || !$metadata) {
			return $this;
		}
		$this->signRequest->setMetadata(array_merge(
			$metadata,
			$this->signRequest->getMetadata(),
		));
		$this->signRequestMapper->update($this->signRequest);
		return $this;
	}

	/**
	 * @return SignRequestEntity[]
	 */
	private function getSigners(): array {
		if (empty($this->signers)) {
			$this->signers = $this->signRequestMapper->getByFileId($this->signRequest->getFileId());
			if ($this->signers) {
				foreach ($this->signers as $key => $signer) {
					if ($signer->getId() === $this->signRequest->getId()) {
						$this->signers[$key] = $this->signRequest;
						break;
					}
				}
			}
		}
		return $this->signers;
	}

	private function updateStatus(): bool {
		$signers = $this->getSigners();
		$total = array_reduce($signers, function ($carry, $signer) {
			$carry += $signer->getSigned() ? 1 : 0;
			return $carry;
		}, 0);
		if ($total > 0
			&& count($signers) !== $total
			&& $this->libreSignFile->getStatus() !== FileEntity::STATUS_PARTIAL_SIGNED
		) {
			$this->libreSignFile->setStatus(FileEntity::STATUS_PARTIAL_SIGNED);
			return true;
		} elseif (count($signers) === $total
			&& $this->libreSignFile->getStatus() !== FileEntity::STATUS_SIGNED
		) {
			$this->libreSignFile->setStatus(FileEntity::STATUS_SIGNED);
			return true;
		}
		return false;
	}

	private function getPfxFile(): string {
		if ($this->signWithoutPassword) {
			$tempPassword = sha1((string)time());
			$this->setPassword($tempPassword);
			try {
				$certificate = $this->pkcs12Handler->generateCertificate(
					[
						'host' => $this->userUniqueIdentifier,
						'name' => $this->friendlyName,
					],
					$tempPassword,
					$this->friendlyName,
					true
				);
				$this->pkcs12Handler->setPfxContent($certificate);
			} catch (TypeError $e) {
				throw new LibresignException($this->l10n->t('Failure to generate certificate'));
			} catch (EmptyCertificateException $e) {
				throw new LibresignException($this->l10n->t('Empty root certificate data'));
			} catch (InvalidArgumentException $e) {
				throw new LibresignException($this->l10n->t('Invalid data to generate certificate'));
			} catch (\Throwable $th) {
				throw new LibresignException($this->l10n->t('Failure on generate certificate'));
			}
		}
		return $this->pkcs12Handler->getPfx();
	}

	/**
	 * Get file to sign
	 *
	 * @throws LibresignException
	 */
	public function getFileToSing(FileEntity $libresignFile): \OCP\Files\Node {
		$nodeId = $libresignFile->getNodeId();

		$mountsContainingFile = $this->userMountCache->getMountsForFileId($nodeId);
		foreach ($mountsContainingFile as $fileInfo) {
			$this->root->getByIdInPath($nodeId, $fileInfo->getMountPoint());
		}
		$originalFile = $this->root->getById($nodeId);
		if (count($originalFile) < 1) {
			throw new LibresignException($this->l10n->t('File not found'));
		}
		$originalFile = current($originalFile);
		if (strtolower($originalFile->getExtension()) === 'pdf') {
			return $this->getPdfToSign($libresignFile, $originalFile);
		}
		return $originalFile;
	}

	public function getLibresignFile(?int $nodeId, ?string $signRequestUuid = null): FileEntity {
		try {
			if ($nodeId) {
				$libresignFile = $this->fileMapper->getByFileId($nodeId);
			} elseif ($signRequestUuid) {
				$signRequest = $this->signRequestMapper->getByUuid($signRequestUuid);
				$libresignFile = $this->fileMapper->getById($signRequest->getFileId());
			} else {
				throw new \Exception('Invalid arguments');
			}
		} catch (DoesNotExistException $th) {
			throw new LibresignException($this->l10n->t('File not found'), 1);
		}
		return $libresignFile;
	}

	public function renew(SignRequestEntity $signRequest, string $method): void {
		$identifyMethods = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequest->getId());
		if (empty($identifyMethods[$method])) {
			throw new LibresignException($this->l10n->t('Invalid identification method'));
		}

		$signRequest->setUuid(UUIDUtil::getUUID());
		$this->signRequestMapper->update($signRequest);

		array_map(function (IIdentifyMethod $identifyMethod) {
			$entity = $identifyMethod->getEntity();
			$entity->setAttempts($entity->getAttempts() + 1);
			$entity->setLastAttemptDate($this->timeFactory->getDateTime());
			$identifyMethod->save();
		}, $identifyMethods[$method]);
	}

	public function requestCode(
		SignRequestEntity $signRequest,
		string $identifyMethodName,
		string $signMethodName,
		string $identify = '',
	): void {
		$identifyMethods = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequest->getId());
		if (empty($identifyMethods[$identifyMethodName])) {
			throw new LibresignException($this->l10n->t('Invalid identification method'));
		}
		foreach ($identifyMethods[$identifyMethodName] as $identifyMethod) {
			try {
				$signatureMethod = $identifyMethod->getEmptyInstanceOfSignatureMethodByName($signMethodName);
				$signatureMethod->setEntity($identifyMethod->getEntity());
			} catch (InvalidArgumentException $th) {
				continue;
			}
			/** @var EmailToken $signatureMethod */
			$signatureMethod->requestCode($identify);
			return;
		}
		throw new LibresignException($this->l10n->t('Sending authorization code not enabled.'));
	}

	public function getSignRequestToSign(FileEntity $libresignFile, ?string $signRequestUuid, ?IUser $user): SignRequestEntity {
		$this->validateHelper->fileCanBeSigned($libresignFile);
		try {
			$signRequests = $this->signRequestMapper->getByFileId($libresignFile->getId());

			if (!empty($signRequestUuid)) {
				$signRequest = $this->getSignRequest($signRequestUuid);
			} else {
				$signRequest = array_reduce($signRequests, function (?SignRequestEntity $carry, SignRequestEntity $signRequest) use ($user): ?SignRequestEntity {
					$identifyMethods = $this->identifyMethodMapper->getIdentifyMethodsFromSignRequestId($signRequest->getId());
					$found = array_filter($identifyMethods, function (IdentifyMethod $identifyMethod) use ($user) {
						if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_EMAIL
							&& (
								$identifyMethod->getIdentifierValue() === $user->getUID()
								|| $identifyMethod->getIdentifierValue() === $user->getEMailAddress()
							)
						) {
							return true;
						}
						if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_ACCOUNT
							&& $identifyMethod->getIdentifierValue() === $user->getUID()
						) {
							return true;
						}
						return false;
					});
					if (count($found) > 0) {
						return $signRequest;
					}
					return $carry;
				});
			}

			if (!$signRequest) {
				throw new DoesNotExistException('Sign request not found');
			}
			if ($signRequest->getSigned()) {
				throw new LibresignException($this->l10n->t('File already signed by you'), 1);
			}
		} catch (DoesNotExistException $th) {
			try {
				$accountFile = $this->accountFileMapper->getByFileId($libresignFile->getId());
			} catch (\Throwable $th) {
				throw new LibresignException($this->l10n->t('Invalid data to sign file'), 1);
			}
			$this->validateHelper->userCanApproveValidationDocuments($user);
			$signRequest = new SignRequestEntity();
			$signRequest->setFileId($libresignFile->getId());
			$signRequest->setDisplayName($user->getDisplayName());
			$signRequest->setUuid(UUIDUtil::getUUID());
			$signRequest->setCreatedAt(time());
		}
		return $signRequest;
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 * @psalm-suppress InvalidReturnStatement
	 * @psalm-suppress MixedMethodCall
	 */
	private function getPdfToSign(FileEntity $fileData, File $originalFile): File {
		if ($fileData->getSignedNodeId()) {
			$nodeId = $fileData->getSignedNodeId();

			$mountsContainingFile = $this->userMountCache->getMountsForFileId($nodeId);
			foreach ($mountsContainingFile as $fileInfo) {
				$this->root->getByIdInPath($nodeId, $fileInfo->getMountPoint());
			}
			$fileToSign = $this->root->getById($nodeId);
			/** @var \OCP\Files\File */
			$fileToSign = current($fileToSign);
		} else {
			$signedFilePath = preg_replace(
				'/' . $originalFile->getExtension() . '$/',
				$this->l10n->t('signed') . '.' . $originalFile->getExtension(),
				$originalFile->getPath()
			);

			$footer = $this->footerHandler
				->setTemplateVar('signers', array_map(function (SignRequestEntity $signer) {
					return [
						'displayName' => $signer->getDisplayName(),
						'signed' => $signer->getSigned(),
					];
				}, $this->getSigners()))
				->getFooter($originalFile, $fileData);
			if ($footer) {
				$stamp = $this->tempManager->getTemporaryFile('stamp.pdf');
				file_put_contents($stamp, $footer);

				$input = $this->tempManager->getTemporaryFile('input.pdf');
				file_put_contents($input, $originalFile->getContent());

				$javaPath = $this->appConfig->getAppValue('java_path');
				$pdftkPath = $this->appConfig->getAppValue('pdftk_path');
				if (!file_exists($javaPath) || !file_exists($pdftkPath)) {
					throw new LibresignException($this->l10n->t('The admin hasn\'t set up LibreSign yet, please wait.'));
				}
				$pdf = new Pdf();
				$command = new Command();
				$command->setCommand($javaPath . ' -jar ' . $pdftkPath);
				$pdf->setCommand($command);
				$pdf->addFile($input);
				$buffer = $pdf->multiStamp($stamp)
					->toString();
				if (!is_string($buffer)) {
					throw new LibresignException('Failed to merge the PDF with the footer. The PDF was not successfully created with the footer.');
				}
			} else {
				$buffer = $originalFile->getContent();
			}
			$fileToSign = $this->forceSaveFileOfDifferentUser($signedFilePath, $buffer);
		}
		return $fileToSign;
	}

	/**
	 * Problem: Nextcloud server disalowed to write a content into a file that isn't of authenticated user.
	 *
	 * Workaround: to prevent error when try to save a file in a folder of different authenticated user
	 *
	 * At the follow code:
	 * https://github.com/nextcloud/server/blob/4173dfe05bd0155eb217dd428ac82091a508567a/apps/files_versions/lib/Listener/FileEventsListener.php#L350-L366
	 * Nextcloud server force to use the user folder to get the file of authenticated user.
	 * This piece of code is to bypass the logic to use the authenticated user.
	 *
	 * How to reproduce: With account signer1, upload a file and request to signer 2 to sign
	 * Authenticated as signer2, try to sign the file
	 * Expected behavior: file signed
	 * Current behavior: Will get "internal error" because the code at previous link will return null as the path of file
	 *
	 * @todo Identify a way to be possible save the file content
	 */
	private function forceSaveFileOfDifferentUser(string $path, string $content): \OCP\Files\File {
		try {
			/** @var \OCP\Files\File */
			$fileToSign = $this->root->newFile($path);
		} catch (NotPermittedException $e) {
			throw new LibresignException($this->l10n->t('You do not have permission for this action.'));
		}
		$currentUser = $this->userSession->getUser();
		$this->userSession->setUser(null);
		try {
			$fileToSign->putContent($content);
		} catch (\Throwable $th) {
			$this->userSession->setUser($currentUser);
			throw $th;
		}
		return $fileToSign;
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getSignRequest(string $uuid): SignRequestEntity {
		$this->validateHelper->validateUuidFormat($uuid);
		return $this->signRequestMapper->getByUuid($uuid);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getFile(int $signRequestId): FileEntity {
		return $this->fileMapper->getById($signRequestId);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getFileByUuid(string $uuid): FileEntity {
		return $this->fileMapper->getByUuid($uuid);
	}

	public function getAccountFileById(int $fileId): AccountFile {
		return $this->accountFileMapper->getByFileId($fileId);
	}

	public function getNextcloudFile(int $nodeId): File {
		$mountsContainingFile = $this->userMountCache->getMountsForFileId($nodeId);
		foreach ($mountsContainingFile as $fileInfo) {
			$this->root->getByIdInPath($nodeId, $fileInfo->getMountPoint());
		}
		$fileToSign = $this->root->getById($nodeId);
		if (count($fileToSign) < 1) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$this->l10n->t('File not found')],
			]), AppFrameworkHttp::STATUS_NOT_FOUND);
		}
		/** @var File */
		$fileToSign = $fileToSign[0];
		return $fileToSign;
	}

	public function validateSigner(string $uuid, ?IUser $user = null): void {
		$this->validateHelper->validateSigner($uuid, $user);
	}

	public function validateRenewSigner(string $uuid, ?IUser $user = null): void {
		$this->validateHelper->validateRenewSigner($uuid, $user);
	}

	public function getSignerData(?IUser $user, ?SignRequestEntity $signRequest = null): array {
		$return = ['user' => ['name' => null]];
		if ($signRequest) {
			$return['user']['name'] = $signRequest->getDisplayName();
		} elseif ($user) {
			$return['user']['name'] = $user->getDisplayName();
		}
		return $return;
	}

	public function getAvailableIdentifyMethodsFromSettings(): array {
		$identifyMethods = $this->identifyMethodService->getIdentifyMethodsSettings();
		$return = array_map(function (array $identifyMethod): array {
			return [
				'mandatory' => $identifyMethod['mandatory'],
				'identifiedAtDate' => null,
				'validateCode' => false,
				'method' => $identifyMethod['name'],
			];
		}, $identifyMethods);
		return $return;
	}

	/**
	 * @psalm-return array{file?: File, nodeId?: int, url?: string, base64?: string}
	 */
	public function getFileUrl(string $format, FileEntity $fileEntity, File $fileToSign, string $uuid): array {
		$url = [];
		switch ($format) {
			case 'base64':
				$url = ['base64' => base64_encode($fileToSign->getContent())];
				break;
			case 'url':
				try {
					$this->accountFileMapper->getByFileId($fileEntity->getId());
					$url = ['url' => $this->urlGenerator->linkToRoute('libresign.page.getPdf', ['uuid' => $uuid])];
				} catch (DoesNotExistException $e) {
					$url = ['url' => $this->urlGenerator->linkToRoute('libresign.page.getPdfFile', ['uuid' => $uuid])];
				}
				break;
			case 'nodeId':
				$url = ['nodeId' => $fileToSign->getId()];
				break;
			case 'file':
				$url = ['file' => $fileToSign];
				break;
		}
		return $url;
	}
}
