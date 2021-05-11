![Test Status](https://github.com/lyseontech/libresign/workflows/PHPUnit/badge.svg?branch=main)
[![Coverage Status](https://coveralls.io/repos/LibreSign/libresign/badge.svg?branch=master&service=github)](https://coveralls.io/github/LibreSign/libresign?branch=master)

# LibreSign

Nextcloud app to sign PDF documents.

At the moment file signature requests must be requested via webhook. Read the documentation for more information.

<img src="img/LibreSign.png" />

## Setup

### Java and JSignPDF

Add the follow to Nextcloud PHP container Dockerfile

```Dockerfile
# Install Java and JsignPDF
RUN apt-get update # Only include this line if necessary
RUN mkdir -p /usr/share/man/man1
RUN apt-get install -y default-jre unzip
RUN curl -OL https://sourceforge.net/projects/jsignpdf/files/stable/JSignPdf%201.6.4/JSignPdf-1.6.4.zip \
    && unzip JSignPdf-1.6.4.zip -d /opt \
    && rm JSignPdf-1.6.4.zip
```

### With CFSS server

Don't is necessary if you use a docker setup

Up a cfssl server using this code:

https://github.com/cloudflare/cfssl

The URL of server you will use in [Admin settings](#admin-settings)

### With docker-compose
* Create a folder named cfssl in the same folder as your `docker-compose.yml` file. This folder will be used on one volume of the cfssl service.
* put the file [`/cfssl/entrypoint.sh`](https://github.com/LibreSign/libresign/blob/main/cfssl/entrypoint.sh) in `cfssl` folder
* Add the volume `./cfssl:/cfssl` in Nextcloud php service
* Create a new server using the following code in your `docker-compose.yml` file:
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

Go to `Settings > LibreSign` and fill the necessary values for root certificate:

```
CN: CommonName
OU: OrganizationalUnit
O: Organization
C: CountryName
API: http://cfssl:8888/api/v1/cfssl/
Config path: /cfssl/
```

Go to `Settings > Basic Settings` and configure email settings. Is mandatory.

## Integrations

* [GLPI](https://github.com/LibreSign/libresign-glpi): Plugin to sign GLPI tickets

## Full documentation

[here](https://libresign.github.io/libresign/)

## Contributing

Feel free to submit [Github Issues](https://github.com/lyseontech/libresign/issues) or pull requests.

The documentation website is build from the docs folder with vuepress.

### To start front and backend development environment

```bash
make serve
```

### To build documentation

```bash
make docs
```

### To run PHPUnit

```bash
make test
```

Read more in composer.json scripts section
