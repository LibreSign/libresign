<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\MailSenderStrategy\MailSenderStrategyPolicy;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Mail\Provider\Address;
use OCP\Mail\Provider\IManager as IMailProviderManager;
use OCP\Mail\Provider\IMessageSend;
use OCP\Mail\Provider\IService;
use Psr\Log\LoggerInterface;

class MailService {
	/** @var array */
	private $files = [];

	public function __construct(
		private LoggerInterface $logger,
		private IMailer $mailer,
		private FileMapper $fileMapper,
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private IAppConfig $appConfig,
		private PolicyService $policyService,
		private IMailProviderManager $mailProviderManager,
		private IUserManager $userManager,
	) {
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 */
	private function getFileById(int $fileId): File {
		if (!isset($this->files[$fileId])) {
			$this->files[$fileId] = $this->fileMapper->getById($fileId);
		}
		return $this->files[$fileId];
	}

	/**
	 * @psalm-suppress MixedMethodCall
	 */
	public function notifySignDataUpdated(SignRequest $data, string $email, ?string $description = null): void {
		$emailTemplate = $this->mailer->createEMailTemplate('settings.TestEmail');
		// TRANSLATORS Email subject notifying a signer that a pending signature request changed and should be reviewed again.
		$emailTemplate->setSubject($this->l10n->t('LibreSign: Changes were made to a document waiting for your signature'));
		$emailTemplate->addHeader();
		// TRANSLATORS Email heading shown above a pending document that still needs the recipient's signature.
		$emailTemplate->addHeading($this->l10n->t('Document to sign'), false);

		if (!empty($description)) {
			$emailTemplate->addBodyText($description);
			$emailTemplate->addBodyText('');
		}

		// TRANSLATORS Email body telling the signer to reopen the request because some request details changed.
		$emailTemplate->addBodyText($this->l10n->t('Changes were made to a document you need to sign. Open the link below:'));
		$link = $this->urlGenerator->linkToRouteAbsolute('libresign.page.sign', ['uuid' => $data->getUuid()]);
		$file = $this->getFileById($data->getFileId());
		$emailTemplate->addBodyButton(
			// TRANSLATORS Email button label that opens the signing page. %s is the document filename.
			$this->l10n->t('Sign "%s"', [$file->getName()]),
			$link
		);
		try {
			$this->sendSignRequestNotification($emailTemplate, $data, $email);
		} catch (\Exception $e) {
			$this->logger->error('Notify changes in unsigned notification mail could not be sent: ' . $e->getMessage());
			throw new LibresignException('Notify unsigned notification mail could not be sent', 1);
		}
	}

	/**
	 * @psalm-suppress MixedMethodCall
	 */
	public function notifyUnsignedUser(SignRequest $data, string $email, ?string $description = null): void {
		$emailTemplate = $this->mailer->createEMailTemplate('settings.TestEmail');
		// TRANSLATORS Email subject notifying a signer that a document is ready for their digital signature.
		$emailTemplate->setSubject($this->l10n->t('LibreSign: A document is ready for your signature'));
		$emailTemplate->addHeader();
		// TRANSLATORS Email heading shown above a document awaiting the recipient's signature.
		$emailTemplate->addHeading($this->l10n->t('Document to sign'), false);

		if (!empty($description)) {
			$emailTemplate->addBodyText($description);
			$emailTemplate->addBodyText('');
		}

		// TRANSLATORS Email body inviting the signer to open the document and sign it.
		$emailTemplate->addBodyText($this->l10n->t('A document is ready for your signature. Open the link below:'));
		$link = $this->urlGenerator->linkToRouteAbsolute('libresign.page.sign', ['uuid' => $data->getUuid()]);
		$file = $this->getFileById($data->getFileId());
		$emailTemplate->addBodyButton(
			// TRANSLATORS Email button label that opens the signing page. %s is the document filename.
			$this->l10n->t('Sign "%s"', [$file->getName()]),
			$link
		);
		try {
			$this->sendSignRequestNotification($emailTemplate, $data, $email);
		} catch (\Exception $e) {
			$this->logger->error('Notify unsigned notification mail could not be sent: ' . $e->getMessage());
			throw new LibresignException('Notify unsigned notification mail could not be sent', 1);
		}
	}

	/**
	 * Sends a signature request notification using the strategy configured in
	 * the mail_sender_strategy policy, falling back to the system mailer when
	 * the requester mail account cannot be used.
	 */
	private function sendSignRequestNotification(IEMailTemplate $emailTemplate, SignRequest $data, string $email): void {
		$requesterId = $this->getFileById($data->getFileId())->getUserId();
		if ($this->resolveMailSenderStrategy($requesterId) !== MailSenderStrategyPolicy::STRATEGY_REQUESTER) {
			$this->sendAsSystem($emailTemplate, $data, $email);
			return;
		}

		$requester = $requesterId !== '' ? $this->userManager->get($requesterId) : null;
		if ($this->sendAsRequester($emailTemplate, $data, $email, $requesterId, $requester)) {
			return;
		}
		// Keep replies going to the person who requested the signature even
		// when the message could not leave their own mail account.
		$this->sendAsSystem($emailTemplate, $data, $email, $this->replyToAddress($requester));
	}

	/**
	 * @return array<string, string>|null
	 */
	private function replyToAddress(?IUser $requester): ?array {
		if (!$requester instanceof IUser) {
			return null;
		}
		$address = (string)$requester->getEMailAddress();
		if ($address === '') {
			return null;
		}
		return [$address => $requester->getDisplayName()];
	}

	private function resolveMailSenderStrategy(string $requesterId): string {
		return (string)$this->policyService
			->resolveForUserId(MailSenderStrategyPolicy::KEY, $requesterId !== '' ? $requesterId : null)
			->getEffectiveValue();
	}

	/**
	 * @param array<string, string>|null $replyTo
	 */
	private function sendAsSystem(IEMailTemplate $emailTemplate, SignRequest $data, string $email, ?array $replyTo = null): void {
		$message = $this->mailer->createMessage();
		if ($data->getDisplayName()) {
			$message->setTo([$email => $data->getDisplayName()]);
		} else {
			$message->setTo([$email]);
		}
		if ($replyTo !== null) {
			$message->setReplyTo($replyTo);
		}
		$message->useTemplate($emailTemplate);
		$this->mailer->send($message);
	}

	/**
	 * @return bool true when the message was sent through the requester mail account
	 */
	private function sendAsRequester(IEMailTemplate $emailTemplate, SignRequest $data, string $email, string $requesterId, ?IUser $requester): bool {
		try {
			$service = $this->findRequesterMailService($requesterId, $requester);
			if ($service === null) {
				return false;
			}
			$message = $service->initiateMessage()
				->setFrom($service->getPrimaryAddress())
				->setTo(new Address($email, $data->getDisplayName() ?: null))
				->setSubject($emailTemplate->renderSubject())
				->setBodyHtml($emailTemplate->renderHtml())
				->setBodyPlain($emailTemplate->renderText());
			$service->sendMessage($message);
			return true;
		} catch (\Throwable $e) {
			$this->logger->warning('Unable to send the notification through the requester mail account, falling back to the system mailer.', [
				'requester' => $requesterId,
				'exception' => $e,
			]);
			return false;
		}
	}

	private function findRequesterMailService(string $requesterId, ?IUser $requester): (IService&IMessageSend)|null {
		if ($requesterId === '' || !$this->mailProviderManager->has()) {
			$this->logger->info('No mail provider is available to send the notification as the requester, falling back to the system mailer.', [
				'requester' => $requesterId,
			]);
			return null;
		}
		if (!$requester instanceof IUser) {
			$this->logger->info('Requester account not found, falling back to the system mailer.', [
				'requester' => $requesterId,
			]);
			return null;
		}
		$address = (string)$requester->getEMailAddress();
		$service = $address !== '' ? $this->mailProviderManager->findServiceByAddress($requesterId, $address) : null;
		if ($service instanceof IMessageSend) {
			return $service;
		}
		$this->logger->info('No mail account matching the requester email address can send messages, falling back to the system mailer.', [
			'requester' => $requesterId,
		]);
		return null;
	}

	public function notifySignedUser(SignRequest $signRequest, string $email, File $libreSignFile, string $displayName): void {
		$emailTemplate = $this->mailer->createEMailTemplate('settings.TestEmail');
		// TRANSLATORS Email subject sent to the requester after another signer successfully signs the document.
		$emailTemplate->setSubject($this->l10n->t('LibreSign: A document has been signed'));
		$emailTemplate->addHeader();
		// TRANSLATORS Email heading shown after a document was completed by one signer.
		$emailTemplate->addHeading($this->l10n->t('Signed document'), false);
		// TRANSLATORS Email body confirming that a signer finished signing. %s is the display name of the signer who completed the document.
		$emailTemplate->addBodyText($this->l10n->t('%s signed the document. You can access it using the link below:', [$signRequest->getDisplayName()]));
		$link = $this->urlGenerator->linkToRouteAbsolute('libresign.page.indexFPath', [
			'path' => 'validation/' . $libreSignFile->getUuid(),
		]);
		$file = $this->getFileById($signRequest->getFileId());
		$emailTemplate->addBodyButton(
			// TRANSLATORS Email button label that opens the validation view of the signed document. %s is the document filename.
			$this->l10n->t('View signed document "%s"', [$file->getName()]),
			$link
		);
		$message = $this->mailer->createMessage();
		$message->setTo([$email => $displayName]);

		$message->useTemplate($emailTemplate);
		try {
			$this->mailer->send($message);
		} catch (\Exception $e) {
			$this->logger->error('Notify signed notification mail could not be sent: ' . $e->getMessage());
			throw new LibresignException('Notify signed notification mail could not be sent', 1);
		}
	}

	public function notifyCanceledRequest(SignRequest $signRequest, string $email, File $libreSignFile): void {
		$emailTemplate = $this->mailer->createEMailTemplate('settings.TestEmail');
		// TRANSLATORS Email subject shown when the requester cancels a pending signature request.
		$emailTemplate->setSubject($this->l10n->t('LibreSign: A signature request has been canceled'));
		$emailTemplate->addHeader();
		// TRANSLATORS Email heading shown when a signature request is no longer active.
		$emailTemplate->addHeading($this->l10n->t('Signature request canceled'), false);
		// TRANSLATORS Email body text shown after cancellation. %s is the document filename that no longer needs a signature.
		$emailTemplate->addBodyText($this->l10n->t('The request for you to sign "%s" has been canceled.', [$libreSignFile->getName()]));
		$message = $this->mailer->createMessage();
		if ($signRequest->getDisplayName()) {
			$message->setTo([$email => $signRequest->getDisplayName()]);
		} else {
			$message->setTo([$email]);
		}
		$message->useTemplate($emailTemplate);
		try {
			$this->mailer->send($message);
		} catch (\Exception $e) {
			$this->logger->error('Notify canceled request mail could not be sent: ' . $e->getMessage());
			// Don't throw exception to avoid breaking the flow when mail fails
		}
	}

	public function sendCodeToSign(string $email, string $name, string $code): void {
		$emailTemplate = $this->mailer->createEMailTemplate('settings.TestEmail');
		// TRANSLATORS Email subject for a one-time verification code required to sign a document.
		$emailTemplate->setSubject($this->l10n->t('LibreSign: Verification code to sign a document'));
		$emailTemplate->addHeader();
		// TRANSLATORS Email instruction introducing the one-time code used to complete a digital signature.
		$emailTemplate->addBodyText($this->l10n->t('Use this code to sign the document:'));
		$emailTemplate->addBodyText($code);
		$message = $this->mailer->createMessage();
		if (!empty($name)) {
			$message->setTo([$email => $name]);
		} else {
			$message->setTo([$email]);
		}
		$message->useTemplate($emailTemplate);
		try {
			$this->mailer->send($message);
		} catch (\Exception $e) {
			$this->logger->error('Mail with code to sign document could not be sent: ' . $e->getMessage());
			throw new LibresignException('Mail with code to sign document could not be sent', 1);
		}
	}
}
