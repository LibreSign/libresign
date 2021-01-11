<?php

namespace OCA\Libresign\Controller;

use OC\Security\CSP\ContentSecurityPolicy;
use OCA\Libresign\AppInfo\Application;
use OCP\AppFramework\Controller;
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
        Util::addScript(Application::APP_ID, 'libresign-main');

        $response = new TemplateResponse(Application::APP_ID, 'main');
        
        if ($this->config->getSystemValue('debug')) {
            $csp = new ContentSecurityPolicy();
            $csp->setInlineScriptAllowed(true);
            $response->setContentSecurityPolicy($csp);
        }

        return $response;
    }
}
