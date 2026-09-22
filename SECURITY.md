# Security Policy

## Reporting a vulnerability

If you have found a security vulnerability in `innis/hubstr-core`, report it privately through GitHub's built-in vulnerability reporting: **Security → Advisories → Report a vulnerability** on the repository page. Do not open a public issue for a security-sensitive bug.

Include a description of the vulnerability and its impact, reproduction steps or a proof-of-concept, and the affected version (tag or commit SHA). Acknowledgement is best-effort within 72 hours. Fixes land first, then the advisory is published. This project does not run a bug bounty.

## Supported versions

Only the latest tagged release is supported. Older releases do not receive backported fixes.

## Audit status

This package has not undergone an independent security audit. It is built and reviewed with care, and the properties below are each pinned by tests, but internal review is not a substitute for an external one.

## What this package is, and why that shapes the threat model

`hubstr-core` is the runtime kernel beneath small, self-hosted services: one long-running PHP process, run by one operator, speaking plain HTTP on a loopback address behind a reverse proxy that terminates TLS, with its data in a SQLite file on the same machine. It hosts HTTP, reads a config file, opens a database and renders pages. It holds no keys and performs no cryptography; the services built on it do.

Two kinds of adversary follow from that: a remote client sending hostile requests through the proxy, and another local user on the same host.

## What the kernel guarantees

**Against a hostile request**

- A value rendered through the template port is escaped for the position it occupies — HTML text, attribute, URL or script — by default. The only way to emit raw HTML is Latte's explicit `|noescape` filter, written in the template.
- An error message reaches the client only through the template. It is never sent as the HTTP reason phrase, where a line break in it would inject response headers.
- An unhandled exception in a request handler produces the service's 500 page. The exception's text is logged and never sent to the client.
- A request body over the configured size limit is answered with `413` and the connection is closed, including when the handler reading it does not catch the error. Without the kit, amphp leaves that client waiting and the connection open.
- `X-Forwarded-For` is believed only from the configured trusted proxies, and the address taken is the one the nearest trusted proxy appended, not one the client supplied in front of it. Each trusted proxy must be an IP address or a valid CIDR block, or the service does not start.
- Every response the kit's handlers and middleware produce carries `X-Content-Type-Options: nosniff` and `Referrer-Policy: no-referrer` unless the service set its own value: a page, a static file, the 500 page for an unhandled exception, the refusal of a request method, and a response a service's own middleware produces.
- A template name cannot select a file outside the template directory.
- A log record is written as a single line; a line break in a logged message cannot forge a second record.

**Against another local user**

- A database the kernel creates is readable and writable only by its owner, from the moment it exists. An existing database the service owns has access for other users removed each time the service connects, along with its write-ahead log and shared-memory files. A process that does not own the file — an operator's account running a maintenance tool — cannot change its mode, and leaves it as it found it.

**Against operator error**

- A mistyped config value or a misspelt config key stops the service at start-up and names the key, rather than letting a default apply silently.
- A config file runs in a scope of its own and cannot see or alter the loader.
- A schema migration is applied atomically and recorded with it, or rolled back and reported by name. A broken migration sequence, an empty migration and a database newer than the code are refused before anything is applied.

## Host and operator responsibilities

These are load-bearing. The kernel does not and cannot do them for you.

1. **Terminate TLS in front of the service, and keep the service on loopback.** The kit speaks plain HTTP. `host` defaults to `127.0.0.1`; binding it to a public address exposes that to the network, without per-address connection limits. A host that must do so can at least cap its total connections, by building `HttpServerFactory` with amphp's `ConnectionLimitingServerSocketFactory` in place of the plain one. Transport security headers belong to whatever terminates TLS.
2. **Set `trusted_proxies` to your proxy and nothing wider.** It defaults to `127.0.0.1`. An entry such as `0.0.0.0/0` is accepted, because it is a valid block, and means every client's claimed address is believed — which defeats any per-address rate limiting or banning a service does. If you bind to an IPv6 address, add your proxy's IPv6 address.
3. **Decide framing and content security policy in the service.** The kit sets neither, because no value is right for every service. A service with an administrative interface should forbid framing.
4. **Apply cross-origin policy in the service**, through `HttpServerOptions`' middleware.
5. **Treat the public directory as public, all of it.** Everything in it is served, dotfiles included, and symbolic links are followed wherever they lead. Keep it to static assets and never point it at, or link it into, a directory holding anything else.
6. **Keep the template compile cache writable only by the service.** Latte executes the compiled templates it finds there. Never place it under a shared temporary directory.
7. **Protect the config file yourself.** The kernel reads it and does not create it. If a service's config holds a secret, its permissions are that service's and its operator's to set.
8. **Authenticate and rate-limit in the service.** The kit limits concurrent requests and body size, and nothing else.
9. **Do not make a lifecycle's `stop()` the only guard of durable state.** Shutdown is orderly whenever the kernel gets to run it, but a second signal, the supervisor's kill, or a signal during start-up ends the process without it.
10. **Run the service as a dedicated unprivileged user**, and give the process supervisor a stop timeout: the kernel waits for in-flight requests for as long as they take.
11. **Keep whatever reads the service's output alive for as long as the service.** Logs go to standard output only, and a record that cannot be written ends the process: at once during start-up, otherwise at its next record, and without the graceful drain when that record is the shutdown's. Do not pipe a service into a command that may exit before it does.

## Accepted trade-offs

- **The kit answers clients that have already gone.** A client error also reports a client that disconnected mid-request, so an error page is sometimes rendered for nobody. Telling the cases apart would mean matching on amphp's messages.
- **A request the HTTP driver cannot parse is answered outside the kit's stack.** For a malformed request line or host header the driver renders the service's error page before any middleware runs, so that one response carries neither baseline header. A browser does not send such a request.
- **Connection and request timeouts are amphp's defaults** and are not configurable through the kit. A client that stalls is dropped after amphp's timeout.
- **Creating a database narrows the process umask** for the duration of the open, which is process-wide state. Nothing else runs on the event loop between narrowing and restoring it. The alternative, correcting permissions after creation, would leave the file readable for a moment in which another user could open it and keep the descriptor.
- **Nothing forces a service to declare its known config keys.** A service that omits the call gets strict values and silently ignored misspelt keys.
