# Prequisited

- All requests require a `Content-Type` of `application/json`.
- The API is located at https://nextcloud.local/index.php/apps/libresign/api/v1.0
- All request parameters are required, unless otherwise specified

# Headers

Read https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Authorization

```bash
curl -X POST \
  http://localhost/index.php/apps/libresign/api/0.1/webhook/register \
  -H 'Accept: application/json' \
  -H 'Authorization: Basic YWRtaW46YWRtaW4=' \
  -H 'Content-Type: application/json'
```

# Endpoints

## Webhook

### webhook/register

#### Request body

| Parameter | Type          | Description                        |
| --------- | ------------- | ---------------------------------- |
| file      | string        | File to sign base64 encoded        |
| users     | array of User | List of users to sign file         |
| callback  | string        | Callback url when a user sign file |

Parameters of User object

| Parameter | Type   | Description                    |
| --------- | ------ | ------------------------------ |
| email     | string | Email of user                  |
| firstName | string | **optional** Fist name of user |
| fullName  | string | **optional** Full name of user |

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