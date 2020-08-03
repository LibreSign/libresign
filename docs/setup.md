# Instalação do ambiente

Para rodar o projeto pela primeira vez, crie um arquivo `.env` com base no `.env.dist` adaptando as variáveis de acordo com seu cenário. As variáveis disponiveis são:

|         Variável          |     Default     |                         Descrição                          |
| ------------------------- | --------------- | ---------------------------------------------------------- |
| POSTGRES_PASSWORD         | SECRET_PASSWORD | senha do banco                                             |
| POSTGRES_DB               | nextcloud       | nome do banco                                              |
| POSTGRES_USER             | nextcloud       | usuário do banco                                           |
| NEXTCLOUD_ADMIN_USER      | admin           | username do primeiro administrador do NextCloud            |
| NEXTCLOUD_ADMIN_PASSWORD  | admin           | username do primeiro administrador do NextCloud            |
| NEXTCLOUD_TRUSTED_DOMAINS | mydomain.coop   | dominio local da aplicação                                 |
| VIRTUAL_HOST              | mydomain.coop   | dominio local da aplicação, para a geração de certificados |
| LETSENCRYPT_HOST          | mydomain.coop   | dominio local da aplicação, para a geração de certificados |

Realizado as etapas acima, basta rodar o Makefile, o que pode ser feito executando o comando `make` na raiz do projeto.
