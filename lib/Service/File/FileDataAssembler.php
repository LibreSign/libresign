<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use OCA\Libresign\Db\File;
use OCA\Libresign\Service\FileService;
use stdClass;

class FileDataAssembler {
	public function __construct(
		private FileService $fileService,
	) {
	}

	public function assembleForResponse(
		File $file,
		FileResponseOptions $options,
	): FileResponseData {
		$this->fileService->setFile($file);

		if ($options->isShowSigners()) {
			$this->fileService->showSigners(true);
		}

		if ($options->isShowSettings()) {
			$this->fileService->showSettings(true);
		}

		if ($options->isShowVisibleElements()) {
			$this->fileService->showVisibleElements(true);
		}

		if ($options->isShowMessages()) {
			$this->fileService->showMessages(true);
		}

		if ($options->isValidateFile()) {
			$this->fileService->showValidateFile(true);
		}

		if ($options->isSignerIdentified()) {
			$this->fileService->setSignerIdentified(true);
		}

		if ($options->getMe()) {
			$this->fileService->setMe($options->getMe());
		}

		if ($options->getIdentifyMethodId()) {
			$this->fileService->setIdentifyMethodId($options->getIdentifyMethodId());
		}

		if ($options->getHost()) {
			$this->fileService->setHost($options->getHost());
		}

		$fileData = new stdClass();

		$rawData = $this->fileService->toArray();

		$fileData = json_decode(json_encode($rawData));

		return new FileResponseData($file, $fileData);
	}
}
