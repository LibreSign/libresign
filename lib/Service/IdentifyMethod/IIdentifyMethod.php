<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\IdentifyMethod;

use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\AbstractSignatureMethod;
use OCP\IUser;

interface IIdentifyMethod {
	public static function getId(): string;
	public function getName(): string;
	public function getFriendlyName(): string;
	public function setCodeSentByUser(string $code): void;
	public function cleanEntity(): void;
	public function setEntity(IdentifyMethod $entity): void;
	public function getEntity(): IdentifyMethod;
	public function getEmptyInstanceOfSignatureMethodByName(string $name): AbstractSignatureMethod;
	public function getSignatureMethods(): array;
	public function signatureMethodsToArray(): array;
	public function getSettings(): array;
	public function willNotifyUser(bool $willNotify): void;
	public function notify(): bool;
	public function validateToRequest(): void;
	public function validateToCreateAccount(string $value): void;
	public function validateToIdentify(): void;
	public function validateToRenew(?IUser $user = null): void;
	public function validateToSign(): void;
	public function save(): void;
	public function delete(): void;
}
