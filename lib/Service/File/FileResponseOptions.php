<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use OCA\Libresign\Db\SignRequest;
use OCP\IUser;

class FileResponseOptions {
	private bool $showSigners = false;
	private bool $showSettings = false;
	private bool $showVisibleElements = false;
	private bool $showMessages = false;
	private bool $validateFile = false;
	private bool $signerIdentified = false;
	private ?IUser $me = null;
	private ?SignRequest $signRequest = null;
	private ?int $identifyMethodId = null;
	private string $host = '';

	public function showSigners(bool $show = true): self {
		$this->showSigners = $show;
		return $this;
	}

	public function isShowSigners(): bool {
		return $this->showSigners;
	}

	public function showSettings(bool $show = true): self {
		$this->showSettings = $show;
		return $this;
	}

	public function isShowSettings(): bool {
		return $this->showSettings;
	}

	public function showVisibleElements(bool $show = true): self {
		$this->showVisibleElements = $show;
		return $this;
	}

	public function isShowVisibleElements(): bool {
		return $this->showVisibleElements;
	}

	public function showMessages(bool $show = true): self {
		$this->showMessages = $show;
		return $this;
	}

	public function isShowMessages(): bool {
		return $this->showMessages;
	}

	public function validateFile(bool $validate = true): self {
		$this->validateFile = $validate;
		return $this;
	}

	public function isValidateFile(): bool {
		return $this->validateFile;
	}

	public function setSignerIdentified(bool $identified = true): self {
		$this->signerIdentified = $identified;
		return $this;
	}

	public function isSignerIdentified(): bool {
		return $this->signerIdentified;
	}

	public function setMe(?IUser $user): self {
		$this->me = $user;
		return $this;
	}

	public function getMe(): ?IUser {
		return $this->me;
	}

	public function setSignRequest(?SignRequest $signRequest): self {
		$this->signRequest = $signRequest;
		return $this;
	}

	public function getSignRequest(): ?SignRequest {
		return $this->signRequest;
	}

	public function setIdentifyMethodId(?int $id): self {
		$this->identifyMethodId = $id;
		return $this;
	}

	public function getIdentifyMethodId(): ?int {
		return $this->identifyMethodId;
	}

	public function setHost(string $host): self {
		$this->host = $host;
		return $this;
	}

	public function getHost(): string {
		return $this->host;
	}
}
