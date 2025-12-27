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
				'message' => $this->l10n->t('You need to sign this document')
			];
		}

		if (isset($fileData->settings['canRequestSign']) && $fileData->settings['canRequestSign']) {
			$this->signersLoader->loadLibreSignSigners($file, $fileData, $options, $certData);

			if (empty($fileData->signers)) {
				$messages[] = [
					'type' => 'info',
					'message' => $this->l10n->t('You cannot request signature for this document, please contact your administrator')
				];
			}
		}

		if (!empty($messages)) {
			$fileData->messages = $messages;
		}
	}
}
