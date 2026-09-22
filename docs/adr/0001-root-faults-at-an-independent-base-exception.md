# 0001. Root faults at an independent base exception

## Status

Accepted

## Context

This library is the shared runtime kernel for a family of services that also depend, elsewhere in the wider system, on Nostr libraries. Those Nostr libraries root their own exceptions at a `NostrException` base. A natural but mistaken instinct is to make every exception in the system descend from a single root, or to align an application's exception base with that of a prominent dependency.

That instinct conflates two different questions: who is allowed to raise a fault, and what a piece of code happens to depend on. A fault is a statement made by the code that raises it about a condition it has encountered. The kernel's faults — a missing config file, migrations that cannot be read or do not form a sequence, a migration that failed, a database newer than the code, a directory that cannot be created — are all raised by this library's own code about its own operations. None of them is a Nostr condition. This library has no Nostr dependency at all.

If these faults descended from `NostrException`, the type would lie: it would assert a Nostr origin for conditions that have nothing to do with Nostr, and it would manufacture a dependency on a Nostr package that the library does not otherwise need. Consumers catching `NostrException` would then catch unrelated kernel faults, and consumers catching kernel faults would be forced to reason about Nostr.

## Decision

Faults are rooted by whose code raises them, not by the dependency graph.

This library roots all of its own faults at an abstract `HubstrException`, which extends PHP's `\Exception` directly and is independent of any other library's exception base. The concrete leaf exceptions are `final` and extend `HubstrException`. Code that raises a fault about a Nostr condition would root it at `NostrException`; code that raises a fault about this kernel's operations roots it at `HubstrException`.

`HubstrException` is part of the public surface: a service built on the kernel roots its own faults at it — the relay's `WorkerResultException` and `MalformedStatPayloadException`, for example — rather than at a base of its own.

## Consequences

A single `catch (HubstrException)` reliably scopes to faults raised by this library and the services built on it, with no risk of also catching — or being caught alongside — unrelated Nostr faults. The library declares no Nostr dependency it does not use. The boundary is decided by a stable rule (whose code raises the fault) rather than by the shifting shape of the dependency graph, so the hierarchy stays meaningful as dependencies change. The cost is that the system has more than one exception root; this is intentional and correct, because those roots describe genuinely different origins.
