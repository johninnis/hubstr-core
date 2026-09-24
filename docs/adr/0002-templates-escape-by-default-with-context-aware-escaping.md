# 2. Templates escape by default, with context-aware escaping

## Status

Accepted

## Context

The services render HTML pages — landing and error pages — from templates, with values some of which originate from untrusted input: a site name, an owner key, an error message echoed from a request. If a value is interpolated into HTML verbatim, an attacker who controls it can inject markup and script — a cross-site scripting vulnerability.

The first question is which default protects the caller. A renderer that interpolates raw by default is unsafe by default: every safe use requires the caller to remember to escape, and a single forgotten escape is a vulnerability whose failure mode is silent — the output looks correct in testing and is exploitable in production. Escaping everything by default inverts that: the only failure mode of forgetting is over-escaped output, which is visible and harmless.

The second question is what "escaping" must mean. The cheap answer is a home-grown placeholder renderer that runs every value through a single `htmlspecialchars` call, with a wrapper type as the raw-HTML opt-out. Single-context escaping is the weakness of that design: HTML text, attribute values, URLs and script blocks each have their own injection rules, and one HTML-escape treats them identically — a `javascript:` URL survives HTML-escaping inside an `href` intact. Closing that gap means tracking the syntactic position of every placeholder, which is rebuilding a template engine.

## Decision

Templating is delegated to [Latte](https://latte.nette.org/), reached through the `TemplateRendererInterface` port and wired by `LatteTemplateRenderer`.

- Latte escapes every interpolated value **by default**; there is no unescaped interpolation to forget your way into.
- Escaping is **context-aware**: a value is escaped for the position it occupies — HTML text, attribute, URL, or script — so an injection that survives one context's escaping does not survive its own.
- The single escape hatch is Latte's explicit `|noescape` filter, written in the template at the point of use. Every raw-HTML interpolation is therefore a conscious, greppable decision: the set of trust assertions in a codebase is found by searching the templates for that one filter.
- The kernel does not re-implement any escaping of its own.

## Consequences

The safe path is the default and the unsafe path is opt-in and conspicuous. Forgetting produces harmless over-escaping rather than an injection hole. URL contexts are protected in a way a single-pass HTML escape is not — the renderer's tests pin the `javascript:` URL case directly. The cost is a template-engine dependency (`latte/latte`) and the on-disk compile cache its engine needs, which `LatteTemplateRenderer::create()` provisions.
