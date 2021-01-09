<?php

return [
    'routes' => [
        ['name' => 'api#preflighted_cors', 'url' => '/api/0.1/{path}',
            'verb' => 'OPTIONS', 'requirements' => ['path' => '.+'], ],
        ['name' => 'libresign#sign', 'url' => '/api/0.1/sign', 'verb' => 'POST'],
        ['name' => 'signature#generate', 'url' => '/api/0.1/signature/generate', 'verb' => 'POST'],
        ['name' => 'signature#check', 'url' => '/api/0.1/signature/check', 'verb' => 'GET'],
        ['name' => 'admin#generateCertificate', 'url' => '/api/0.1/admin/certificate', 'verb' => 'POST'],
        ['name' => 'admin#loadCertificate', 'url' => '/api/0.1/admin/certificate', 'verb' => 'GET'],
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
    ],
];
