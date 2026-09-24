# 21. The page responders read the site identity through a provider

## Status

Accepted

## Context

`LandingPageResponder` and `ErrorPageResponder` render a service's name, version and owner onto every page. Both took a `SiteInfo` value in their constructor, so the identity a page showed was whatever the service knew when it wired its server.

For most services that is the whole story: the name is a constant, the version is fixed for the life of the process, and the owner is read from a config file at start. A value is the right shape for that, and a provider around a constant reads as ceremony.

The relay is the case that breaks it. Its name can be changed at runtime over its management API, and the same name is served in its relay-information document through a provider that reads the current value on every request. The landing page, holding the value captured at boot, kept showing the old name until the process restarted, so two surfaces the relay itself publishes disagreed about what the relay is called. No arrangement of a value fixes that; the responder has to ask at render time.

The cost of asking is one interface call per page render. The relay's implementation reads an in-memory projection and builds a small document, which is what its NIP-11 endpoint already does per request; nothing reaches a database or crosses a process.

## Decision

Both responders take a `SiteInfoProviderInterface`, a driven port beside `VersionProviderInterface`, and call `getSiteInfo()` inside `respond()`. `SiteInfo` stays an immutable value.

The kit ships `StaticSiteInfoProvider`, which wraps one `SiteInfo` and returns it unchanged, for a service whose identity never moves. A service whose identity can change supplies its own provider.

## Consequences

- A page always shows the identity the service holds at the moment it renders, so a runtime rename reaches the landing page and the error pages in the same request that reaches every other surface.
- A service with a fixed identity wraps its value in the static provider and is otherwise unaffected; the value object and the template parameters are unchanged.
- Do not capture `getSiteInfo()` in the responder's constructor to save the call. The call is the point, and it costs an in-memory read.
- Do not make `SiteInfo` mutable to reach the same end. A changing identity is expressed by a provider answering differently, never by a value that changes under its readers.
