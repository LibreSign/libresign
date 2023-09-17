<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUser;
use OCA\Libresign\Exception\LibresignException;
use OCP\IConfig;
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
		private IConfig $config
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
	public function notifySignDataUpdated(FileUser $data, string $email): void {
		$emailTemplate = $this->mailer->createEMailTemplate('settings.TestEmail');
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
	public function notifyUnsignedUser(FileUser $data, string $email): void {
		$notifyUnsignedUser = $this->config->getAppValue(Application::APP_ID, 'notify_unsigned_user', true);
		if (!$notifyUnsignedUser) {
			return;
		}
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

	/**
	 * @psalm-suppress MixedMethodCall
	 */
	public function notifyCancelSign(FileUser $data): void {
		$emailTemplate = $this->mailer->createEMailTemplate('settings.TestEmail');
		$emailTemplate->setSubject($this->l10n->t('LibreSign: Signature request cancelled'));
		$emailTemplate->addHeader();
		$emailTemplate->addBodyText($this->l10n->t('The signature request has been canceled.'));
		$message = $this->mailer->createMessage();
		if ($data->getDisplayName()) {
			$message->setTo([$data->getEmail() => $data->getDisplayName()]);
		} else {
			$message->setTo([$data->getEmail()]);
		}
		$message->useTemplate($emailTemplate);
		try {
			$this->mailer->send($message);
		} catch (\Exception $e) {
			$this->logger->error('Notify cancel sign notification mail could not be sent: ' . $e->getMessage());
			throw new LibresignException('Notify cancel sign notification mail could not be sent', 1);
		}
	}

	public function sendCodeToSign(FileUser $fileUser, string $code): void {
		$emailTemplate = $this->mailer->createEMailTemplate('settings.TestEmail');
		$emailTemplate->setSubject($this->l10n->t('LibreSign: Code to sign file'));
		$emailTemplate->addHeader();
		$emailTemplate->addBodyText($this->l10n->t('Use this code to sign the document:'));
		$emailTemplate->addBodyText($code);
		$message = $this->mailer->createMessage();
		if ($fileUser->getDisplayName()) {
			$message->setTo([$fileUser->getEmail() => $fileUser->getDisplayName()]);
		} else {
			$message->setTo([$fileUser->getEmail()]);
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
