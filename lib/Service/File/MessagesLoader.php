<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use OCA\Libresign\Db\File;
use OCP\IL10N;
use stdClass;

class MessagesLoader {
	public function __construct(
		private SignersLoader $signersLoader,
		private IL10N $l10n,
	) {
	}

	public function loadMessages(
		?File $file,
		stdClass $fileData,
		FileResponseOptions $options,
		array $certData = [],
	): void {
		if (!$options->isShowMessages()) {
			return;
		}

		$messages = [];

		if (isset($fileData->settings['canSign']) && $fileData->settings['canSign']) {
			$messages[] = [
				'type' => 'info',
				// TRANSLATORS: Informational message shown in the file details UI when the current user is one of the required signers and still needs to sign the document.
				'message' => $this->l10n->t('You need to sign this document')
			];
		}

		if (isset($fileData->settings['canRequestSign']) && $fileData->settings['canRequestSign']) {
			$this->signersLoader->loadLibreSignSigners($file, $fileData, $options, $certData);

			if (empty($fileData->signers)) {
				$messages[] = [
					'type' => 'info',
					// TRANSLATORS: Informational message shown when the current user can request signatures in general, but no eligible signer can be added for this specific document.
					'message' => $this->l10n->t('You cannot create a signature request for this document. Please contact your administrator.')
				];
			}
		}

		if (!empty($messages)) {
			$fileData->messages = $messages;
		}
	}
}
