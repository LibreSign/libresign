<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Service\IdDocRejectionRecipientResolver;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\DataProvider;

final class IdDocRejectionRecipientResolverTest extends TestCase {
	/**
	 * @param list<array{string, string}> $methods
	 * @param array<string, string|null> $accountEmails
	 * @param list<array{uid: ?string, email: ?string}> $expected
	 */
	#[DataProvider('recipients')]
	public function testResolvesRecipientIdentitiesWithoutModifyingTheirMeaning(
		?string $accountUserId,
		array $methods,
		array $accountEmails,
		array $expected,
	): void {
		$mapper = $this->createMock(IdentifyMethodMapper::class);
		$users = $this->createMock(IUserManager::class);
		$identifiers = [];
		foreach ($methods as [$key, $value]) {
			$method = new IdentifyMethod();
			$method->setIdentifierKey($key);
			$method->setIdentifierValue($value);
			$identifiers[] = $method;
		}
		if ($accountUserId !== null) {
			$mapper->expects($this->never())->method('getIdentifyMethodsFromSignRequestId');
		} else {
			$mapper->expects($this->once())->method('getIdentifyMethodsFromSignRequestId')
				->with(42)->willReturn($identifiers);
		}
		$users->method('get')->willReturnCallback(function (string $uid) use ($accountEmails): ?IUser {
			if (!array_key_exists($uid, $accountEmails)) {
				return null;
			}
			$user = $this->createMock(IUser::class);
			$user->method('getEMailAddress')->willReturn($accountEmails[$uid]);
			return $user;
		});
		$resolver = new IdDocRejectionRecipientResolver($mapper, $users);

		$this->assertSame($expected, $resolver->resolve($accountUserId, 42));
	}

	public static function recipients(): array {
		return [
			'registered signer overrides unrelated methods' => [
				'signer', [['email', 'another@example.test']], ['signer' => 'signer@example.test'],
				[['uid' => 'signer', 'email' => 'signer@example.test']],
			],
			'registered signer without email' => [
				'signer', [], ['signer' => null],
				[['uid' => 'signer', 'email' => null]],
			],
			'missing account remains an in-app target' => [
				'deleted-user', [], [],
				[['uid' => 'deleted-user', 'email' => null]],
			],
			'guest with email method' => [
				null, [['email', 'external@example.test']], [],
				[['uid' => null, 'email' => 'external@example.test']],
			],
			'guest with account method' => [
				null, [['account', 'signer']], ['signer' => 'signer@example.test'],
				[['uid' => 'signer', 'email' => 'signer@example.test']],
			],
			'mixed methods retain identities and order' => [
				null, [['email', 'one@example.test'], ['account', 'signer']], ['signer' => 'two@example.test'],
				[
					['uid' => null, 'email' => 'one@example.test'],
					['uid' => 'signer', 'email' => 'two@example.test'],
				],
			],
			'unsupported methods ignored' => [
				null, [['password', 'secret'], ['email', 'external@example.test']], [],
				[['uid' => null, 'email' => 'external@example.test']],
			],
			'duplicate methods preserved for outbox idempotency' => [
				null, [['email', 'same@example.test'], ['email', 'same@example.test']], [],
				[
					['uid' => null, 'email' => 'same@example.test'],
					['uid' => null, 'email' => 'same@example.test'],
				],
			],
			'no methods produces no recipients' => [null, [], [], []],
		];
	}
}
