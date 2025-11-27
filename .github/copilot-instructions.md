# LibreSign Development Guide for AI Agents

LibreSign is a Nextcloud app for digital PDF signature using personal or system-generated certificates. This guide helps AI agents understand the critical patterns and workflows specific to this codebase.

## Architecture Overview

### Core Components
- **Backend (PHP)**: Nextcloud app following OCP patterns
  - `lib/Service/`: Business logic (SignFileService, AccountService, CertificatePolicyService, etc.)
  - `lib/Handler/`: Signature engines (Pkcs12Handler, Pkcs7Handler) and certificate engines (OpenSslHandler, CfsslHandler)
  - `lib/Controller/`: API controllers inheriting from `AEnvironmentAwareController`
  - `lib/Db/`: Database entities and mappers (File, SignRequest, AccountFile, Crl)
- **Frontend (Vue 2)**: SPA with Pinia store, using `@nextcloud/vue` components
  - `src/views/`: Main view components (Validation.vue, etc.)
  - `src/store/`: Pinia stores for state management
  - `src/Components/`: Reusable Vue components
- **Integration Points**:
  - Files app integration via `FilesTemplateLoader` and sidebar listeners
  - Event system using `SignedEvent`, `SendSignNotificationEvent`
  - Activity, Notification, and Mail listeners for multi-channel notifications

### Key Architectural Patterns
- **Certificate Engines**: Factory pattern (`CertificateEngineFactory`) returns OpenSSL, CFSSL, or None handler based on config
- **Signature Engines**: `SignEngineFactory` provides Pkcs12Handler (PDF) or Pkcs7Handler (CMS) based on file type
- **Identity Methods**: Dynamic loading via namespace convention (`OCA\Libresign\Service\IdentifyMethod\*`)
- **Event-Driven**: Uses Nextcloud event dispatcher for cross-component communication (Activity, Notifications, Mail)

## Development Environment

### Docker-Based Setup
LibreSign runs within a Nextcloud Docker container. The project is located at `/home/username/projects/nextcloud/php82-master/volumes/nextcloud`.

**Container structure**:
- Main Nextcloud installation: `/var/www/html/`
- LibreSign app: `/var/www/html/apps-extra/libresign/`
- Data directory: `/var/www/html/data/`

Common commands:

```bash
# Access container shell
docker compose exec nextcloud bash

# Inside container, navigate to app
cd apps-extra/libresign

# Run commands in container context
docker compose exec nextcloud bash -c "cd apps-extra/libresign && composer test:unit -- --filter TestName"
```

### Testing Workflows

**PHPUnit Tests** (inside container):
```bash
cd apps-extra/libresign
# ⚠️ NEVER run tests without filter - always specify --filter to run specific tests
composer test:unit -- --filter ClassName     # Specific test class (REQUIRED)
composer test:unit -- --filter testMethod    # Specific test method
composer test:coverage -- --filter ClassName # With coverage for specific tests
```

**CRITICAL**: Always use `--filter` when running tests. Running all tests without filter can:
- Take excessive time
- Consume unnecessary resources
- Make debugging harder
- Clutter output

**Behat Integration Tests** (inside container):
```bash
# First time setup (run only once)
cd tests/integration
composer i
chown -R www-data: .

# Running integration tests (from libresign root directory)
cd tests/integration
runuser -u www-data -- vendor/bin/behat features/<path>.feature

# Example: Run specific feature file
cd tests/integration
runuser -u www-data -- vendor/bin/behat features/auth/login.feature
```

**Frontend Testing**:
```bash
npm test                # Jest tests
npm run test:watch      # Watch mode
npm run test:coverage   # Coverage
```

### VS Code Tasks
Pre-configured tasks for quick testing (all run inside Docker container):
- "LibreSign: Run OrderCertificatesTrait Tests" - Certificate ordering and validation tests
- "LibreSign: Access Container Shell" - Direct bash access to container
- "LibreSign: Run Tests with Coverage" - PHPUnit with coverage reports
- "LibreSign: Test Certificate Validation" - `testValidateCertificateChain` tests
- "LibreSign: Test ICP-Brasil Certificates" - `testICPBrasilRealWorldExample` tests
- "LibreSign: Test LYSEON TECH Certificate Chain" - 4-certificate chain validation
- "LibreSign: Run Specific Test Method" - Custom test method via input

