# 0020. The kit answers every error a handler raises inside its own stack

## Status

Accepted

## Context

The kit stacks middleware in front of every response a service sends (see [0005](0005-core-provides-the-shared-http-host-kit.md)), and two records depend on that stack seeing every response: the baseline security headers are added by its outermost entry ([0013](0013-the-host-kit-sends-two-baseline-security-headers-and-no-more.md)), and a service's own middleware, its cross-origin headers above all, decorates error responses on their way out ([0009](0009-the-host-kit-answers-a-client-error-a-handler-lets-escape.md)).

amphp produces three responses outside any middleware a host gives it. Its server wraps the whole stack in an `AllowedMethodsMiddleware` of its own, so the refusal of a request method never enters the host's stack. Its HTTP driver catches what a handler throws — an `HttpErrorException` a handler throws on purpose, and any other throwable — and answers through the error handler directly; the catch for the latter is documented in the driver as a last resort, with the instruction to use `ExceptionHandlerMiddleware` instead. The 500 page for an unhandled exception is the error page a service produces most, and relying on the last resort meant it was the first to reach a client without the headers the kit says every response carries, and without a service's cross-origin headers.

## Decision

Every error a handler raises is answered inside the kit's own middleware stack, and nothing the kit can answer is left to the server outside it.

`HttpServerFactory::createSocketServer()` builds the `SocketHttpServer` with request-method filtering switched off. `createServer()` then wraps the router, innermost to outermost, in:

- amphp's `AllowedMethodsMiddleware`, given the kit's `HttpMethod` cases, so a method the kit does not route is refused with 405 or 501 through the service's error handler;
- `EscapedErrorMiddleware`, which answers a `ClientException` a handler let escape and an `HttpErrorException` a handler threw (see [0009](0009-the-host-kit-answers-a-client-error-a-handler-lets-escape.md));
- amphp's `ExceptionHandlerMiddleware` with its `DefaultExceptionHandler`, which logs any other throwable, message and all, and answers with the service's 500 page and none of the exception's text.

They wrap the router rather than joining the socket server's list because each needs the service's error handler, which arrives with the `RouterDefinition` in the second factory call (see [0016](0016-the-http-server-is-assembled-in-two-steps.md)). Every response they produce therefore passes out through the service's middleware and the kit's own, and the outermost entry is outermost in fact.

One response remains outside: the driver's answer to a request it could not parse — a malformed request line, a missing or repeated host header — which it writes before any handler exists to run middleware for.

## Consequences

The 500 page, the refusal of a method, a thrown HTTP error and an escaped client error all carry the baseline headers and a service's own, and the kit's integration tests send each of them through a real server to prove it. An unhandled exception's message reaches the log and not the client, and that is pinned too.

Do not restore the server's default method filtering as a simplification, and do not remove `ExceptionHandlerMiddleware` on the grounds that the driver "already" answers an unhandled exception: both put a response back outside the stack, and the headers and cross-origin decoration silently stop applying to exactly the responses that matter most.

The cost is one more line in the factory and the knowledge that amphp's defaults were deliberately declined. The parse-failure response is accepted as it is: the two headers protect a browser, and a browser does not send a request the driver cannot parse.
