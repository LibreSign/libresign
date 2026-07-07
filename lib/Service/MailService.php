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
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Mail\IMailer;
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
		$message = $this->mailer->createMessage();
		if ($data->getDisplayName()) {
			$message->setTo([$email => $data->getDisplayName()]);
		} else {
			$message->setTo([$email]);
		}
		$message->useTemplate($emailTemplate);
		try {
			$this->mailer->send($message);
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
		$message = $this->mailer->createMessage();
		if ($data->getDisplayName()) {
			$message->setTo([$email => $data->getDisplayName()]);
		} else {
			$message->setTo([$email]);
		}
		$message->useTemplate($emailTemplate);
		try {
			$this->mailer->send($message);
		} catch (\Exception $e) {
			$this->logger->error('Notify unsigned notification mail could not be sent: ' . $e->getMessage());
			throw new LibresignException('Notify unsigned notification mail could not be sent', 1);
		}
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
