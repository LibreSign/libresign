# Prequisited

- All requests require a `Content-Type` of `application/json`.
- The API is located at https://nextcloud.local/index.php/apps/libresign/api/v1.0
- All request parameters are required, unless otherwise specified

# Headers

Read https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Authorization

Example:

```bash
curl -X POST \
  http://localhost/index.php/apps/libresign/api/0.1/webhook/register \
  -H 'Accept: application/json' \
  -H 'Authorization: Basic YWRtaW46YWRtaW4=' \
  -H 'Content-Type: application/json'
  -d '{
	"file": {
		"url": "https://test.coop/test.pdf"
	},
	"name": "test",
	"callback": "https://test.coop/callbackWebhook",
	"users": [
		{
			"display_name": "Jhon Doe",
			"email": "jhondoe@test.coop",
			"description": "Lorem ipsum"
		}
	]
}'
```

# Endpoints

[Api Documentation](api)