<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\Exception\LibresignException;

trait HandleParamsTrait
{
    protected function checkParams(array $params): void
    {
        foreach ($params as $key => $param) {
            if (empty($param)) {
                throw new LibresignException("parameter '{$key}' is required!", 400);
            }
        }
    }
}
