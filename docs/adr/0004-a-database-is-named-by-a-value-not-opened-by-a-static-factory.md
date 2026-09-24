# 4. A database is named by a value, not opened by a static factory

## Status

Accepted

## Context

Every service opens SQLite through the kernel, and the reason is the connection's settings. A handle opened without them — write-ahead journalling, a busy timeout, foreign keys, the cache and memory-map sizes — behaves differently enough under concurrency to be a different database, so "a connection opened by this ecosystem" has to mean one specific configuration, set in one place.

The obvious way to provide that is a static factory taking a path, `create(string $path): PDO`. One property disqualifies it. A PDO handle cannot cross a process boundary, and a service that runs its database work on worker processes — the relay does — must send the far side something that can open a connection when it arrives. A static call cannot travel; only a value can. With a static factory what travels is a bare `string`, leaving every worker task to hold that string and remember which static to call on it: the same two lines repeated in each task, over a value that nothing distinguishes from any other string, or even guarantees is not blank.

## Decision

`SqliteDatabase` is a `final readonly` value naming a database. It is built by `atPath()` or `inMemory()` and exposes `connect(): PDO`, which opens a handle with the kernel's settings applied.

It carries its path and no resource, so it serialises: a host sends it to a worker process and connects there. `atPath()` rejects a blank path, establishing that once at the configuration edge rather than re-checking it inward. `inMemory()` names the `:memory:` case so that hosts and tests do not pass a magic string, and skips the parent-directory creation a real path needs.

`connect()` returns a fresh handle on every call. The value is not a pool and does not memoise, because how long a connection lives is the host's decision: one handle on the event loop, one per worker process.

## Consequences

A configured database is one typed thing a host passes, stores and serialises, and the settings stay in one place. Do not add a static `create(string): PDO` beside `SqliteDatabase` as a shortcut, and do not add caching or pooling to `connect()`: a value that sometimes returns the same handle and sometimes does not has stopped being a value.

A service whose database work is one small indexed statement per request keeps its connection on the event loop, which that volume makes acceptable. If that changes, the proportionate first step is already in place — write-ahead journalling with a busy timeout — and moving the work onto worker processes is warranted only for a genuinely high-volume, single-writer workload.
