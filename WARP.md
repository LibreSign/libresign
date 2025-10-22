# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## About LibreSign

LibreSign is a Nextcloud app for digitally signing PDF documents using digital certificates. It's a self-hosted document signer with support for multiple signers, QR code validation, and full REST API integration.

## Development Commands

### Setup and Dependencies

```bash
# Full development setup (installs all dependencies)
make dev-setup

# Install/update Composer dependencies only
composer install --prefer-dist

# Install/update npm dependencies only
npm ci
```

### Building

```bash
# Build production JavaScript bundle
npm run build
# or
make build-js-production

# Build development JavaScript bundle
npm run dev
# or
make build-js

# Watch mode for development
npm run watch
# or
make watch-js

# Dev server with hot reload
npm run serve
```

### Linting and Code Quality

```bash
# PHP linting
composer run lint

# PHP code style check
composer run cs:check

# PHP code style auto-fix
composer run cs:fix

# Psalm static analysis
composer run psalm

# Update Psalm baseline
composer run psalm:update-baseline

# JavaScript/TypeScript linting
npm run lint
# or
make lint

# JavaScript/TypeScript lint auto-fix
npm run lint:fix
# or
make lint-fix

# CSS/SCSS linting
npm run stylelint
# or
make stylelint

# CSS/SCSS lint auto-fix
npm run stylelint:fix
```

### Testing

```bash
# Run PHP unit tests
composer run test:unit
# or
vendor/bin/phpunit -c tests/php/phpunit.xml

# Run PHP tests with coverage
composer run test:coverage

# Run integration tests (Behat)
# See .github/workflows/behat-*.yml for setup requirements

# Run JavaScript tests (Jest)
npm run test

# Jest watch mode
npm run test:watch

# Jest with coverage
npm run test:coverage

# TypeScript type checking
npm run typescript:check
```

### OpenAPI

```bash
# Generate OpenAPI spec and TypeScript types
composer run openapi
```

## Architecture Overview

### Backend (PHP - Nextcloud App)

LibreSign follows standard Nextcloud app architecture:

- **Entry point**: `lib/AppInfo/Application.php` - Registers services, event listeners, middleware, commands, and capabilities
- **Controllers** (`lib/Controller/`): Handle HTTP requests, following Nextcloud's controller patterns
  - Public controllers for external signature flows (e.g., `SignFileController`)
  - Admin controllers for configuration
  - Account management controllers
- **Services** (`lib/Service/`): Core business logic layer
  - `SignFileService.php`: Document signing operations
  - `RequestSignatureService.php`: Signature request workflows
  - `AccountService.php`: User account management
  - `FileService.php`: File handling and PDF operations
  - `CertificateService/`: Digital certificate management
  - `IdentifyMethodService.php`: Identity verification methods
- **Database** (`lib/Db/`): Entity mappers for database operations
- **Events** (`lib/Events/`): Custom domain events (e.g., `SignedEvent`, `SendSignNotificationEvent`)
- **Listeners** (`lib/Listener/`): Event handlers for various app events
- **Middleware** (`lib/Middleware/`): Request/response processing
- **Commands** (`lib/Command/`): CLI commands for administration and development
- **Handlers** (`lib/Handler/`): Specialized handlers for certificates, PDFs, etc.

### Frontend (Vue.js 2)

Built with Vue 2.7, Vue Router, and Pinia for state management:

- **Entry points** (`src/`):
  - `init.js`: Main app initialization
  - `external.js`: External/public signature flows
  - `settings.js`: Admin settings interface
  - `tab.js`: Files app tab integration
  - `validation.js`: Document validation flows
- **Routing** (`src/router/router.js`): Vue Router with history mode
  - Public routes: `/p/sign/:uuid/*` for external signers
  - Internal routes: `/f/*` for authenticated users
  - Lazy-loaded components for code splitting
- **State Management** (`src/store/`): Multiple Pinia stores
  - `sign.js`: Signature process state
  - `files.js`: File management
  - `sidebar.js`: Sidebar state
  - `signatureElements.js`: Visual signature elements
  - `configureCheck.js`: Configuration validation
- **Components** (`src/Components/`): Reusable Vue components
- **Views** (`src/views/`): Page-level components
  - `SignPDF/`: PDF signing interface
  - `Account/`: User account management
  - `FilesList/`: Document list views
  - `Request.vue`: Request signature workflow
  - `Validation.vue`: Document validation
- **Services** (`src/services/`): API client services
- **Domains** (`src/domains/`): Domain-specific logic and models

### Build System

- Webpack 5 with `@nextcloud/webpack-vue-config`
- ESBuild for fast transpilation
- Multiple entry points for different app contexts
- Code splitting and lazy loading enabled
- Dev server on port 3000 for hot reload

## Key Patterns and Conventions

### Namespace and Autoloading

- PHP namespace: `OCA\Libresign\`
- PSR-4 autoloading: `lib/` directory maps to namespace
- Third-party dependencies in `3rdparty/` with separate namespace

### Event-Driven Architecture

The app heavily uses Nextcloud's event system:
- Custom events dispatched for signing operations (`SignedEvent`, `SendSignNotificationEvent`)
- Event listeners handle notifications, activity logging, callbacks
- See `lib/AppInfo/Application.php` `register()` method for event bindings

### API Structure

- OpenAPI specs in root: `openapi.json`, `openapi-administration.json`, `openapi-full.json`
- API controllers use OpenAPI attributes for automatic documentation
- TypeScript types auto-generated from OpenAPI specs

### Testing Structure

- **PHP Unit tests**: `tests/php/Unit/` and `tests/php/Api/`
- **Integration tests**: `tests/integration/` (Behat)
- **JavaScript tests**: Configure Jest as needed (scripts defined in `package.json`)

### Code Quality Standards

- PHP CS Fixer with Nextcloud coding standard
- Psalm level 5 for static analysis
- ESLint with Nextcloud config for JavaScript/Vue
- Stylelint for CSS/SCSS

## Development Tips

### Running Single PHP Test

```bash
vendor/bin/phpunit -c tests/php/phpunit.xml tests/php/Unit/Path/To/YourTest.php
```

### Working with OpenAPI

After modifying API endpoints:
1. Add OpenAPI annotations to controller methods
2. Run `composer run openapi` to regenerate specs and TypeScript types
3. Frontend types will be updated automatically

### Hot Module Replacement

Use `npm run serve` for development with HMR. The dev server runs on port 3000 and proxies to your Nextcloud instance.

### Nextcloud Integration

This app runs within a Nextcloud instance. The development environment assumes:
- Nextcloud installed at `../../` or `../nextcloud/`
- App is symlinked or located in Nextcloud's `apps/` or `apps-extra/` directory
- Use `php ../../occ` (or adjust path) for CLI commands

### CLI Commands

```bash
# Check configuration
php occ libresign:configure:check

# Install signing dependencies (CFSSL, binaries)
php occ libresign:install --all

# Reset development environment
php occ libresign:developer:reset
```

## Important Notes

- **PHP Version**: Requires PHP 8.2+
- **Node Version**: Requires Node.js 20+, npm 10+
- **Nextcloud Version**: Currently targets Nextcloud 33
- **Architecture Support**: x86_64 and aarch64
- **License**: AGPL-3.0-or-later

## External Documentation

- [Official Documentation](https://docs.libresign.coop/)
- [API Guide](https://libresign.github.io/)
- [Development Setup](https://docs.libresign.coop/developer_manual/getting-started/development-environment/setup)
- [GitHub Repository](https://github.com/LibreSign/libresign)
