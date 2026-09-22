# 0017. Config reads are strict, and a bad value stops the service

## Status

Accepted

## Context

A raw `array<mixed>` makes every reader validate its scalars again, so each config class grows private helpers — a non-empty-string check here, an integer-or-default there — and they diverge. The dangerous divergence is leniency. A read such as `is_numeric($value) ? (int) $value : 100` turns `'max_connections' => 'lots'` into the default without a word, and the operator believes a setting is in force that is not.

There is also a rule this collides with. Elsewhere in this ecosystem a parser of untrusted input returns its failure — a `null`, a failure value — rather than throwing, because the caller has a decision to make: reject the message, answer the client, carry on.

## Decision

`ConfigValues` is a `final readonly` value over the file's array, built by `ConfigValues::fromArray()`. Its constructor is private: the only other thing it carries is the section path its error messages are prefixed with, and that may come only from reading a section, never from a caller.

It has exactly the reads the services use — `string`, `optionalString`, `int`, `optionalInt`, `optionalBool`, `optionalStringList`, and `section` for a nested array. Each is strict: an integer is an `int`, never a numeric string; a boolean is a `bool`, never a truthy value. A bad value throws `InvalidArgumentException` naming the key by its full path inside a section (`limits.max_filters`); a missing required key reads `<key> is required`.

A bad value throws rather than coming back as a failure, deliberately. A service reading its own config at start-up has no decision to make: there is no request to answer and nothing to carry on with, so every caller of a `?self` would do the same thing, stop, having lost the name of the key. A malformed config value is an operator error, and the useful behaviour is to stop at once and say which key.

A default is written at the call site, `$values->optionalInt('worker_pool_limit') ?? 0`. There is no `intOr($key, $default)` family: the optional reads are needed regardless, for keys whose absence means something other than a scalar default, and a second spelling of the same read is a second way to do one thing.

`ConfigValues` stops at scalars. It returns no domain types, because the kernel carries no Nostr or Blossom logic: a service maps `optionalStringList('tenant_pubkeys')` to public keys itself. It owns no invariant beyond the type, either — that a port is in range, or an upload limit positive, stays with the type that owns that rule.

## Consequences

A malformed value stops the service at start-up instead of silently running with a default, and no config class validates a scalar by hand.

Do not grow `ConfigValues` towards a configuration framework: no dot-path lookups, no coercion, no schema or table of defaults, no `xOr()` reads, and no read added ahead of a service that needs it. A general options library does more than this and was not adopted for that reason; each extra capability is a second way to express a default or a type, which the services would then use inconsistently.

The three scalar reads share a shape and are not merged into one generic read, because the analyser could then no longer give each its precise return type.
