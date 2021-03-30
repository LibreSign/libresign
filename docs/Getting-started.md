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

## Webhook

### webhook/register

#### Request body

| Parameter | Type          | Description                        |
| --------- | ------------- | ---------------------------------- |
| file      | File          | File to sign                       |
| users     | array of User | List of users to sign file         |
| name      | string        | **optional** Name for file         |
| callback  | string        | Callback url when a user sign file |

Parameters of File object

| Parameter | Type   | Description                     |
| --------- | ------ | ------------------------------- |
| url       | string | **optional** Public URL of file |
| base64    | string | **optional** File in base64     |

Parameters of User object

| Parameter    | Type   | Description                         |
| ------------ | ------ | ----------------------------------- |
| email        | string | Email of user                       |
| display_name | string | **optional** Display name of user   |
| description  | string | **optional** Description to signers |

#### Response

##### 200 Success

```json
{
    "message": "Success"
}
```

##### 403 Forbidden

A 403 response might be returned if the users ability to access webhook has been disabled by the administrator.

```json
{
    "message": "Insufficient permissions to use API"
}
```