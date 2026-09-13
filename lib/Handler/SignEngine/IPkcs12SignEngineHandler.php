<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: Copyright (c) 2026 Rolf39
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\SignEngine;

interface IPkcs12SignEngineHandler extends ISignEngineHandler {
	public function setCertificate(string $certificate): self;
	public function getCertificate(): string;
	public function readCertificate(): array;
	public function setPassword(string $password): self;
	public function getPassword(): string;
}
