<?php

return [
	'routes' => [
		// API
		['name' => 'api#preflighted_cors',            'url' => '/api/0.1/{path}', 'verb' => 'OPTIONS', 'requirements' => ['path' => '.+'], ],

		// Account
		['name' => 'account#createToSign',              'url' => '/api/0.1/account/create/{uuid}', 'verb' => 'POST'],
		['name' => 'account#me',                        'url' => '/api/0.1/account/me', 'verb' => 'GET'],
		['name' => 'account#updateSettings',            'url' => '/api/0.1/account/settings', 'verb' => 'PATCH'],
		['name' => 'account#signatureGenerate',         'url' => '/api/0.1/account/signature', 'verb' => 'POST'],
		['name' => 'account#addFiles',                  'url' => '/api/0.1/account/files', 'verb' => 'POST'],
		['name' => 'account#deleteFile',                'url' => '/api/0.1/account/files', 'verb' => 'DELETE'],
		['name' => 'account#accountFileListToOwner',    'url' => '/api/0.1/account/files', 'verb' => 'GET'],
		['name' => 'account#accountFileListToApproval', 'url' => '/api/0.1/account/files/approval/list', 'verb' => 'GET'],
		['name' => 'account#createSignatureElement',    'url' => '/api/0.1/account/signature/elements', 'verb' => 'POST'],
		['name' => 'account#getSignatureElements',      'url' => '/api/0.1/account/signature/elements', 'verb' => 'GET'],
		['name' => 'account#getSignatureElement',       'url' => '/api/0.1/account/signature/elements/{elementId}', 'verb' => 'GET'],
		['name' => 'account#patchSignatureElement',     'url' => '/api/0.1/account/signature/elements/{elementId}', 'verb' => 'PATCH'],
		['name' => 'account#deleteSignatureElement',    'url' => '/api/0.1/account/signature/elements/{elementId}', 'verb' => 'DELETE'],

		// Account
		['name' => 'signRequest#request',                         'url' => '/api/0.1/sign/request', 'verb' => 'POST'],
		['name' => 'signRequest#updateSign',                      'url' => '/api/0.1/sign/request', 'verb' => 'PATCH'],
		['name' => 'signRequest#deleteAllSignRequestUsingFileId', 'url' => '/api/0.1/sign/file_id/{fileId}', 'verb' => 'DELETE'],
		['name' => 'signRequest#deleteOneSignRequestUsingFileId', 'url' => '/api/0.1/sign/file_id/{fileId}/{fileUserId}', 'verb' => 'DELETE'],

		['name' => 'signFile#signUsingUuid',                   'url' => '/api/0.1/sign/uuid/{uuid}', 'verb' => 'POST'],
		['name' => 'signFile#signUsingFileId',                 'url' => '/api/0.1/sign/file_id/{fileId}', 'verb' => 'POST'],
		['name' => 'signFile#getCodeUsingUuid',                'url' => '/api/0.1/sign/uuid/{uuid}/code', 'verb' => 'POST'],
		['name' => 'signFile#getCodeUsingFileId',              'url' => '/api/0.1/sign/file_id/{fileId}/code', 'verb' => 'POST'],

		['name' => 'File#Save',              'url' => '/api/0.1/file', 'verb' => 'POST'],
		['name' => 'File#list',              'url' => '/api/0.1/file/list', 'verb' => 'GET'],
		['name' => 'File#validateUuid',      'url' => '/api/0.1/file/validate/uuid/{uuid}', 'verb' => 'GET'],
		['name' => 'File#validateFileId',    'url' => '/api/0.1/file/validate/file_id/{fileId}', 'verb' => 'GET'],
		['name' => 'File#getPage',           'url' => '/api/0.1/file/page/{uuid}/{page}.png', 'verb' => 'GET'],

		['name' => 'FileElement#post',       'url' => '/api/0.1/file-element/{uuid}', 'verb' => 'POST'],
		['name' => 'FileElement#patch',      'url' => '/api/0.1/file-element/{uuid}/{elementId}', 'verb' => 'PATCH'],
		['name' => 'FileElement#delete',      'url' => '/api/0.1/file-element/{uuid}/{elementId}', 'verb' => 'DELETE'],

		['name' => 'notify#signers',                  'url' => '/api/0.1/notify/signers', 'verb' => 'POST'],

		// Settings
		['name' => 'setting#hasRootCert',             'url' => '/api/0.1/setting/has-root-cert', 'verb' => 'GET'],

		// Admin config
		['name' => 'admin#generateCertificate',       'url' => '/api/0.1/admin/certificate', 'verb' => 'POST'],
		['name' => 'admin#loadCertificate',           'url' => '/api/0.1/admin/certificate', 'verb' => 'GET'],
		['name' => 'admin#downloadBinaries',          'url' => '/api/0.1/admin/download-binaries', 'verb' => 'GET'],
		['name' => 'admin#downloadStatus',            'url' => '/api/0.1/admin/download-status', 'verb' => 'GET'],
		['name' => 'admin#configureCheck',            'url' => '/api/0.1/admin/configure-check', 'verb' => 'GET'],

		// Pages - restricted
		['name' => 'page#index',                      'url' => '/', 'verb' => 'GET'],
		['name' => 'page#index',                      'url' => '/f/', 'verb' => 'GET'],
		['name' => 'page#index',                      'url' => '/f/{path}', 'verb' => 'GET', 'requirements' => ['path' => '.+'], 'postfix' => 'front'],
		['name' => 'page#getPdfUser',                 'url' => '/pdf/user/{uuid}', 'verb' => 'GET'],
		['name' => 'page#resetPassword',              'url' => '/reset-password', 'verb' => 'GET'],
		// Pages - public
		['name' => 'page#sign',                       'url' => '/p/sign/{uuid}', 'verb' => 'GET'],
		['name' => 'page#sign',                       'url' => '/p/sign/{uuid}/{path}', 'verb' => 'GET', 'requirements' => ['path' => '.+'], 'postfix' => 'extra'],
		['name' => 'page#signAccountFile',            'url' => '/p/account/files/approve/{uuid}', 'verb' => 'GET'],
		['name' => 'page#signAccountFile',            'url' => '/p/account/files/approve/{uuid}/{path}', 'verb' => 'GET', 'requirements' => ['path' => '.+'], 'postfix' => 'extra'],
		['name' => 'page#validation',                 'url' => '/p/validation/{uuid}', 'verb' => 'GET'],
		['name' => 'page#validationFileWithShortUrl', 'url' => '/validation/{uuid}', 'verb' => 'GET'],
		['name' => 'page#validationFile',             'url' => '/p/validation/{uuid}', 'verb' => 'GET'],
		['name' => 'page#getPdf',                     'url' => '/p/pdf/{uuid}', 'verb' => 'GET']
	],
];
