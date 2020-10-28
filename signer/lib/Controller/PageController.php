<?php

namespace OCA\Signer\Controller;

use OC\Config;
use OCA\Signer\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\EmptyContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller
{
    /** @var IConfig */
    private $config;
    public function __construct(IRequest $request, IConfig $config)
    {
        parent::__construct(Application::APP_ID, $request);
        $this->config = $config;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * Render default template
     */
    public function index()
    {
        Util::addScript(Application::APP_ID, 'signer-main');

        $response = new TemplateResponse(Application::APP_ID, 'main');
        
        if ($this->config->getSystemValue('debug')) {
            $csp = new ContentSecurityPolicy();
            $csp->allowInlineScript(true);
            $response->setContentSecurityPolicy($csp);
        }

        return $response;
    }
}
