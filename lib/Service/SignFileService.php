<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use DateTime;
use DateTimeInterface;
use Exception;
use InvalidArgumentException;
use OC\AppFramework\Http as AppFrameworkHttp;
use OC\User\NoUserException;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\DataObjects\VisibleElementAssoc;
use OCA\Libresign\Db\AccountFile;
use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Events\SignedEventFactory;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Handler\PdfTk\Pdf;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Handler\SignEngine\SignEngineFactory;
use OCA\Libresign\Handler\SignEngine\SignEngineHandler;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\IToken;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\Events\GenerateSecurePasswordEvent;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sabre\DAV\UUIDUtil;

class SignFileService {
	private SignRequestEntity $signRequest;
	private string $password = '';
	private ?FileEntity $libreSignFile = null;
	/** @var VisibleElementAssoc[] */
	private $elements = [];
	private bool $signWithoutPassword = false;
	private ?File $fileToSign = null;
	private string $userUniqueIdentifier = '';
	private string $friendlyName = '';
	private array $signers = [];
	private ?IUser $user = null;
	private ?SignEngineHandler $engine = null;

	public function __construct(
		protected IL10N $l10n,
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
		private AccountFileMapper $accountFileMapper,
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
		private IDateTimeZone $dateTimeZone,
		private FileElementMapper $fileElementMapper,
		private UserElementMapper $userElementMapper,
		private IEventDispatcher $eventDispatcher,
		protected ISecureRandom $secureRandom,
		private IURLGenerator $urlGenerator,
		private IdentifyMethodMapper $identifyMethodMapper,
		private ITempManager $tempManager,
		private IdentifyMethodService $identifyMethodService,
		private ITimeFactory $timeFactory,
		protected SignEngineFactory $signEngineFactory,
		private SignedEventFactory $signedEventFactory,
		private Pdf $pdf,
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
			throw new \Exception($this->l10n->t('Please provide either UUID or File object'));
		}
		$signed = array_filter($signatures, fn ($s) => $s->getSigned());
		if ($signed) {
			throw new \Exception($this->l10n->t('Document already signed'));
		}
		array_walk($data['users'], function ($user) use ($signatures): void {
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

	public function notifyCallback(File $file): void {
		$uri = $this->libreSignFile->getCallback();
		if (!$uri) {
			$uri = $this->appConfig->getValueString(Application::APP_ID, 'webhook_sign_url');
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
	public function setSignWithoutPassword(bool $signWithoutPassword = true): self {
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

	public function setVisibleElements(array $list): self {
		$fileElements = $this->fileElementMapper->getByFileIdAndSignRequestId($this->signRequest->getFileId(), $this->signRequest->getId());
		$canCreateSignature = $this->signerElementsService->canCreateSignature();

		foreach ($fileElements as $fileElement) {
			$this->elements[] = $this->buildVisibleElementAssoc($fileElement, $list, $canCreateSignature);
		}

		return $this;
	}

	private function buildVisibleElementAssoc(FileElement $fileElement, array $list, bool $canCreateSignature): VisibleElementAssoc {
		if (!$canCreateSignature) {
			return new VisibleElementAssoc($fileElement);
		}

		$element = $this->array_find($list, fn (array $element): bool => ($element['documentElementId'] ?? '') === $fileElement->getId());
		$nodeId = $this->getNodeId($element, $fileElement);

		return $this->bindFileElementWithTempFile($fileElement, $nodeId);
	}

	private function getNodeId(?array $element, FileElement $fileElement): int {
		if ($this->isValidElement($element)) {
			return (int)$element['profileNodeId'];
		}

		return $this->retrieveUserElement($fileElement);
	}

	private function isValidElement(?array $element): bool {
		if (is_array($element) && !empty($element['profileNodeId']) && is_int($element['profileNodeId'])) {
			return true;
		}
		$this->logger->error('Invalid data provided for signing file.', ['element' => $element]);
		throw new LibresignException($this->l10n->t('Invalid data to sign file'), 1);
	}

	private function retrieveUserElement(FileElement $fileElement): int {
		try {
			if (!$this->user instanceof IUser) {
				throw new Exception('User not set');
			}
			$userElement = $this->userElementMapper->findOne([
				'user_id' => $this->user->getUID(),
				'type' => $fileElement->getType(),
			]);
		} catch (MultipleObjectsReturnedException|DoesNotExistException|Exception) {
			throw new LibresignException($this->l10n->t('You need to define a visible signature or initials to sign this document.'));
		}
		return $userElement->getFileId();
	}

	private function bindFileElementWithTempFile(FileElement $fileElement, int $nodeId): VisibleElementAssoc {
		try {
			$node = $this->getNode($nodeId);
			if (!$node) {
				throw new \Exception('Node content is empty or unavailable.');
			}
		} catch (\Throwable) {
			throw new LibresignException($this->l10n->t('You need to define a visible signature or initials to sign this document.'));
		}

		$tempFile = $this->tempManager->getTemporaryFile('_' . $nodeId . '.png');
		$content = $node->getContent();
		if (empty($content)) {
			$this->logger->error('Failed to retrieve content for node.', ['nodeId' => $nodeId, 'fileElement' => $fileElement]);
			throw new LibresignException($this->l10n->t('You need to define a visible signature or initials to sign this document.'));
		}
		file_put_contents($tempFile, $content);
		return new VisibleElementAssoc($fileElement, $tempFile);
	}

	private function getNode(int $nodeId): ?File {
		if ($this->user instanceof IUser) {
			return $this->folderService->getFileById($nodeId);
		}

		$filesOfElementes = $this->signerElementsService->getElementsFromSession();
		return $this->array_find($filesOfElementes, fn ($file) => $file->getId() === $nodeId);
	}

	/**
	 * Fallback to PHP < 8.4
	 *
	 * Reference: https://www.php.net/manual/en/function.array-find.php#130257
	 *
	 * @todo remove this after minor PHP version is >= 8.4
	 * @deprecated This method will be removed once the minimum PHP version is >= 8.4. Use native array_find instead.
	 */
	private function array_find(array $array, callable $callback): mixed {
		foreach ($array as $key => $value) {
			if ($callback($value, $key)) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * @return VisibleElementAssoc[]
	 */
	public function getVisibleElements(): array {
		return $this->elements;
	}

	public function sign(): File {
		$signedFile = $this->getEngine()->sign();

		$hash = $this->computeHash($signedFile);

		$this->updateSignRequest($hash);
		$this->updateLibreSignFile($hash);

		$this->dispatchSignedEvent();

		return $signedFile;
	}

	protected function computeHash(File $file): string {
		return hash('sha256', $file->getContent());
	}

	protected function updateSignRequest(string $hash): void {
		$lastSignedDate = $this->getEngine()->getLastSignedDate();
		$this->signRequest->setSigned($lastSignedDate);
		$this->signRequest->setSignedHash($hash);
		$this->signRequestMapper->update($this->signRequest);
	}

	protected function updateLibreSignFile(string $hash): void {
		$nodeId = $this->getEngine()->getInputFile()->getId();
		$this->libreSignFile->setSignedNodeId($nodeId);
		$this->libreSignFile->setSignedHash($hash);
		$this->setNewStatusIfNecessary();
		$this->fileMapper->update($this->libreSignFile);
	}

	protected function dispatchSignedEvent(): void {
		$event = $this->signedEventFactory->make(
			$this->signRequest,
			$this->libreSignFile,
			$this->getEngine()->getInputFile(),
		);
		$this->eventDispatcher->dispatchTyped($event);
	}

	protected function identifyEngine(File $file): SignEngineHandler {
		return $this->signEngineFactory->resolve($file->getExtension());
	}

	protected function getSignatureParams(): array {
		$certificateData = $this->readCertificate();
		$signatureParams = $this->buildBaseSignatureParams($certificateData);
		$signatureParams = $this->addEmailToSignatureParams($signatureParams, $certificateData);
		$signatureParams = $this->addMetadataToSignatureParams($signatureParams);
		return $signatureParams;
	}

	private function buildBaseSignatureParams(array $certificateData): array {
		return [
			'DocumentUUID' => $this->libreSignFile?->getUuid(),
			'IssuerCommonName' => $certificateData['issuer']['CN'] ?? '',
			'SignerCommonName' => $certificateData['subject']['CN'] ?? '',
			'LocalSignerTimezone' => $this->dateTimeZone->getTimeZone()->getName(),
			'LocalSignerSignatureDateTime' => (new DateTime('now', new \DateTimeZone('UTC')))
				->format(DateTimeInterface::ATOM)
		];
	}

	private function addEmailToSignatureParams(array $signatureParams, array $certificateData): array {
		if (isset($certificateData['extensions']['subjectAltName'])) {
			preg_match('/(?:email:)+(?<email>[^\s,]+)/', $certificateData['extensions']['subjectAltName'], $matches);
			if ($matches && filter_var($matches['email'], FILTER_VALIDATE_EMAIL)) {
				$signatureParams['SignerEmail'] = $matches['email'];
			} elseif (filter_var($certificateData['extensions']['subjectAltName'], FILTER_VALIDATE_EMAIL)) {
				$signatureParams['SignerEmail'] = $certificateData['extensions']['subjectAltName'];
			}
		}
		if (empty($signatureParams['SignerEmail']) && $this->user instanceof IUser) {
			$signatureParams['SignerEmail'] = $this->user->getEMailAddress();
		}
		if (empty($signatureParams['SignerEmail'])) {
			$identifyMethod = $this->identifyMethodService->getIdentifiedMethod($this->signRequest->getId());
			if ($identifyMethod->getName() === IdentifyMethodService::IDENTIFY_EMAIL) {
				$signatureParams['SignerEmail'] = $identifyMethod->getEntity()->getIdentifierValue();
			}
		}
		return $signatureParams;
	}

	private function addMetadataToSignatureParams(array $signatureParams): array {
		$signRequestMetadata = $this->signRequest->getMetadata();
		if (isset($signRequestMetadata['remote-address'])) {
			$signatureParams['SignerIP'] = $signRequestMetadata['remote-address'];
		}
		if (isset($signRequestMetadata['user-agent'])) {
			$signatureParams['SignerUserAgent'] = $signRequestMetadata['user-agent'];
		}
		return $signatureParams;
	}

	public function storeUserMetadata(array $metadata = []): self {
		$collectMetadata = $this->appConfig->getValueBool(Application::APP_ID, 'collect_metadata', false);
		if (!$collectMetadata || !$metadata) {
			return $this;
		}
		$this->signRequest->setMetadata(array_merge(
			$this->signRequest->getMetadata() ?? [],
			$metadata,
		));
		$this->signRequestMapper->update($this->signRequest);
		return $this;
	}

	/**
	 * @return SignRequestEntity[]
	 */
	protected function getSigners(): array {
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

	protected function setNewStatusIfNecessary(): bool {
		$newStatus = $this->evaluateStatusFromSigners();

		if ($newStatus === null || $newStatus === $this->libreSignFile->getStatus()) {
			return false;
		}

		$this->libreSignFile->setStatus($newStatus);
		return true;
	}

	private function evaluateStatusFromSigners(): ?int {
		$signers = $this->getSigners();

		$total = count($signers);

		if ($total === 0) {
			return null;
		}

		$totalSigned = count(array_filter($signers, fn ($s) => $s->getSigned() !== null));

		if ($totalSigned === $total) {
			return FileEntity::STATUS_SIGNED;
		}

		if ($totalSigned > 0) {
			return FileEntity::STATUS_PARTIAL_SIGNED;
		}

		return null;
	}

	private function getOrGeneratePfxContent(SignEngineHandler $engine): string {
		if ($certificate = $engine->getCertificate()) {
			return $certificate;
		}
		if ($this->signWithoutPassword) {
			$tempPassword = $this->generateTemporaryPassword();
			$this->setPassword($tempPassword);
			$engine->generateCertificate(
				[
					'host' => $this->userUniqueIdentifier,
					'uid' => $this->userUniqueIdentifier,
					'name' => $this->friendlyName,
				],
				$tempPassword,
				$this->friendlyName,
			);
		}
		return $engine->getPfxOfCurrentSigner();
	}

	private function generateTemporaryPassword(): string {
		$passwordEvent = new GenerateSecurePasswordEvent();
		$this->eventDispatcher->dispatchTyped($passwordEvent);
		return $passwordEvent->getPassword() ?? $this->secureRandom->generate(20);
	}

	protected function readCertificate(): array {
		return $this->getEngine()
			->readCertificate();
	}

	/**
	 * Get file to sign
	 *
	 * @throws LibresignException
	 */
	protected function getFileToSign(): File {
		if ($this->fileToSign instanceof File) {
			return $this->fileToSign;
		}

		$userId = $this->libreSignFile->getUserId();
		$nodeId = $this->libreSignFile->getNodeId();

		$originalFile = $this->root->getUserFolder($userId)->getFirstNodeById($nodeId);
		if (!$originalFile instanceof File) {
			throw new LibresignException($this->l10n->t('File not found'));
		}
		if ($this->isPdf($originalFile)) {
			$this->fileToSign = $this->getPdfToSign($originalFile);
		} else {
			$this->fileToSign = $originalFile;
		}
		return $this->fileToSign;
	}

	private function isPdf(File $file): bool {
		return strcasecmp($file->getExtension(), 'pdf') === 0;
	}

	protected function getEngine(): SignEngineHandler {
		if (!$this->engine) {
			$originalFile = $this->getFileToSign();
			$this->engine = $this->identifyEngine($originalFile);

			$this->configureEngine();
		}
		return $this->engine;
	}

	private function configureEngine(): void {
		$this->engine
			->setInputFile($this->getFileToSign())
			->setCertificate($this->getOrGeneratePfxContent($this->engine))
			->setPassword($this->password);

		if ($this->engine::class === Pkcs12Handler::class) {
			$this->engine
				->setVisibleElements($this->getVisibleElements())
				->setSignatureParams($this->getSignatureParams());
		}
	}

	public function getLibresignFile(?int $nodeId, ?string $signRequestUuid = null): FileEntity {
		try {
			if ($nodeId) {
				return $this->fileMapper->getByFileId($nodeId);
			}

			if ($signRequestUuid) {
				$signRequest = $this->signRequestMapper->getByUuid($signRequestUuid);
				return $this->fileMapper->getById($signRequest->getFileId());
			}

			throw new \Exception('Invalid arguments');

		} catch (DoesNotExistException) {
			throw new LibresignException($this->l10n->t('File not found'), 1);
		}
	}

	public function renew(SignRequestEntity $signRequest, string $method): void {
		$identifyMethods = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequest->getId());
		if (empty($identifyMethods[$method])) {
			throw new LibresignException($this->l10n->t('Invalid identification method'));
		}

		$signRequest->setUuid(UUIDUtil::getUUID());
		$this->signRequestMapper->update($signRequest);

		array_map(function (IIdentifyMethod $identifyMethod): void {
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
			} catch (InvalidArgumentException) {
				continue;
			}
			/** @var IToken $signatureMethod */
			$signatureMethod->requestCode($identify, $identifyMethod->getEntity()->getIdentifierKey());
			return;
		}
		throw new LibresignException($this->l10n->t('Sending authorization code not enabled.'));
	}

	public function getSignRequestToSign(FileEntity $libresignFile, ?string $signRequestUuid, ?IUser $user): SignRequestEntity {
		$this->validateHelper->fileCanBeSigned($libresignFile);
		try {
			$signRequests = $this->signRequestMapper->getByFileId($libresignFile->getId());

			if (!empty($signRequestUuid)) {
				$signRequest = $this->getSignRequestByUuid($signRequestUuid);
			} else {
				$signRequest = array_reduce($signRequests, function (?SignRequestEntity $carry, SignRequestEntity $signRequest) use ($user): ?SignRequestEntity {
					$identifyMethods = $this->identifyMethodMapper->getIdentifyMethodsFromSignRequestId($signRequest->getId());
					$found = array_filter($identifyMethods, function (IdentifyMethod $identifyMethod) use ($user) {
						if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_EMAIL
							&& $user
							&& (
								$identifyMethod->getIdentifierValue() === $user->getUID()
								|| $identifyMethod->getIdentifierValue() === $user->getEMailAddress()
							)
						) {
							return true;
						}
						if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_ACCOUNT
							&& $user
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
		} catch (DoesNotExistException) {
			try {
				$accountFile = $this->accountFileMapper->getByFileId($libresignFile->getId());
			} catch (\Throwable) {
				throw new LibresignException($this->l10n->t('Invalid data to sign file'), 1);
			}
			$this->validateHelper->userCanApproveValidationDocuments($user);
			$signRequest = new SignRequestEntity();
			$signRequest->setFileId($libresignFile->getId());
			$signRequest->setDisplayName($user->getDisplayName());
			$signRequest->setUuid(UUIDUtil::getUUID());
			$signRequest->setCreatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
		}
		return $signRequest;
	}

	protected function getPdfToSign(File $originalFile): File {
		$file = $this->getSignedFile();
		if ($file instanceof File) {
			return $file;
		}
		$footer = $this->footerHandler
			->setTemplateVar('signers', array_map(fn (SignRequestEntity $signer) => [
				'displayName' => $signer->getDisplayName(),
				'signed' => $signer->getSigned()
					? $signer->getSigned()->format(DateTimeInterface::ATOM)
					: null,
			], $this->getSigners()))
			->getFooter($originalFile, $this->libreSignFile);
		if ($footer) {
			$stamp = $this->tempManager->getTemporaryFile('stamp.pdf');
			file_put_contents($stamp, $footer);

			$input = $this->tempManager->getTemporaryFile('input.pdf');
			file_put_contents($input, $originalFile->getContent());

			try {
				$pdfContent = $this->pdf->applyStamp($input, $stamp);
			} catch (RuntimeException $e) {
				throw new LibresignException($e->getMessage());
			}
		} else {
			$pdfContent = $originalFile->getContent();
		}
		return $this->createSignedFile($originalFile, $pdfContent);
	}

	protected function getSignedFile(): ?File {
		$nodeId = $this->libreSignFile->getSignedNodeId();
		if (!$nodeId) {
			return null;
		}

		$fileToSign = $this->getNodeByIdUsingUid($this->libreSignFile->getUserId(), $nodeId);

		if ($fileToSign->getOwner()->getUID() !== $this->libreSignFile->getUserId()) {
			$fileToSign = $this->getNodeByIdUsingUid($fileToSign->getOwner()->getUID(), $nodeId);
		}
		return $fileToSign;
	}

	protected function getNodeByIdUsingUid(string $uid, int $nodeId): File {
		try {
			$fileToSign = $this->root->getUserFolder($uid)->getFirstNodeById($nodeId);
		} catch (NoUserException) {
			throw new LibresignException($this->l10n->t('User not found.'));
		} catch (NotPermittedException) {
			throw new LibresignException($this->l10n->t('You do not have permission for this action.'));
		}
		if (!$fileToSign instanceof File) {
			throw new LibresignException($this->l10n->t('File not found'));
		}
		return $fileToSign;
	}

	private function createSignedFile(File $originalFile, string $content): File {
		$filename = preg_replace(
			'/' . $originalFile->getExtension() . '$/',
			$this->l10n->t('signed') . '.' . $originalFile->getExtension(),
			basename($originalFile->getPath())
		);
		$owner = $originalFile->getOwner()->getUID();
		try {
			/** @var \OCP\Files\Folder */
			$parentFolder = $this->root->getUserFolder($owner)->getFirstNodeById($originalFile->getParentId());
			return $parentFolder->newFile($filename, $content);
		} catch (NotPermittedException) {
			throw new LibresignException($this->l10n->t('You do not have permission for this action.'));
		}
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getSignRequestByUuid(string $uuid): SignRequestEntity {
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

	public function getNextcloudFile(FileEntity $fileData): File {
		$fileToSign = $this->root->getUserFolder($fileData->getUserId())->getFirstNodeById($fileData->getNodeId());
		if (!$fileToSign instanceof File) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [['message' => $this->l10n->t('File not found')]],
			]), AppFrameworkHttp::STATUS_NOT_FOUND);
		}
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
		$return = array_map(fn (array $identifyMethod): array => [
			'mandatory' => $identifyMethod['mandatory'],
			'identifiedAtDate' => null,
			'validateCode' => false,
			'method' => $identifyMethod['name'],
		], $identifyMethods);
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
				} catch (DoesNotExistException) {
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
