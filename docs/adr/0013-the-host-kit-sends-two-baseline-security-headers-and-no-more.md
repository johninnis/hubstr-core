# 13. The host kit sends two baseline security headers, and no more

## Status

Accepted

## Context

The kit builds every response a service sends — pages, static files, error pages, whatever a service's own handlers return. A response carries headers a browser uses to decide how far to trust it, and if the kit sets none, each service has them only where its operator's reverse proxy happens to add them: a protection that exists in one example proxy configuration is one the services deployed any other way do not have.

The question is which headers a shared kit can decide for every service, because the rest of the usual list cannot be decided here. Framing is the clear case: the signer's panel should never be framed, and blossom serves media whose whole purpose is to be embedded, so `X-Frame-Options` or a `frame-ancestors` policy set by the kit would be wrong for one of them whichever way it went. A content security policy depends on what a page loads, and transport security belongs to whatever terminates TLS, which is never this process.

## Decision

The kit's outermost middleware gives every response two headers, unless the service has already set its own value:

- `X-Content-Type-Options: nosniff`, so a browser uses the content type it was given. A service that serves anything a user influenced — and blossom serves users' uploads — must not have a browser decide for itself that a text file is a script.
- `Referrer-Policy: no-referrer`, so a link followed from a service's page does not carry that page's address to the site it leads to.

Both are correct for a page, a static file, a JSON document and a websocket upgrade alike, which is the test a header must pass to be decided here. The middleware is outermost so that a response produced further in carries them too — one a service's own middleware answers with without reaching a handler, as the relay's does for a CORS preflight, and every error page: the 500 for an unhandled exception and the refusal of a request method are produced by middleware the kit stacks beneath this one, not by the server outside it (see [0020](0020-the-kit-answers-every-error-a-handler-raises-inside-its-own-stack.md)). A test sends each kind of response through a real server and checks the headers arrive. Framing, content security policy and transport security stay with the service and its proxy, as CORS does (see [0005](0005-core-provides-the-shared-http-host-kit.md)).

## Consequences

Every service has the two headers without asking, wherever it is deployed, and a service that wants a different value sets it and is left alone. Do not extend the list with a header that is right for most services: the kit cannot tell which service it is in, and a default that is wrong for one of them is a default that service must remember to undo.

The one response written before any middleware runs — the driver's answer to a request it could not parse, such as a malformed request line — renders the service's error page and carries neither header. The two headers protect a browser, and a browser does not send a request the driver cannot parse.
