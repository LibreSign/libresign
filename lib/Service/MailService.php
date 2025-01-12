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
		$notifyUnsignedUser = $this->appConfig->getValueBool(Application::APP_ID, 'notify_unsigned_user', true);
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
