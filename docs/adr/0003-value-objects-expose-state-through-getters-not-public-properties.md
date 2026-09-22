# 0003. Value objects expose state through getters, not public properties

## Status

Accepted

## Context

PHP 8.4 makes it idiomatic to expose a value object's state directly: a `public readonly` property for a plain carrier, a property hook for a computed read. Applied here, that would replace `SiteInfo::getVersion()` with `$siteInfo->version`, and a reviewer reaching for the modern idiom will be tempted to make exactly that change. It would take this library away from the accessor surface used across the wider ecosystem it belongs to.

The choice is not about what the language permits; it is about which surface stays stable as value objects evolve.

## Decision

Every value object exposes its state through `getX()` (or `toX()`) accessors over private properties. No public readonly state, no property hooks, no asymmetric visibility.

1. **An accessor is a stable seam; a public property is not.** Normalising a value on read, computing it, or binding it to an interface later is a non-event behind `getX()` — no call site changes. Behind a public property the same move breaks every reader. Reads that normalise or compute — an encoded key, a derived identifier, a value fixed at construction — are the norm in the ecosystem this kernel is consumed alongside, not the exception.
2. **Some reads can only be methods, so a uniform surface means all reads are methods.** A computed or interface-bound read cannot be a bare property, and a `final readonly class` cannot carry a property hook (hooks require a non-readonly property). Exposing only plain carriers as properties splits the surface into `$vo->size` beside `$vo->getHash()` — two access styles for every reader to navigate.
3. **Getter-to-property conversion is purely syntactic.** It changes no behaviour and gives the analyser nothing new; it only rewrites call sites and fractures the style.

## Consequences

The value-object surface is uniformly `getX()`/`toX()`, matching the sibling libraries this kernel is consumed alongside, so a service built on both reads every value object one way. Setters do not arise: value objects are immutable and transform by returning a new instance. Do not "modernise" an accessor into a public property or a hook — it fractures the access style and turns a future computed or interface-bound read from a non-event into a breaking change across every call site.