Use `Tasks: Run Task` command or `Ctrl+Shift+P > Tasks: Run Task` to execute these.

**All tasks automatically run inside the Docker container** - no need to manually exec into container.

## Project-Specific Conventions

### PHP Namespace Structure
- **Services**: `OCA\Libresign\Service\*` - Business logic, dependency injection via constructor
- **Controllers**: `OCA\Libresign\Controller\*`
  - **OCS API controllers**: Extend `AEnvironmentAwareController` (for OCS routes)
  - **Regular controllers**: Follow standard Nextcloud controller pattern (extend `Controller`)
- **Handlers**: `OCA\Libresign\Handler\*` - Specialized processors (SignEngine, CertificateEngine)
- **Db**: `OCA\Libresign\Db\*` - Entities extend `Entity`, Mappers extend `QBMapper`

### Frontend Architecture
- **Vue 2** with Composition API patterns via `@vueuse/core`
- **Pinia stores** for state (not Vuex)
- **Router**: `src/router/router.js` defines SPA routes
- **OpenAPI integration**: TypeScript types generated from OpenAPI spec via `npm run typescript:generate`

### Critical Files
- `lib/Service/SignFileService.php`: Core signature orchestration (846 lines)
- `lib/Handler/SignEngine/SignEngineHandler.php`: Abstract base for signature engines
- `lib/AppInfo/Application.php`: Bootstrap, event listener registration, middleware
- `appinfo/info.xml`: App metadata, dependencies, commands, background jobs

### Database & Migrations
- Migrations in `lib/Migration/Version*.php` (date-based naming)
- Use `QBMapper` for queries, avoid raw SQL

### Certificate Revocation List (CRL)
- CRL management via `lib/Service/CrlService.php`
- Database table: `libresign_crl` stores revocation information
- Serial number validation is critical for CRL operations
- Use `occ libresign:crl:*` commands for CRL operations

## Build & Release Process

### Development
```bash
make dev-setup          # Install deps (composer + npm)
npm run watch           # Frontend hot reload
npm run dev             # Build frontend once
```

### Production Build
```bash
make build-js-production  # Webpack production build
composer cs:fix           # Format PHP code
composer psalm            # Static analysis
```

### Linting & Formatting
- **PHP**: `.php-cs-fixer.dist.php` config, run `composer cs:fix`
- **PHP Static Analysis**: `psalm.xml` config, run `composer psalm`
- **JavaScript**: ESLint via `eslint.config.mjs`, run `npm run lint:fix`
- **CSS**: Stylelint via `stylelint.config.js`, run `npm run stylelint:fix`

### OpenAPI Workflow
```bash
composer openapi    # Generate OpenAPI spec from PHP annotations
npm run typescript:generate  # Generate TypeScript types from spec
```
**Pattern**: PHP controllers use `@OpenAPI` annotations → spec generation → TS types

## Testing Patterns

### PHPUnit Structure
- `tests/php/Unit/`: Unit tests with mocked dependencies
- `tests/php/Api/`: API integration tests
- `tests/php/Integration/`: Full integration scenarios
- Fixtures in `tests/php/fixtures/` (e.g., `small_valid.pdf`)

### Mocking Convention
Use PHPUnit `createMock()` for dependencies:
```php
$this->mockService = $this->createMock(ServiceClass::class);
$this->mockService->method('methodName')->willReturn($value);
```

### Certificate Testing
Special test cases for certificate chain validation:
- `testValidateCertificateChain`: Generic chain validation
- `testICPBrasilRealWorldExample`: ICP-Brasil specific
- `testLyseonTechRealWorldExample`: 4-cert chain example (LYSEON TECH)
- `OrderCertificatesTraitTest`: Tests for certificate ordering and chain validation

**Important**: Certificate chains must be ordered from end-entity to root. The `OrderCertificatesTrait` handles automatic ordering.

Run via: `docker compose exec nextcloud bash -c "cd apps-extra/libresign && composer test:unit -- --filter testName"`

## Common Pitfalls

