# Hubstr Core

[![CI](https://github.com/johninnis/hubstr-core/actions/workflows/ci.yml/badge.svg)](https://github.com/johninnis/hubstr-core/actions/workflows/ci.yml)

The shared runtime kernel beneath the Hubstr services.

Hubstr is a set of small, self-hosted [Nostr](https://nostr.com) services, each a single long-running PHP process on [amphp](https://amphp.org) with its data in SQLite: [`hubstr-relay`](https://github.com/johninnis/hubstr-relay) (a personal relay), [`hubstr-blossom`](https://github.com/johninnis/hubstr-blossom) (a Blossom media server) and [`hubstr-signer`](https://github.com/johninnis/hubstr-signer) (a NIP-46 remote signer). This package is what they have in common: how a service reads its config, opens and migrates its database, logs, renders a page, hosts HTTP, and shuts down cleanly.

It carries no Nostr or Blossom logic. Those live in the `innis/nostr-*` libraries and in the services. It is published because the services depend on it, and you can build your own amphp service on it, but it is deliberately opinionated — amphp, SQLite, Latte templates, a PHP config file, logs on standard output — and makes no attempt to be a general framework.

**Status:** pre-1.0. A minor version may change the public API.

## Features

- **[Configuration](#configuration)** — one PHP file, read through strict typed values; a mistyped value or a misspelt key stops the service at start-up and names the key.
- **[Database and migrations](#database-and-migrations)** — a SQLite connection with the settings that matter under concurrency, a file only its owner can read, and numbered migrations applied atomically.
- **[HTTP host](#http-host)** — the amphp server assembly the services share, with the middleware, error handling and page rendering that go with it.
- **[Lifecycle and shutdown](#lifecycle-and-shutdown)** — start, wait for a signal, drain in-flight work, stop; and the same sequence when something fails.
- **[Logging](#logging)** — PSR-3, to standard output, without blocking the event loop.
- **[Templating and version reporting](#templating-and-version-reporting)** — Latte with context-aware escaping, and the installed version for a page footer.

## Requirements

Declared in `composer.json`:

- PHP 8.4 or higher, on a POSIX system
- `ext-pdo_sqlite` (the database)
- `ext-pcntl` (trapping `SIGINT` and `SIGTERM` for shutdown)
- `ext-zlib` (response compression)
- the amphp v3 family (the event loop, the HTTP server, its router and static-file handler, and the non-blocking log handler)
- `latte/latte` (templating), `monolog/monolog` (the PSR-3 logger)

## Installation

```bash
composer require innis/hubstr-core
```

## Quick Start

A whole service: config, logging, a migrated database, an HTTP host with a landing page, static files and templated error pages, and a clean shutdown on Ctrl-C. This is the body of [`examples/serve_site.php`](examples/serve_site.php), less its imports; run that file to see it work.

```php
$values = new ConfigLoader('HUBSTR_CORE_EXAMPLE_CONFIG')->load(__DIR__.'/config/site.php');
$values->rejectUnknownKeys('site_name', 'owner_npub', 'template_cache_path', ...ServiceRuntimeConfig::KEYS);
$runtime = ServiceRuntimeConfig::fromValues($values);
$binding = $runtime->getBinding();

$logger = new LoggerFactory(getStdout(), $runtime->getLogLevel())->create('example');

$database = SqliteDatabase::atPath($runtime->getDatabasePath())->connect();
new SchemaMigrator($database)->migrate(__DIR__.'/resources/migrations');
$database->exec("INSERT INTO starts (started_at) VALUES (strftime('%s', 'now'))");

$renderer = LatteTemplateRenderer::create(__DIR__.'/templates', $values->string('template_cache_path'));
$site = new StaticSiteInfoProvider(new SiteInfo($values->string('site_name'), new ComposerVersionProvider()->getVersion(), $values->optionalString('owner_npub')));

$landingPage = new LandingPageResponder('index.latte', $renderer, $site);
$errorHandler = new TemplatedErrorHandler(new ErrorPageResponder('error.latte', $renderer, $site));

$definition = new RouterDefinition(
    [new Route(HttpMethod::Get, '/', static fn (Request $request): Response => $landingPage->respond())],
    $errorHandler,
    __DIR__.'/public',
);

$factory = new HttpServerFactory($logger, new ResourceServerSocketFactory());
$socketServer = $factory->createSocketServer($binding, HttpServerOptions::create(concurrencyLimit: 64));
$server = $factory->createServer($socketServer, $definition);

new Kernel($logger, new AmphpShutdownSignal())->run($server, 'Serving the example site', [
    'url' => sprintf('http://%s:%d', $binding->getHost(), $binding->getPort()),
    'database' => $runtime->getDatabasePath(),
    'start' => $database->lastInsertId(),
]);
```

## Configuration

A service is configured by one PHP file that returns an array. `ConfigLoader` is built with the name of an environment variable that may point it at a different file, and `load()` returns a `ConfigValues`.

```php
$values = new ConfigLoader('MY_SERVICE_CONFIG')->load(__DIR__.'/config/service.php');
```

`ConfigValues` reads strictly, with no coercion: `string`, `optionalString`, `int`, `optionalInt`, `optionalBool`, `optionalStringList`, and `section` for a nested array. An integer is an `int`, never `'8080'`. A default is written where it is used:

```php
$workers = $values->optionalInt('worker_pool_limit') ?? 0;
$limits = $values->section('limits')->optionalInt('max_filters') ?? 5;
```

`ServiceRuntimeConfig::fromValues()` builds the part every service shares, from these keys:

| Key               | Required | Default         | Meaning                                                   |
|-------------------|----------|-----------------|-----------------------------------------------------------|
| `database_path`   | yes      |                 | The SQLite database file                                  |
| `port`            | yes\*    |                 | Port to bind to, 1–65535                                  |
| `host`            | no       | `127.0.0.1`     | IP address to bind to; a hostname is refused              |
| `trusted_proxies` | no       | `['127.0.0.1']` | Addresses or CIDR blocks whose `X-Forwarded-For` to trust |
| `log_level`       | no       | `info`          | `debug` to `emergency`, in any case                       |

\* A service may pass its own default port to `fromValues()`.

A service reads its own keys from the same `ConfigValues`, and declares every key it knows, so a misspelt one is an error rather than a silently applied default (see [ADR-0018](docs/adr/0018-an-unknown-config-key-is-an-error-and-the-known-keys-are-listed.md)):

```php
$values->rejectUnknownKeys('relay_url', 'admin_pubkey', ...ServiceRuntimeConfig::KEYS);
```

A missing file throws `ConfigFileNotFoundException`. A bad value or an unknown key throws `InvalidArgumentException` naming the key, by its full path inside a section (`limits.max_filters`).

## Database and migrations

`SqliteDatabase` names a database; `connect()` opens a PDO handle with write-ahead journalling, a busy timeout, foreign keys and the cache settings applied. It is a value, so it can be serialised and sent to a worker process, which connects there.

```php
$database = SqliteDatabase::atPath($runtime->getDatabasePath());   // or SqliteDatabase::inMemory()
$pdo = $database->connect();
```

A database it creates is readable only by its owner, and an existing one the service owns has access for other users removed each time it connects, because a service's database may hold secrets. Group access you have granted is left alone (see [ADR-0012](docs/adr/0012-a-database-file-is-closed-to-other-users.md)).

A schema is a directory of migrations, applied by `SchemaMigrator` (see [ADR-0010](docs/adr/0010-a-schema-evolves-through-numbered-migrations-tracked-in-user-version.md)):

```
resources/migrations/0001-initial-schema.sql
resources/migrations/0002-add-expiry-to-sessions.sql
```

- Files are named `NNNN-description.sql` and numbered contiguously from `0001`.
- Each is applied once, in order, in its own transaction. The version reached is kept in SQLite's `user_version`, and a failed migration rolls back completely and names the file.
- A migration must not contain `BEGIN`, `COMMIT` or `ROLLBACK`.
- Never edit a migration that has shipped: a database that ran it will not run it again. A schema change is a new file.
- A gap, a duplicate number, an empty file and a database newer than the code are all refused before anything is applied. There are no down-migrations.

## HTTP host

`HttpServerFactory` builds a server in two calls, so that a host which needs the socket server while building its own handlers — the relay does, for its websocket — works between them instead of forking the assembly (see [ADR-0016](docs/adr/0016-the-http-server-is-assembled-in-two-steps.md)).

```php
$socketServer = $factory->createSocketServer($binding, HttpServerOptions::create(concurrencyLimit: 64));
$server = $factory->createServer($socketServer, new RouterDefinition($routes, $errorHandler, $publicDirectory));
```

A `Route` is an `HttpMethod`, a pattern and a handler. Anything no route matches is served from the public directory, and anything not found there goes to the error handler.

Every response passes through the kit's middleware, which:

- limits concurrent requests to `HttpServerOptions`' limit;
- believes `X-Forwarded-For` only from the configured trusted proxies;
- compresses compressible responses, and never a `text/event-stream` (see [ADR-0006](docs/adr/0006-an-event-stream-is-never-compressed.md));
- sends `X-Content-Type-Options: nosniff` and `Referrer-Policy: no-referrer`, unless the service set its own (see [ADR-0013](docs/adr/0013-the-host-kit-sends-two-baseline-security-headers-and-no-more.md));
- answers a client error a handler lets escape — a body over the size limit, above all — with the right status through the service's error handler, where amphp alone would leave the client waiting (see [ADR-0009](docs/adr/0009-the-host-kit-answers-a-client-error-a-handler-lets-escape.md));
- answers an unhandled exception with the service's 500 page, logging the exception and sending none of its text to the client, and refuses a request method it does not route, both inside its own stack so that the headers above and a service's middleware apply to every error page (see [ADR-0020](docs/adr/0020-the-kit-answers-every-error-a-handler-raises-inside-its-own-stack.md)).

`ErrorPageResponder` (through `TemplatedErrorHandler`) and `LandingPageResponder` render pages from a `SiteInfo`: the site's name, version and owner. They read it through a `SiteInfoProviderInterface` on every render, so a service whose name can change at runtime shows the current one; a service with a fixed identity wraps its value in `StaticSiteInfoProvider` (see [ADR-0021](docs/adr/0021-the-page-responders-read-the-site-identity-through-a-provider.md)). An error message reaches the client only through the template, never the status line (see [ADR-0014](docs/adr/0014-an-error-message-reaches-the-client-only-through-the-template.md)).

The kit leaves to each service what differs between them: its routes, cross-origin handling, framing and content security policy, and the content of its pages. Extra middleware goes in through `HttpServerOptions`.

## Lifecycle and shutdown

`Kernel::run()` starts the server, starts the optional `LifecycleInterface`, logs a banner and waits for `SIGINT` or `SIGTERM`. It then stops the server — which drains in-flight work through the lifecycle's `drain()` as it stops — and calls the lifecycle's `stop()` (see [ADR-0007](docs/adr/0007-the-lifecycle-drains-through-the-server-stop-callback.md)).

```php
new Kernel($logger, new AmphpShutdownSignal(), $lifecycle)->run($server, 'Relay started', ['port' => 8080]);
```

Once the server has started, that shutdown always runs: if the lifecycle fails to start, or the server's own stop throws, the server and the lifecycle are still stopped and the failure propagates. A `LifecycleInterface` implementation must therefore tolerate `drain()` and `stop()` after a `start()` that failed.

The kernel waits for in-flight requests for as long as they take, and leaves the deadline to the process supervisor. A second signal, the supervisor's kill, or a signal that arrives during start-up ends the process at once, so `stop()` must never be the only thing protecting durable state.

## Logging

```php
$logger = new LoggerFactory(getStdout(), $runtime->getLogLevel())->create('relay');
```

A service writes plain single-line records to its standard output, through a handler that does not block the event loop, and keeps no log file (see [ADR-0011](docs/adr/0011-a-service-logs-to-standard-output-only-without-blocking.md)). Whatever runs the process — systemd's journal, a container runtime — keeps and rotates the log.

## Templating and version reporting

`TemplateRendererInterface`, implemented by `LatteTemplateRenderer`, renders a named [Latte](https://latte.nette.org/) template with context-aware escaping: a value is escaped for the position it occupies, whether HTML text, an attribute, a URL or a script (see [ADR-0002](docs/adr/0002-templates-escape-by-default-with-context-aware-escaping.md)). `LatteTemplateRenderer::create()` takes the template directory and a directory for the compile cache. Keep that cache somewhere only the service can write: Latte executes what it finds there.

`VersionProviderInterface`, implemented by `ComposerVersionProvider`, reports the version of the installed root package — the service — with a short commit reference appended to a development version.

## Error handling

Faults are thrown. `HubstrException` is the abstract root, and its `final` leaves cover a missing config file, migrations that cannot be read or do not form a sequence, a migration that failed, a database newer than the code, and a directory that cannot be created. A service roots its own faults at `HubstrException` too, so one `catch (HubstrException)` scopes to the kernel and the services built on it, and never to a Nostr library's faults (see [ADR-0001](docs/adr/0001-root-faults-at-an-independent-base-exception.md)).

A malformed config value or an unknown key is an operator error met at start-up, and throws `InvalidArgumentException` naming the key.

## Security

What the kernel guarantees, what it leaves to the service and its operator, and how to report a vulnerability are set out in [SECURITY.md](SECURITY.md).

## Examples

```bash
php examples/render_template.php   # context-aware escaping through the template port
php examples/serve_site.php        # the Quick Start above, on http://127.0.0.1:8080
```

`serve_site.php` reads [`examples/config/site.php`](examples/config/site.php), and `HUBSTR_CORE_EXAMPLE_CONFIG` points it at another file. Both write only under the package's own `var/example/`.

## Architecture

Clean architecture, with dependencies pointing inward.

- **Domain** — the value objects (`ConfigValues`, `ServiceRuntimeConfig`, `HttpBinding`, `SiteInfo`, `PackageVersion`), the `LogLevel` and `HttpMethod` enums, and the fault hierarchy.
- **Application** — the ports a host or the infrastructure implements (`ServerInterface`, `LifecycleInterface`, `ShutdownSignalInterface`, `TemplateRendererInterface`, `VersionProviderInterface`, `SiteInfoProviderInterface`) and the `Kernel` that orchestrates them.
- **Infrastructure** — by concern: `Config/`, `Filesystem/`, `Http/` (the server assembly), `Logging/`, `Persistence/`, `Process/`, `Templating/`, `Version/`.
- **Presentation** — `Http/`: the error and landing page rendering.

## Architecture decisions

Design rationale lives in [`docs/adr/`](docs/adr/) as sequentially-numbered Architecture Decision Records — read these before "correcting" a choice that reads like a smell. Each record states the context, the decision, and what it forbids; the filenames are the index.

## Testing

```bash
composer test          # PHPUnit (Unit and Integration) and PHPStan at level 9
composer check-style   # php-cs-fixer, dry run
composer check-rector  # Rector's PHP 8.4 modernisation check
```

## Licence

MIT
