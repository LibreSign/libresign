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
	public function notifySignDataUpdated(SignRequest $data, string $email): void {
		$emailTemplate = $this->mailer->createEMailTemplate('settings.TestEmail');
		// TRANSLATORS The subject of the email that is sent after changes are made to the signature request that may affect something for the signer who will sign the document. Some possible reasons: URL for signature changed (when the URL expires), the person who requested the signature sent a notification
		$emailTemplate->setSubject($this->l10n->t('LibreSign: Changes into a file for you to sign'));
		$emailTemplate->addHeader();
		$emailTemplate->addHeading($this->l10n->t('File to sign'), false);
		$emailTemplate->addBodyText($this->l10n->t('Changes have been made in a file that you have to sign. Access the link below:'));
		$link = $this->urlGenerator->linkToRouteAbsolute('libresign.page.sign', ['uuid' => $data->getUuid()]);
		$file = $this->getFileById($data->getFileId());
		$emailTemplate->addBodyButton(
			$this->l10n->t('Sign »%s«', [$file->getName()]),
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
	public function notifyUnsignedUser(SignRequest $data, string $email): void {
		$emailTemplate = $this->mailer->createEMailTemplate('settings.TestEmail');
		$emailTemplate->setSubject($this->l10n->t('LibreSign: There is a file for you to sign'));
		$emailTemplate->addHeader();
		$emailTemplate->addHeading($this->l10n->t('File to sign'), false);
		$emailTemplate->addBodyText($this->l10n->t('There is a document for you to sign. Access the link below:'));
		$link = $this->urlGenerator->linkToRouteAbsolute('libresign.page.sign', ['uuid' => $data->getUuid()]);
		$file = $this->getFileById($data->getFileId());
		$emailTemplate->addBodyButton(
			$this->l10n->t('Sign »%s«', [$file->getName()]),
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
		// TRANSLATORS The subject of the email that is sent after a document has been signed by a user. This email is sent to the person who requested the signature.
		$emailTemplate->setSubject($this->l10n->t('LibreSign: A file has been signed'));
		$emailTemplate->addHeader();
		$emailTemplate->addHeading($this->l10n->t('File signed'), false);
		// TRANSLATORS The text in the email that is sent after a document has been signed by a user. %s will be replaced with the name of the user who signed the document.
		$emailTemplate->addBodyText($this->l10n->t('%s signed the document. You can access it using the link below:', [$signRequest->getDisplayName()]));
		$link = $this->urlGenerator->linkToRouteAbsolute('libresign.page.indexFPath', [
			'path' => 'validation/' . $libreSignFile->getUuid(),
		]);
		$file = $this->getFileById($signRequest->getFileId());
		$emailTemplate->addBodyButton(
			// TRANSLATORS The button text in the email that is sent after a document has been signed by a user. %s will be replaced with the name of the file that was signed.
			$this->l10n->t('View signed file »%s«', [$file->getName()]),
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

	public function sendCodeToSign(string $email, string $name, string $code): void {
		$emailTemplate = $this->mailer->createEMailTemplate('settings.TestEmail');
		$emailTemplate->setSubject($this->l10n->t('LibreSign: Code to sign file'));
		$emailTemplate->addHeader();
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
