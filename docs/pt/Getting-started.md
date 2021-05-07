# Pré-requisitos

- Todas as requisições precisam ter `Content-Type` com `application/json`.
- A API é acesívem em https://nextcloud.local/index.php/apps/libresign/api/v1.0
- Todos os parâmetros são necesários exceto se for especificado que não são.

# Cabeçalhos

Leia https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Authorization

Exemplo:

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

[Documetnação da Api](api)