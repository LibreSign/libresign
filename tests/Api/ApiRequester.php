<?php

namespace OCA\Libresign\Tests\Api;

use ByJG\ApiTools\AbstractRequester;
use ByJG\Util\Psr7\Response;
use GuzzleHttp\Psr7\Stream;
use OC\AppFramework\Http\Request;
use OCP\IRequest;
use OCP\IRequestId;
use org\bovigo\vfs\vfsStream;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * Request handler based on ByJG HttpClient (WebRequest)
 */
class ApiRequester extends AbstractRequester {
	/**
	 * @param RequestInterface $request
	 * @return Response|ResponseInterface
	 */
	protected function handleRequest(RequestInterface $request) {
		$this->setupRequest($request);
		$body = $this->doRequest();

		$response = Response::getInstance(http_response_code());
		$response = $response->withBody(new Stream($body));

		$headers = \xdebug_get_headers();
		foreach ($headers as $header) {
			$header = explode(': ', $header, 2);
			$response = $response->withHeader($header[0], $header[1]);
		}

		return $response;
	}

	private function doRequest() {
		ob_start();
		\OC::handleRequest();
		$handler = fopen('php://memory', 'r+');
		fwrite($handler, ob_get_contents());
		ob_end_clean();
		rewind($handler);
		return $handler;
	}

	private function setupRequest(RequestInterface $request) {
		$request = $request->withHeader("User-Agent", "ByJG Swagger Test");
		$server = [
			'REQUEST_URI' => $request->getUri()->getPath(),
			'SCRIPT_NAME' => '/index.php',
			'REQUEST_METHOD' => $request->getMethod(),
			'SERVER_PROTOCOL' => $request->getProtocolVersion(),
			'REMOTE_ADDR' => '127.0.0.1'
		];
		$_SERVER = array_merge($_SERVER, $server);
		foreach ($request->getHeaders() as $key => $value) {
			$name = strtoupper($key);
			$name = str_replace('-', '_', $name);
			$server['HTTP_' . $name] = $value[0];
		}
		if (isset($server['HTTP_AUTHORIZATION'])) {
			$auth = base64_decode(explode(' ', $server['HTTP_AUTHORIZATION'])[1]);
			[$server['PHP_AUTH_USER'], $server['PHP_AUTH_PW']] = explode(':', $auth);
		}
		parse_str($request->getUri()->getQuery(), $get);
		$vars = [
			'get' => $get,
			'files' => [],
			'server' => $server,
			'env' => $_ENV,
			'cookies' => ['cookie_test' => true],
			'method' => $server['REQUEST_METHOD'],
			'urlParams' => $get,
		];
		$stream = '';
		if ($request->getBody()) {
			if (isset($server['HTTP_CONTENT_TYPE']) && $server['HTTP_CONTENT_TYPE'] === 'application/json') {
				vfsStream::setup('home');
				$stream = vfsStream::url('home/test.txt');
				file_put_contents($stream, $request->getBody()->getContents());
			} else {
				$vars['post'] = $request->getBody()->getContents();
			}
		}
		$mockRequest = new Request(
			$vars,
			\OCP\Server::get(IRequestId::class),
			\OCP\Server::get(\OCP\IConfig::class),
			\OCP\Server::get(\OC\Security\CSRF\CsrfTokenManager::class),
			$stream
		);
		\OC::$server->registerService(IRequest::class, function () use ($mockRequest) {
			return $mockRequest;
		});
		\OC::$CLI = false;

		$router = \OCP\Server::get(\OC\Route\Router::class);
		$reflectionClass = new \ReflectionClass($router);
		$property = $reflectionClass->getProperty('context');
		$property->setAccessible(true);
		$property->setValue($router, new RequestContext(
			'/index.php',
			$server['REQUEST_METHOD'],
			$server['HTTP_HOST'],
			$request->getUri()->getScheme()
		));

		// Just to work with Nextcloud 20
		\OC::$server->registerAlias(\OCP\Route\IRouter::class, \OC\Route\Router::class);
	}
}
