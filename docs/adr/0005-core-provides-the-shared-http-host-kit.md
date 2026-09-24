# 5. Core provides the shared HTTP host kit

## Status

Accepted

## Context

Every service built on this kernel is an amphp HTTP host, and each needs the same scaffolding: an adapter from the server to the kernel's `ServerInterface`, values describing a route and a set of routes, a factory that assembles the socket server, its middleware, the router and the static-file fallback, an error handler that renders error pages, and responders for the error and landing pages. Scaffolding copied into each service drifts: a fix to the compression pattern, the forwarded-address handling or the bind has to be made once per service, and is not.

The services are not uniform in every respect. The relay serves one root handler for `GET` and `POST /` rather than a route table, runs a scheduler beside the HTTP server, and takes the name and version on its pages from its NIP-11 document rather than from static configuration. Blossom answers cross-origin preflights with per-route responses; the relay answers them in a middleware that short-circuits `OPTIONS`. Those are deliberate, service-specific designs, not drift to be flattened.

## Decision

The kernel provides an HTTP host kit holding the parts the services share, in two layers.

The server assembly is infrastructure, in `Infrastructure/Http`: `AmphpHttpServer` (the `ServerInterface` adapter), `Route` and `RouterDefinition`, and `HttpServerFactory` with an `HttpServerOptions` value (a concurrency limit, a body-size limit that defaults to amphp's own, and extra middleware; it refuses a concurrency limit below one and a negative body-size limit, as `HttpBinding` refuses a port out of range), along with the middleware the factory itself stacks. How the factory exposes that assembly is its own decision (see [0016](0016-the-http-server-is-assembled-in-two-steps.md)).

Page rendering is presentation, in `Presentation/Http`: `ErrorPageResponder` (driven by `TemplatedErrorHandler`) and `LandingPageResponder`. They render what a person sees, which is what the presentation layer is for, and it is where every service files the same kind of class — its own responders, error handlers and page renderers. Both responders render from a `SiteInfo`, the site's name, version and owner, so the seam that varies between services is the identity data and not the responder: the relay maps its NIP-11 document into a `SiteInfo` and reuses them.

Which layer a class belongs to is settled by who constructs it as much as by what it does. The middleware the factory stacks handles HTTP interaction, and a service would file its like under presentation; but the factory constructs it, and infrastructure may not depend on presentation, so it stays beside the factory. The page classes are constructed only by a service's composition root, which may reach every layer, so nothing holds them in infrastructure.

What sits in the infrastructure folder is decided by what a type names, not by who uses it. `Route`, `RouterDefinition` and `HttpServerOptions` carry amphp types — a request handler, an error handler, middleware — so they are infrastructure. `SiteInfo`, `HttpBinding` and `HttpMethod` name no technology: they are plain values and a plain classification, and live in the domain beside the configuration values. A service's presentation layer builds a `SiteInfo` and names an `HttpMethod` without importing the kernel's infrastructure.

Cross-origin handling is deliberately not in the kit. The services apply it through different strategies, and one configurable middleware covering both would be an abstraction serving no shared truth. A service injects its own through `HttpServerOptions`.

## Consequences

The host assembly exists once, and every service composes it: a fix to the compression pattern, the forwarded-address handling or the bind is a fix for all of them. The error and landing responders exist once each, and their behaviour — the standard-reason fallback, the HTML response, the template context — is verified in one place. Putting the seam at the `SiteInfo` data rather than at a responder interface is what makes that possible; a port there would leave each service re-implementing the response around its own source of identity.

The kernel takes on the `amphp/http-server` family as direct dependencies. The boundary is "assembly, error handling and page rendering are shared; routing shape, cross-origin handling and page content are the service's own". A service whose needs diverge extends the seam here rather than forking the assembly.

Do not move `SiteInfo` or `HttpMethod` beside the classes that use them for being "theirs": a value is filed by what it is. Do not move the factory's middleware to presentation to sit with the responders: the dependency would then point outward.
