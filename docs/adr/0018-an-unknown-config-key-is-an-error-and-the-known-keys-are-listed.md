# 0018. An unknown config key is an error, and the known keys are listed

## Status

Accepted

## Context

Strict reads catch a wrong value ([0017](0017-config-reads-are-strict-and-a-bad-value-stops-the-service.md)). They cannot catch a wrong *key*. `log_levle`, `trusted_proxy`, `max_conections` are never read at all, so the default applies in silence: the same fault as a lenient value, arriving by another route, and the harder one to notice because nothing in the file is malformed.

Detecting it needs the set of keys a service knows. That set could be inferred, by having `ConfigValues` record which keys were read and reporting the rest. Recording reads is mutable state inside a value, and it is wrong whenever a read sits on a branch that did not run.

## Decision

`ConfigValues::rejectUnknownKeys(string ...$knownKeys)` throws `InvalidArgumentException` naming every key that is not known, all at once, each by its full path inside a section.

The known keys are listed, not inferred. `ServiceRuntimeConfig::KEYS` lists the kernel's five reads; a service calls the check once with its own keys and those, and once on each section it reads.

## Consequences

A misspelt optional key stops the service at start-up by name. A key the kernel has removed is refused the same way, which is how a config still carrying one is caught.

The list repeats each key beside its read, and that repetition fails safe: a key added to the reads but not to the list rejects the service's own valid config, which the first test of a valid config catches. It is a list of names and not a schema — it says nothing about types, requiredness or defaults, which stay with the reads.

Nothing forces a service to make the call. Making it unavoidable would need a second type — an unread config that yields readable values only once its keys are declared — and that was judged poor value for a handful of config classes. A service that omits the call gets strict values and silently ignored keys.
