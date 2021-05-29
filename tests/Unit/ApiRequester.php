<?php

namespace OCA\Libresign\Tests\Unit;

use ByJG\ApiTools\AbstractRequester;
use ByJG\Util\Psr7\Response;
use GuzzleHttp\Psr7\Stream;
use OC\AppFramework\Http\Request;
use OCP\IRequest;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Request handler based on ByJG HttpClient (WebRequest)
 */
class ApiRequester extends AbstractRequester
{
    /**
     * @param RequestInterface $request
     * @return Response|ResponseInterface
     */
    protected function handleRequest(RequestInterface $request)
    {
        $this->setupRequest($request);
        $body = $this->doRequest();

        $response = Response::getInstance(http_response_code());
        $response = $response->withBody(new Stream($body));

        $headers = xdebug_get_headers();
        foreach ($headers as $header) {
            $header = explode(': ', $header, 2);
            $response = $response->withHeader($header[0], $header[1]);
        }

        return $response;
    }

    private function doRequest() {
        ob_start();
        \OC::handleRequest();
        $handler = fopen('php://memory','r+');
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
            'SERVER_PROTOCOL' => $request->getProtocolVersion()
        ];
        $_SERVER = array_merge($_SERVER, $server);
        foreach ($request->getHeaders() as $key => $value) {
            $name = strtoupper($key);
            $name = str_replace('-', '_', $name);
            $server['HTTP_' . $name] = $value[0];
        }
        if (isset($server['HTTP_AUTHORIZATION'])) {
            $auth = base64_decode(explode(' ', $server['HTTP_AUTHORIZATION'])[1]);
            list($server['PHP_AUTH_USER'], $server['PHP_AUTH_PW']) = explode(':', $auth);
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
        $mockRequest = new Request(
            $vars,
            \OC::$server->get(\OCP\Security\ISecureRandom::class),
            \OC::$server->get(\OCP\IConfig::class),
            \OC::$server->get(\OC\Security\CSRF\CsrfTokenManager::class),
            $stream
        );
        \OC::$server->registerService(IRequest::class, function () use ($mockRequest) {
            return $mockRequest;
        });
        \OC::$CLI = false;
        $log = \OC::$server->get(\OC\Log::class);
        $router = new \OC\Route\Router($log);
        \OC::$server->registerService(\OC\Route\Router::class, function () use ($router) {
            return $router;
        });
    }
}
