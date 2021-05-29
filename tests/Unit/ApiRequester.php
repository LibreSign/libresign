<?php

namespace OCA\Libresign\Tests\Unit;

use ByJG\ApiTools\AbstractRequester;
use Psr\Http\Message\RequestInterface;

/**
 * Request handler based on ByJG HttpClient (WebRequest)
 */
class ApiRequester extends AbstractRequester
{
    /**
     * @param RequestInterface $request
     */
    protected function handleRequest(RequestInterface $request)
    {
    }
}
