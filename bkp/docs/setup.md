# Instalação do ambiente

Para rodar o projeto pela primeira vez, crie um arquivo `.env` com base no `.env.dist` adaptando as variáveis de acordo com seu cenário. As variáveis disponiveis são:

|         Variável          |     Default     |                         Descrição                          |
| ------------------------- | --------------- | ---------------------------------------------------------- |
| POSTGRES_PASSWORD         | SECRET_PASSWORD | senha do banco                                             |
| POSTGRES_DB               | nextcloud       | nome do banco                                              |
| POSTGRES_USER             | nextcloud       | usuário do banco                                           |
| NEXTCLOUD_ADMIN_USER      | admin           | username do primeiro administrador do NextCloud            |
| NEXTCLOUD_ADMIN_PASSWORD  | admin           | senha do primeiro administrador do NextCloud               |
| NEXTCLOUD_TRUSTED_DOMAINS | mydomain.coop   | dominio local da aplicação                                 |

Também é preciso adicionar os certificados de SSL dentro da estrutura `./certs/default.crt` e `./certs/default.key`;

Realizado as etapas acima, basta rodar o Makefile, o que pode ser feito executando o comando `make` na raiz do projeto.
