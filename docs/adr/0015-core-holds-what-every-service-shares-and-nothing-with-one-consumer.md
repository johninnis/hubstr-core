# 0015. Core holds what every service shares, and nothing with one consumer

## Status

Accepted

## Context

This package is the kernel beneath several services, and a shared kernel has one characteristic way of going wrong: it accumulates. Anything that sounds general is placed in it on the expectation that another service will want it, and the package grows into a framework its services must work around.

The sharpest case here is the relay's worker mechanism. The relay dedicates worker processes — one writer, a pool of readers — each holding an open SQLite connection, because SQLite is single-writer, PDO is synchronous, and under a relay's continuous query load neither blocking the event loop nor unserialised writes is survivable. Feeding a held-open connection takes a persistent receive loop over a channel, a sentinel value a worker sends back instead of throwing across the process boundary, and a fault the parent raises on receiving it. Named "worker", those three read as shared infrastructure, and their absence from the other services reads as an omission.

Neither impression survives inspection. The mechanism exists for one shape of work: a long-lived worker servicing a stream of requests against a resource held open inside it. Blossom offloads its expensive work too, but as one-shot pool tasks that are submitted and awaited once, where a failure surfaces as the pool's own task failure and there is no sentinel to translate. The signer performs a single signing operation per request. The mechanism also depends on nothing the kernel owns, only on the concurrency library's channel. It is a storage-agnostic mechanism with exactly one consumer.

## Decision

The kernel holds what every service built on it uses: the application lifecycle, configuration, logging, the SQLite substrate, templating, version reporting and the HTTP host kit. A mechanism with a single consumer lives with that consumer, however general its name.

The relay's worker mechanism — the message loop, the failure sentinel and the fault that reports it — therefore lives in the relay, whole. The three are one contract seen from its two ends and are not divided between packages: the fault is not kept here as "shared vocabulary" while the sentinel it reports lives elsewhere.

Promotion follows a second consumer. A mechanism moves into the kernel when a second service actually needs it, not in anticipation of one.

## Consequences

The kernel's surface is what the services share, so "why does the shared kernel carry something only one service uses?" does not arise, and a service-specific detail is found in the service.

The cost is that a second service developing the relay's shape must promote the mechanism rather than find it waiting. That is the intended trade: one consumer does not justify a shared abstraction, and promoting on the second is cheap.

The same rule decides smaller questions. Cross-origin handling is not in the HTTP kit because the services apply it through different strategies (see [0005](0005-core-provides-the-shared-http-host-kit.md)); a fault type is shared when a second service throws it, not when its name sounds general.
