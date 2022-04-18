![Test Status](https://github.com/libresign/libresign/workflows/PHPUnit/badge.svg?branch=main)
[![Coverage Status](https://coveralls.io/repos/github/LibreSign/libresign/badge.svg?branch=main)](https://coveralls.io/github/LibreSign/libresign?branch=main)
[![Start contributing](https://img.shields.io/github/issues/LibreSign/libresign/good%20first%20issue?color=7057ff&label=Contribute)](https://github.com/LibreSign/libresign/issues?q=is%3Aissue+is%3Aopen+sort%3Aupdated-desc+label%3A%22good+first+issue%22)

Nextcloud app to sign PDF documents.

<img src="img/LibreSign.png" />

**Table of contents**
- [Releases compatibility](#releases-compatibility)
- [Setup](#setup)
  - [Java and JSignPDF](#java-and-jsignpdf)
    - [Standalone](#standalone)
    - [Using Docker](#using-docker)
  - [CFSSL](#cfssl)
    - [CFSS server handmade install](#cfss-server-handmade-install)
    - [With docker-compose](#with-docker-compose)
  - [Admin settings](#admin-settings)
  - [Validation page](#validation-page)
- [Integrations](#integrations)
- [Full documentation](#full-documentation)
- [Contributing](#contributing)

## Releases compatibility

| LibreSign | Nextcloud | JSignPDF |
| --------- | --------- | -------- |
| 5.x       | 24        | 2.1.0    |
| 4.x       | 23        | 2.1.0    |
| 3.x       | 22        | 2.1.0    |
| 2         | 22        | 1.6.5    |

## Setup

### Java and JSignPDF

#### Standalone
Run commands:
```bash
occ libresign:install --all
occ libresign:configure:cfssl --cn=<yourCN> --ou=<yourOU> --o=<yourO> --c=<yourCountry>
```
#### Using Docker
Add the follow to Nextcloud PHP container Dockerfile

```Dockerfile
# Install Java and JsignPDF
RUN apt-get update # Only include this line if necessary
RUN mkdir -p /usr/share/man/man1
RUN apt-get install -y default-jre unzip
RUN curl -OL https://sourceforge.net/projects/jsignpdf/files/stable/JSignPdf%202.1.0/jsignpdf-2.1.0.zip \
    && unzip jsignpdf-2.1.0.zip -d /opt \
    && rm jsignpdf-2.1.0.zip
```

### CFSSL
#### CFSS server handmade install

Don't is necessary if you use a docker setup or if you did the standalone setup

Up a cfssl server using this code:

https://github.com/cloudflare/cfssl

The URL of server you will use in [Admin settings](#admin-settings)

> **PS**: Use latest version, on many cases the version of package manage of linux distro is outdated and incompatible with LibreSign

#### With docker-compose
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

Don't is necessary if you did the standalone setup

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

### Validation page

The validation can be done on a page without access restriction informing the file UUID or the file to be validated.

| Route | Description |
|---|---|
| /apps/libresign/validation | Document validation home page. On this page it is possible to send a binary or enter a file UUID for validation. |
| /apps/libresign/validation/<uuid> | Enter the UUID of the file in the URL and go straight to the page informing the signature data.  |

To have a short URL at the bottom of the document, you can add these directives to your Nginx. Replace domains with those for your application.

```nginx
server {
    listen 80;
    server_name validate.yourdomain.coop;
    location ~ "\/(?<uuid>[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12})" {
        rewrite ^ $scheme://cloud.yourdomain.coop/apps/libresign/validation/$uuid;
    }
    location ~ / {
        return 301 $scheme://cloud.yourdomain.coop/apps/libresign/validation;
    }
}
```

With this configuration when accessing `validate.yourdomain.coop/b9809a7e-ab6e-11eb-bcbc-0242ac130002` you will be redirected to `cloud.yourdomain.coop/apps/libresign/validation/b9809a7e-ab6e-11eb-bcbc-0242ac130002`

## Integrations

* [GLPI](https://github.com/LibreSign/libresign-glpi): Plugin to sign GLPI tickets
* [Approval](https://github.com/nextcloud/approval): Approve/reject files based on workflows defined by admins

## Full documentation

[here](https://libresign.github.io/)

## Contributing

[here](/CONTRIBUTING.md)