### Testing Without Filters
**🚨 CRITICAL**: NEVER run `composer test:unit` without `--filter` parameter
- Always specify which test class or method to run
- Running all tests is slow, resource-intensive, and unnecessary for development
- Example: `composer test:unit -- --filter ServiceNameTest`

### Docker Context Awareness
- **Always run tests inside container**: Host environment lacks Nextcloud dependencies
- **File paths**: Use absolute paths inside container (`/var/www/html/apps-extra/libresign/`)
- **Database**: Container uses SQLite by default; postgres/mysql for CI

### Nextcloud OCP Updates
Run `make updateocp` after pulling Nextcloud server changes to sync OCP interfaces.

### Composer Autoload
After adding new classes:
```bash
composer dump-autoload -o
```
Autoloader suffix: `Libresign` (see `composer.json`)

### Frontend Build Issues
- **Node version**: Requires Node 20.x (`engines` in `package.json`)
- **Missing types**: Re-run `npm run typescript:generate` after OpenAPI changes
- **Webpack cache**: Clear `node_modules/.cache` if seeing stale builds

## Debugging Tips

### PHP Debugging
```php
\OCP\Server::get(\Psr\Log\LoggerInterface::class)->debug('Message', ['context' => $data]);
```
Logs appear in `data/nextcloud.log` inside container.

### Frontend Debugging
```javascript
import logger from './logger.js'
logger.debug('Debug info', { data })
```

### Test Debugging
```bash
# ⚠️ ALWAYS use --filter when debugging tests
# Run single test with verbose output
vendor/bin/phpunit -c tests/php/phpunit.xml --filter testMethodName --testdox

# Run specific test class
vendor/bin/phpunit -c tests/php/phpunit.xml --filter ClassName --testdox
```

**Never run tests without specifying --filter**: This is a development requirement to maintain performance and focus.

## Integration Points

### Files App
- Sidebar: `LoadSidebarListener` registers tab
- Template loader: `FilesTemplateLoader::register()` in `Application::boot()`
- File actions: `JSActions` helper defines file menu items

### Notifications
Multi-channel notification flow:
1. `SendSignNotificationEvent` dispatched
2. Listeners: `NotificationListener`, `MailNotifyListener`, `ActivityListener`, `TwofactorGatewayListener`
3. Each listener handles notification in its domain

### Background Jobs
- `OCA\Libresign\BackgroundJob\Reminder`: Sends signature reminders
- `OCA\Libresign\BackgroundJob\UserDeleted`: Cleanup on user deletion
- Registered in `appinfo/info.xml`

## Commands (OCC)

```bash
# Configuration
occ libresign:configure:check       # Verify setup
occ libresign:configure:cfssl       # Setup CFSSL engine
occ libresign:configure:openssl     # Setup OpenSSL engine

# CRL Management
occ libresign:crl:stats             # CRL statistics
occ libresign:crl:cleanup           # Clean old CRLs
occ libresign:crl:revoke <serial>   # Revoke certificate

# Development
occ libresign:developer:reset       # Reset dev environment
occ libresign:developer:sign-setup  # Sign setup files for app store

# Installation
occ libresign:install --all         # Install signing binaries
occ libresign:uninstall             # Remove binaries
```

## Workflow Examples

### Adding a New Service
1. Create `lib/Service/NewService.php` extending no base (use DI)
2. Add constructor with typed dependencies (auto-wired)
3. Implement business logic methods
4. Register in controller constructor if needed
5. Add unit tests in `tests/php/Unit/Service/NewServiceTest.php`

### Adding API Endpoint
1. Add method to controller in `lib/Controller/`
2. Use `@OpenAPI` annotations for spec generation
3. Run `composer openapi` to update spec
4. Run `npm run typescript:generate` for frontend types
5. Implement frontend service call in `src/services/`

### Certificate Chain Debugging
Use test data approach:
```php
$pemChain = [
    file_get_contents(__DIR__ . '/fixtures/cert1.pem'),
    file_get_contents(__DIR__ . '/fixtures/cert2.pem'),
    // ... ordered from end-entity to root
];
$service->validateCertificateChain($pemChain);
```
Certificate order matters: end-entity → intermediate(s) → root.

---

**Key Principle**: LibreSign integrates tightly with Nextcloud's architecture (OCP, event system, Files app). Always consider Nextcloud patterns and test in the full container environment.
