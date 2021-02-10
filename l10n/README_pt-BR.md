[<img src="../img/en_US.svg" width="30">](../README.md) [<img src="../img/pt_BR.svg" width="30">](./l10n/README_pt-BR.md)


![Test Status](https://github.com/lyseontech/libresign/workflows/PHPUnit/badge.svg?branch=main)

# Libresign

App Nextcloud para assinar documentos PDF

## Setup

### Usando um Servidor CFSS

Abra um servidor cfssl usando este código:

https://github.com/cloudflare/cfssl

O URL do servidor que você usará no [Admin settings](#admin-settings)

### Usando docker-compose
* Copie o arquivo  `/cfssl/entrypoint.sh` para a pasta `cfssl` na raiz do projeto.
* Dentro do seu serviço Nextcloud Adicione no volumes `./cfssl:/cfssl`
* Crie um novo serviço usando as configurações abaixo:
```yml
  cfssl:
    image: cfssl/cfssl
    volumes:
      - ./cfssl:/cfssl
    working_dir: /cfssl
    entrypoint: /bin/bash
    command:
      - /cfssl/entrypoint.sh
```

### Configurações de Administrador

Vá para `Settings > Security` e preencha os valores necessários para poder gerar o certificado raiz:

```
CN: CommonName
OU: OrganizationalUnit
O: Organization
C: CountryName
API: http://cfssl:8888/api/v1/cfssl/
Config path: /cfssl/
```
