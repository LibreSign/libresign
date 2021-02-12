<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\FileUser;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Service\AccountService;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class AccountServiceTest extends TestCase {
	/** @var IL10N */
	private $l10n;
	/** @var FileUserMapper */
	private $fileUserMapper;

	public function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));
		$this->fileUserMapper = $this->createMock(FileUserMapper::class);
		$this->service = new AccountService(
			$this->l10n,
			$this->fileUserMapper
		);
	}

	public function testValidateInvalidUuid() {
		$this->expectExceptionMessage('Invalid UUID');
		$this->service->validateCreateToSign([
			'uuid' => 'invalid'
		]);
	}

	public function testValidateUuidNotFound() {
		$this->fileUserMapper
			->method('getByUuid')
			->willReturnCallback(function () {
				throw new \Exception("Beep, beep, not found!", 1);
			});
		$this->expectExceptionMessage('UUID not found');
		$this->service->validateCreateToSign([
			'uuid' => '12345678-1234-1234-1234-123456789012'
		]);
	}

	public function testValidateInvalidEmail() {
		$this->expectExceptionMessage('Invalid email');
		$this->service->validateCreateToSign([
			'uuid' => '12345678-1234-1234-1234-123456789012',
			'email' => 'invalid'
		]);
	}

	public function testValidateDontIsYourFile() {
		$fileUser = $this->createMock(FileUser::class);
		$fileUser
			->method('__call')
			->with($this->equalTo('getEmail'), $this->anything())
			->will($this->returnValue('valid@test.coop'));
		$this->fileUserMapper
			->method('getByUuid')
			->will($this->returnValue($fileUser));
		$this->expectExceptionMessage('Dont is your file');
		$this->service->validateCreateToSign([
			'uuid' => '12345678-1234-1234-1234-123456789012',
			'email' => 'invalid@test.coop'
		]);
	}

	public function testValidateuserAlreadyExists() {
		$fileUser = $this->createMock(FileUser::class);
		$fileUser
			->method('__call')
			->with($this->equalTo('getEmail'), $this->anything())
			->will($this->returnValue('valid@test.coop'));
		$this->fileUserMapper
			->method('getByUuid')
			->will($this->returnValue($fileUser));
		$this->userManager
			->method('userExists')
			->will($this->returnValue(true));
		$this->expectExceptionMessage('User already exists');
		$this->service->validateCreateToSign([
			'uuid' => '12345678-1234-1234-1234-123456789012',
			'email' => 'valid@test.coop'
		]);
	}

	public function testValidatePasswordEmpty() {
		$fileUser = $this->createMock(FileUser::class);
		$fileUser
			->method('__call')
			->with($this->equalTo('getEmail'), $this->anything())
			->will($this->returnValue('valid@test.coop'));
		$this->fileUserMapper
			->method('getByUuid')
			->will($this->returnValue($fileUser));
		$this->expectExceptionMessage('Password is mandatory');
		$this->service->validateCreateToSign([
			'uuid' => '12345678-1234-1234-1234-123456789012',
			'email' => 'valid@test.coop',
			'password' => '',
		]);
	}

	public function testValidateSignPasswordDontMatch() {
		$fileUser = $this->createMock(FileUser::class);
		$fileUser
			->method('__call')
			->with($this->equalTo('getEmail'), $this->anything())
			->will($this->returnValue('valid@test.coop'));
		$this->fileUserMapper
			->method('getByUuid')
			->will($this->returnValue($fileUser));
		$this->expectExceptionMessage('Password to sign is mandatory');
		$this->service->validateCreateToSign([
			'uuid' => '12345678-1234-1234-1234-123456789012',
			'email' => 'valid@test.coop',
			'password' => '123456789',
			'signPassword' => '123456789',
		]);
	}
}
