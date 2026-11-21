<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\IdDoc;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;

/** Compose the recipient-localized message; MailService owns its delivery. */
class IdDocRejectionEmailTemplateBuilder {
	public function __construct(
		private IUserManager $users,
		private IFactory $l10nFactory,
		private IMailer $mailer,
		private IURLGenerator $urls,
	) {
	}

	public function build(
		SignRequest $signRequest,
		File $document,
		string $reason,
		bool $accountSigner,
		?string $recipientUserId = null,
	): IEMailTemplate {
		// Resolve language at delivery time: a queued message has no signer request context.
		$recipient = $recipientUserId !== null ? $this->users->get($recipientUserId) : null;
		$language = $recipient !== null ? $this->l10nFactory->getUserLanguage($recipient) : null;
		$l10n = $this->l10nFactory->get('libresign', $language);

		$template = $this->mailer->createEMailTemplate('libresign.IdentificationDocumentRejected');
		$template->setLanguage($l10n->getLanguageCode());
		// TRANSLATORS Subject of an email asking the signer to replace a rejected identification document.
		$template->setSubject($l10n->t('LibreSign: Identification document rejected'));
		$template->addHeader();
		// TRANSLATORS Email heading for a rejected identification document.
		$template->addHeading($l10n->t('Please replace your identification document'));
		// TRANSLATORS Email body identifying the rejected file. %s is its filename.
		$template->addBodyText($l10n->t('Your identification document "%s" was not approved.', [$document->getName()]));
		// TRANSLATORS Email text introducing the reviewer's explanation for rejecting the identification document.
		$template->addBodyText($l10n->t('The reviewer provided the following reason:'));
		// The reviewer's free-text reason must remain unmodified; IEMailTemplate escapes its HTML.
		$template->addBodyText($reason);
		// TRANSLATORS Instruction for the signer after the submitted identification document was rejected.
		$template->addBodyText($l10n->t('Upload a replacement identification document to continue signing.'));
		$template->addBodyButton(
			// TRANSLATORS Button to return to the identification document upload flow.
			$l10n->t('Replace identification document'),
			$accountSigner
				? $this->urls->linkToRouteAbsolute('libresign.page.indexF')
				: $this->urls->linkToRouteAbsolute('libresign.page.signFPath', ['uuid' => $signRequest->getUuid(), 'path' => 'pdf']),
		);
		$template->addFooter();
		return $template;
	}
}
