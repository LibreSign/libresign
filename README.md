![Test Status](https://github.com/lyseontech/libresign/workflows/ci/badge.svg?branch=main)

# Libresign

## Setup

### With CFSS server

Up a cfssl server using this code:

https://github.com/cloudflare/cfssl

The URL of server you will use in [Admin settings](#admin-settings)

### With docker-compose
* put the file `/cfssl/entrypoint.sh` in `cfssl` folder
* Add the volume `./cfssl:/cfssl` in Nextcloud service
* Create a new server using the following code:
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

### Admin settings

Go to `Settings > Security` and fill the necessary values for root certificate:

```
CN: CommonName
OU: OrganizationalUnit
O: Organization
C: CountryName
API: http://cfssl:8888/api/v1/cfssl/
Config path: /cfssl/
```