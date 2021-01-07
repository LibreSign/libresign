<?php

namespace OCA\Signer\Settings;

use OCA\Signer\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\BackgroundJob\IJobList;
use OCP\IAppConfig;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings
{
    public function getForm()
    {
        return new TemplateResponse(Application::APP_ID, 'settings');
    }

    public function getSection()
    {
        return 'security';
    }

    public function getPriority()
    {
        return 00;
    }
}
