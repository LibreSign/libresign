<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignerRemoval;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;

/**
 * Tracks records touched by a removal, including when a transaction fails.
 * Cache invalidation and notifications must not run inside the transaction.
 */
final class RemovalChanges {
	public ?File $file = null;
	public ?SignRequest $signRequest = null;
	/** @var array<string, array<IIdentifyMethod>> */
	public array $identifyMethods = [];
	/** @var array<int, File> */
	public array $files = [];
	/** @var array<int, SignRequest> */
	public array $signRequests = [];
	/** @var list<SignRequest> */
	public array $activatedSigners = [];

	public function track(File $file, SignRequest $signRequest): void {
		$this->files[$file->getId()] = $file;
		$this->signRequests[$signRequest->getId()] = $signRequest;
	}

	public function activated(SignRequest $signRequest): void {
		$this->signRequests[$signRequest->getId()] = $signRequest;
		$this->activatedSigners[] = $signRequest;
	}
}
