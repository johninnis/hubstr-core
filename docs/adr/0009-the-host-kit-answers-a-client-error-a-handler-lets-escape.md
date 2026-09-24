# 9. The host kit answers a client error a handler lets escape

## Status

Accepted

## Context

amphp enforces a request-body size limit inside its HTTP driver. When a request handler reads a body past that limit, the driver does not answer the client. It fails the body stream with a `ClientException` carrying 413, which surfaces inside the handler at the read — and if the handler does not catch it, amphp rethrows it on purpose: its own exception middleware names `ClientException` and `HttpErrorException` as the two things it will not handle. No response is written. The client, having sent its whole request, waits on a socket that says nothing until a timeout somewhere gives up.

amphp's position is coherent: a `ClientException` usually means the client has gone away or is misbehaving, the handler is the one holding the stream, so the handler decides. The driver only answers for itself when no handler is pending.

That leaves every handler that reads a body needing the same `try`/`catch`. A handler that reads with a bare `buffer()` and does not catch is the ordinary way to write one, and a service that must read the body before it can authenticate the request — because a signature covers it — exposes the hang to any client, authenticated or not. When every service needs the same four lines for the same reason, the omission belongs to the thing they all compose, not to them.

## Decision

`HttpServerFactory::createServer()` wraps the router in `EscapedErrorMiddleware`. A `ClientException` that escapes a handler is answered exactly as amphp's driver answers one when no handler is pending: the exception's code as the status when it is an HTTP client or server error status and 400 otherwise, its message as the reason, rendered through the service's own `ErrorHandler`, on a response marked `Connection: close`.

The same middleware answers an `HttpErrorException` a handler throws on purpose, with the status and reason it carries, on a connection left open. The driver would answer it too, but outside the kit's stack (see [0020](0020-the-kit-answers-every-error-a-handler-raises-inside-its-own-stack.md)); answering it here is what keeps the kit's headers and a service's own middleware on that response.

It wraps the router rather than joining the socket server's middleware stack, because it needs the service's error handler and that arrives with the `RouterDefinition` in the second factory call. The socket-level middleware — a service's CORS included — therefore still sees and decorates the error response on its way out.

The connection is closed after a client error because the unread remainder of the body is still on the socket; reusing the connection would parse those bytes as the next request.

A handler remains free to catch either exception itself and answer differently. The middleware is the floor, not the only path.

## Consequences

An over-limit body gets a 413 through the service's error page from every service built on the kit, with nothing added to the services. The behaviour is pinned by an integration test whose handler deliberately does not catch, and whose client read times out rather than hangs, so a regression fails the suite instead of stalling it.

Do not remove the middleware on the grounds that amphp "already handles" client errors — for a pending handler it deliberately does not — and do not replace it with a `try`/`catch` in each service's handlers, which is the repetition this exists to prevent.

The cost is a response written to clients that have already disconnected: a `ClientException` also reports a client that went away mid-request, and the kit will render an error page nobody reads. The write fails harmlessly on the closed socket. Telling the two cases apart would mean matching on amphp's status codes and messages, which buys nothing and breaks when they change.
