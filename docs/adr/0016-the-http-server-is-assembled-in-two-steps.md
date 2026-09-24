# 16. The HTTP server is assembled in two steps

## Status

Accepted

## Context

The natural shape for a server factory is one call: a binding and a route table in, a running server out. It reads as complete, and it has no seam.

One host needs a seam. The relay builds a websocket relay that takes the `SocketHttpServer` itself as a constructor argument, and the handler that relay produces is what the routes then dispatch to. So the relay needs the assembled socket server in its hands part-way through building the router; it cannot hand a finished route table to a factory and receive a finished server back. A kit whose only entry point is the single call gives that host nothing to use, and its only recourse is to copy the whole assembly — middleware stack, bind and all — which then drifts from the shared one.

## Decision

`HttpServerFactory` exposes the assembly as two steps, and those two steps are the only way to build a server.

`createSocketServer(HttpBinding, HttpServerOptions)` returns the `SocketHttpServer` with the shared middleware applied and the address exposed. `createServer(SocketHttpServer, RouterDefinition)` routes it and returns the `ServerInterface`. Every host calls both, in that order. A host with nothing to do in between writes the two calls back to back; the relay builds its websocket relay and its root handler between them.

The factory is built with a logger and a `ServerSocketFactory`. It constructs the rest of amphp's collaborators itself, and takes that one from the host because it is the one whose choice depends on where the host runs: a service behind a reverse proxy passes amphp's plain `ResourceServerSocketFactory`, as every shipped service does, and a host bound to a public address passes a `ConnectionLimitingServerSocketFactory` to cap the connections it will accept.

There is deliberately no third method that does both. A one-call shortcut would be a second way to build the same thing, and the host that could not use it is precisely the host that would otherwise have to fork the kit.

## Consequences

Every host pays one extra line, including those that never need the gap. That is the price of one path instead of a shared path plus an escape hatch.

The first step returns amphp's concrete server rather than a port, because the type the relay needs between the steps is that server. Nothing stops a caller passing the second step a socket server built elsewhere, without the kit's middleware; the factory trusts its caller there, since a type that prevented it would hide the very object the seam exists to expose.

Do not add a convenience method that performs both steps.
