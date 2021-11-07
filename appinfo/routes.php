<?php

return [
	'routes' => [
		// API
		['name' => 'api#preflighted_cors',            'url' => '/api/0.1/{path}', 'verb' => 'OPTIONS', 'requirements' => ['path' => '.+'], ],

		// Deprecated
		['name' => 'accountDeprecated#me',            'url' => '/api/0.1/webhook/me', 'verb' => 'GET'],

		['name' => 'account#createToSign',            'url' => '/api/0.1/account/create/{uuid}', 'verb' => 'POST'],
		['name' => 'account#me',                      'url' => '/api/0.1/account/me', 'verb' => 'GET'],
		['name' => 'account#signatureGenerate',       'url' => '/api/0.1/account/signature', 'verb' => 'POST'],
		['name' => 'account#addFiles',                'url' => '/api/0.1/account/files', 'verb' => 'POST'],
		['name' => 'account#accountFileList',            'url' => '/api/0.1/account/files/approval/list', 'verb' => 'GET'],
		['name' => 'account#createSignatureElement',  'url' => '/api/0.1/account/signature/elements', 'verb' => 'POST'],
		['name' => 'account#getSignatureElements',    'url' => '/api/0.1/account/signature/elements', 'verb' => 'GET'],
		['name' => 'account#getSignatureElement',     'url' => '/api/0.1/account/signature/elements/{elementId}', 'verb' => 'GET'],
		['name' => 'account#patchSignatureElement',   'url' => '/api/0.1/account/signature/elements/{elementId}', 'verb' => 'PATCH'],
		['name' => 'account#deleteSignatureElement',  'url' => '/api/0.1/account/signature/elements/{elementId}', 'verb' => 'DELETE'],

		// Deprecated
		['name' => 'signFileDeprecated#requestSign',  'url' => '/api/0.1/webhook/register', 'verb' => 'POST'],
		['name' => 'signFileDeprecated#updateSign',   'url' => '/api/0.1/webhook/register', 'verb' => 'PATCH'],
		['name' => 'signFileDeprecated#removeSign',   'url' => '/api/0.1/webhook/register/signature', 'verb' => 'DELETE'],
		['name' => 'signFileDeprecated#sign',         'url' => '/api/0.1/sign', 'verb' => 'POST'],

		['name' => 'signFile#requestSign',            'url' => '/api/0.1/sign/register', 'verb' => 'POST'],
		['name' => 'signFile#updateSign',             'url' => '/api/0.1/sign/register', 'verb' => 'PATCH'],
		['name' => 'signFile#signUsingUuid',          'url' => '/api/0.1/sign/uuid/{uuid}', 'verb' => 'POST'],
		['name' => 'signFile#signUsingFileid',        'url' => '/api/0.1/sign/file_id/{fileId}', 'verb' => 'POST'],
		['name' => 'signFile#deleteAllSignRequestUsingFileId', 'url' => '/api/0.1/sign/file_id/{fileId}', 'verb' => 'DELETE'],
		['name' => 'signFile#deleteOneSignRequestUsingFileId', 'url' => '/api/0.1/sign/file_id/{fileId}/{signatureId}', 'verb' => 'DELETE'],

		['name' => 'libreSignFile#list',              'url' => '/api/0.1/file/list', 'verb' => 'GET'],
		['name' => 'libreSignFile#validateUuid',      'url' => '/api/0.1/file/validate/uuid/{uuid}', 'verb' => 'GET'],
		['name' => 'libreSignFile#validateFileId',    'url' => '/api/0.1/file/validate/file_id/{fileId}', 'verb' => 'GET'],
		['name' => 'libreSignFile#getPage',           'url' => '/api/0.1/file/page/{uuid}/{page}.png', 'verb' => 'GET'],
		['name' => 'libreSignFile#postElement',       'url' => '/api/0.1/file/{uuid}/elements', 'verb' => 'POST'],
		['name' => 'libreSignFile#patchElement',      'url' => '/api/0.1/file/{uuid}/elements/{elementId}', 'verb' => 'PATCH'],
		['name' => 'libreSignFile#deletelement',      'url' => '/api/0.1/file/{uuid}/elements/{elementId}', 'verb' => 'DELETE'],

		['name' => 'notify#signers',                  'url' => '/api/0.1/notify/signers', 'verb' => 'POST'],

		// Deprecated
		['name' => 'settingDeprecated#hasRootCert',   'url' => '/api/0.1/signature/has-root-cert', 'verb' => 'GET'],

		['name' => 'setting#hasRootCert',             'url' => '/api/0.1/setting/has-root-cert', 'verb' => 'GET'],
		// Admin config
		['name' => 'admin#generateCertificate',       'url' => '/api/0.1/admin/certificate', 'verb' => 'POST'],
		['name' => 'admin#loadCertificate',           'url' => '/api/0.1/admin/certificate', 'verb' => 'GET'],

		// Pages - restricted
		['name' => 'page#index',                      'url' => '/', 'verb' => 'GET'],
		['name' => 'page#index',                      'url' => '/f/{path}', 'verb' => 'GET', 'requirements' => ['path' => '.+'], 'postfix' => 'front'],
		['name' => 'page#getPdfUser',                 'url' => '/pdf/user/{uuid}', 'verb' => 'GET'],
		['name' => 'page#resetPassword',              'url' => '/reset-password', 'verb' => 'GET'],
		// Pages - public
		['name' => 'page#sign',                       'url' => '/p/sign/{uuid}', 'verb' => 'GET'],
		['name' => 'page#sign',                  	  'url' => '/p/sign/{uuid}/{path}', 'verb' => 'GET', 'requirements' => ['path' => '.+'], 'postfix' => 'extra'],
		['name' => 'page#validation',                 'url' => '/p/validation', 'verb' => 'GET'],
		['name' => 'page#validationFile',             'url' => '/p/validation/{uuid}', 'verb' => 'GET'],
		['name' => 'page#getPdf',                     'url' => '/p/pdf/{uuid}', 'verb' => 'GET']
	],
];
