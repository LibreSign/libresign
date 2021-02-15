<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileUser;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Exception\LibresignException;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;

class MailService {
	/** @var LoggerInterface */
	private $logger;
	/** @var Mailer */
	private $mailer;
	/** @var FileMapper */
	private $fileMapper;
	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var IL10N */
	private $l10n;
	/** @var IURLGenerator */
	private $urlGenerator;
	
	public function __construct(
		LoggerInterface $logger,
		IMailer $mailer,
		FileMapper $fileMapper,
		FileUserMapper $fileUserMapper,
		IL10N $l10n,
		IURLGenerator $urlGenerator
	) {
		$this->logger = $logger;
		$this->mailer = $mailer;
		$this->fileMapper = $fileMapper;
		$this->fileUserMapper = $fileUserMapper;
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
	}

	public function notifyAllUnsigned() {
		$unsigned = $this->fileUserMapper->findUnsigned();
		if (!$unsigned) {
			throw new LibresignException('No users to notify', 1);
		}
		foreach ($unsigned as $data) {
			// if ($exists) {
			// 	$this->notifyUnsignedUser($user);
			// } else {
			$this->notifyUnsignedUser($data);
			// }
		}
		return true;
	}

	/**
	 * Undocumented function
	 *
	 * @param int $fileId
	 * @return File
	 */
	private function getFileById($fileId) {
		if (!isset($this->files[$fileId])) {
			$this->files[$fileId] = $this->fileMapper->getById($fileId);
		}
		return $this->files[$fileId];
	}

	public function notifyUnsignedUser(FileUser $data) {
		$emailTemplate = $this->mailer->createEMailTemplate('settings.TestEmail');
		$emailTemplate->setSubject($this->l10n->t('There is a file for you to sign'));
		$emailTemplate->addHeader();
		$emailTemplate->addHeading($this->l10n->t('File to sign'), false);
		$emailTemplate->addBodyText($this->l10n->t('There is a document for you to sign. Access the link below:'));
		$link = $this->urlGenerator->linkToRouteAbsolute('libresign.page.sign', ['uuid' => $data->getUuid()]);
		$file = $this->getFileById($data->getLibresignFileId());
		$emailTemplate->addBodyButton(
			$this->l10n->t('Sign »%s«', [$file->getName()]),
			$link
		);
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
			$this->logger->error('Notify unsigned notification mail could not be sent: ' . $e->getMessage());
			throw new LibresignException('Notify unsigned notification mail could not be sent', 1);
		}
	}
}
