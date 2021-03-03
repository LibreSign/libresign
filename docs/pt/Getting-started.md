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

## Webhook

### webhook/register

#### Corpo da requisição

| Parâmetro | Tipo          | Descrição                                                           |
| --------- | ------------- | ------------------------------------------------------------------- |
| file      | File          | Arquivo para assinar                                                |
| users     | array of User | Lista de pessoas que irão assinar                                   |
| name      | string        | **optional** Nome do arquivo a ser assinado                         |
| callback  | string        | URL de callback chamada quando todas as pessoas assinarem o arquivo |

Parâmetros do objeto File

| Parâmetro | Tipo   | Descrição                           |
| --------- | ------ | ----------------------------------- |
| url       | string | **optional** URL pública do arquivo |
| base64    | string | **optional** Arquivo em base64      |

Parâmetros do objeto User

| Parâmetro    | Tipo   | Descrição                                    |
| ------------ | ------ | -------------------------------------------- |
| email        | string | Email do usuário                             |
| display_name | string | **optional** Nome de exibição do usuário     |
| description  | string | **optional** Descrição para quem irá assinar |

#### Respostas

##### 200 Sucesso

```json
{
    "message": "Success"
}
```

##### 4xx Forbidden

Respostas `4xx` podem ser retornadas sempre que houverem erros ao procesar a requisição.

Exemplo:
```json
{
    "message": "Insufficient permissions to use API"
}
```