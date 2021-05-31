<?php

namespace OCA\Libresign\Tests\Unit\Controller;

use OC\Authentication\Login\Chain;
use OCA\Libresign\Controller\AccountController;
use OCA\Libresign\Db\File as LibresignFile;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUser;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Tests\lib\User\Dummy;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

final class AccountControllerTest extends TestCase {
	use ProphecyTrait;
	/** @var AccountController */
	private $controller;
	/** @var IRequest */
	private $request;
	/** @var IL10N */
	private $l10n;
	/** @var AccountService */
	private $account;
	/** @var FileMapper */
	private $fileMapper;
	/** @var IRootFolder */
	private $root;
	/** @var Chain */
	private $loginChain;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var IUserSession */
	private $session;

	public function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));
		$this->account = $this->createMock(AccountService::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->root = $this->createMock(IRootFolder::class);
		$this->loginChain = $this->createMock(Chain::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->session = $this->getMockBuilder(IUserSession::class)
			->disableOriginalConstructor()
			->getMock();
		$this->controller = new AccountController(
			$this->request,
			$this->l10n,
			$this->account,
			$this->fileMapper,
			$this->root,
			$this->loginChain,
			$this->urlGenerator,
			$this->session
		);
	}

	public function testCreateSuccess() {
		$fileUser = $this->createMock(FileUser::class);
		$fileUser
			->method('__call')
			->withConsecutive(
				[$this->equalTo('getFileId'), $this->anything()],
				[$this->equalTo('getDescription'), $this->anything()]
			)
			->will($this->returnValueMap([
				[$this->returnValue(1)],
				[$this->returnValue('Description')]
			]));
		$this->account
			->method('getFileUserByUuid')
			->will($this->returnValue($fileUser));

		$fileData = $this->createMock(LibresignFile::class);
		$fileData
			->method('__call')
			->withConsecutive(
				[$this->equalTo('getUserId'), $this->anything()],
				[$this->equalTo('getNodeId'), $this->anything()],
				[$this->equalTo('getName'), $this->anything()]
			)
			->will($this->returnValueMap([
				['getUserId', [], 1],
				['getNodeId', [], 1],
				['getName', [], 'Filename']
			]));
		$this->fileMapper
			->method('getById')
			->will($this->returnValue($fileData));

		$userDummyBackend = $this->createMock(Dummy::class);
		$userDummyBackend
			->method('userExists')
			->will($this->returnValue(true));
		\OC::$server->getUserManager()->registerBackend($userDummyBackend);
		\OC::$server->getSession()->set('user_id', 1);

		$node = $this->createMock(File::class);
		$node->method('getContent')
			->will($this->returnvalue('PDF'));
		$this->root
			->method('getById')
			->will($this->returnValue([$node]));

			
		$this->urlGenerator
			->method('linkToRoute')
			->will($this->returnValue('http://test.coop'));

		$actual = $this->controller->createToSign('uuid', 'email', 'password', 'signPassword');
		$expected = new JSONResponse([
			'message' => 'Success',
			'action' => JSActions::ACTION_SIGN,
			'filename' => 'Filename',
			'description' => null,
			'pdf' => [
				'url' => 'http://test.coop'
			]
		], Http::STATUS_OK);
		$this->assertEquals($expected, $actual);
	}
}
