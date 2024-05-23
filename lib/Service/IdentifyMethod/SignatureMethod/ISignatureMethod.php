<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\IdentifyMethod\SignatureMethod;

interface ISignatureMethod {
	public const SIGNATURE_METHOD_CLICK_TO_SIGN = 'clickToSign';
	public const SIGNATURE_METHOD_EMAIL_TOKEN = 'emailToken';
	public const SIGNATURE_METHOD_PASSWORD = 'password';
	public function enable(): void;
	public function isEnabled(): bool;
	public function toArray(): array;
}
