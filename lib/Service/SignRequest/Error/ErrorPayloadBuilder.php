<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignRequest\Error;

use DateTime;
use DateTimeInterface;

class ErrorPayloadBuilder {
	private string $message = 'An error occurred';
	private int $code = 0;
	private ?int $fileId = null;
	private ?int $signRequestId = null;
	private ?string $signRequestUuid = null;
	/** @var array<int, array{message: string, code: int}> */
	private array $fileErrors = [];

	public static function fromException(\Throwable $e, ?int $fileId = null, ?int $signRequestId = null, ?string $signRequestUuid = null): self {
		$builder = new self();
		return $builder
			->setMessage($e->getMessage())
			->setCode($e->getCode())
			->setFileId($fileId)
			->setSignRequestId($signRequestId)
			->setSignRequestUuid($signRequestUuid);
	}

	public function setMessage(string $message): self {
		$this->message = $message;
		return $this;
	}

	public function setCode(int $code): self {
		$this->code = $code;
		return $this;
	}

	public function setFileId(?int $fileId): self {
		$this->fileId = $fileId;
		return $this;
	}

	public function setSignRequestId(?int $signRequestId): self {
		$this->signRequestId = $signRequestId;
		return $this;
	}

	public function setSignRequestUuid(?string $signRequestUuid): self {
		$this->signRequestUuid = $signRequestUuid;
		return $this;
	}

	public function addFileError(int $fileId, \Throwable $e): self {
		$this->fileErrors[$fileId] = [
			'message' => $e->getMessage(),
			'code' => $e->getCode(),
		];
		return $this;
	}

	public function clearFileErrors(): self {
		$this->fileErrors = [];
		return $this;
	}

	public function build(): array {
		$payload = [
			'message' => $this->message,
			'code' => $this->code,
			'timestamp' => (new DateTime())->format(DateTimeInterface::ATOM),
		];

		if ($this->fileId !== null) {
			$payload['fileId'] = $this->fileId;
		}

		if ($this->signRequestId !== null) {
			$payload['signRequestId'] = $this->signRequestId;
		}

		if ($this->signRequestUuid !== null) {
			$payload['signRequestUuid'] = $this->signRequestUuid;
		}

		if (!empty($this->fileErrors)) {
			$payload['fileErrors'] = $this->fileErrors;
		}

		return $payload;
	}
}
