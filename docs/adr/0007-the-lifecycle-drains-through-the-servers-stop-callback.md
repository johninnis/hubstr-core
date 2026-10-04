# 7. The lifecycle drains through the server's stop callback

## Status

Accepted

## Context

A service built on this kernel usually holds work that outlives a single request: open server-sent-event streams, a scheduler, a pool of connections. On shutdown that work has to be released, and *when* it is released decides whether shutdown is graceful or lossy.

Shutdown has three distinct moments, and only the middle one is useful for releasing in-flight work:

1. the listening sockets close, so no new connection is accepted;
2. the connections already accepted are still open and their responses still streaming;
3. the request handlers are torn down and everything still open is severed.

Releasing in-flight work belongs in (2). A stream that is told to finish while its connection is still open can write its last frame and close cleanly; the same stream told to finish after (3) has already had its socket taken away, and the client sees a truncated response instead of a clean end.

The obvious code does not express that. Written as the sequence a reader expects —

```
$server->stop();
$lifecycle->drain();
$lifecycle->stop();
```

— the drain runs *after* the server has finished stopping, which is moment (3). It compiles, it reads naturally, and it drains into connections that are already gone. Nothing about the shape of the API warns you: `stop()` returns `void` and gives no indication that the interesting window closed inside it.

The kernel therefore needs a seam that runs *during* the server's stop, and the server is the only party that knows when moment (2) is. That is what `ServerInterface::onStop()` exists for — it is not a general-purpose event hook, and the kernel registers exactly one callback on it.

## Decision

`LifecycleInterface` splits shutdown into `drain()` and `stop()`, and the kernel wires them to different moments:

- Before starting the server, the kernel registers `$lifecycle->drain(...)` as a server stop callback. The server invokes it once the listeners are closed and before the request handlers are torn down — moment (2).
- After `$server->stop()` returns, the kernel calls `$lifecycle->stop()` to release the resources that outlived the connections — moment (3).

A host with nothing to drain passes no lifecycle at all; the kernel then registers no callback.

Shutdown is unconditional once the server has started. Whatever happens between the server starting and the signal arriving — the lifecycle failing to start, the wait itself failing — the kernel still stops the server and then the lifecycle, and the lifecycle is still stopped when the server's own stop throws. A lifecycle's `stop()` is where a host releases what must not outlive the process in a usable state — the signer locks its vault there — so it cannot be left to run only on the path where nothing went wrong. A server that never started is not stopped: there is nothing to release. The failure that caused the shutdown propagates, and "Shutdown complete" is logged only when the shutdown did complete.

"Unconditional" has three limits, all deliberate, and all the process supervisor's to cover.

The kernel waits for in-flight requests for as long as they take; it sets no deadline of its own. A second signal while it waits, or the supervisor's kill when its own stop timeout expires — the shipped units allow fifteen seconds — ends the process by the signal's default action, with no further callback. And the kernel listens for a signal only once it is waiting for one, which is after the server and the lifecycle have started: a signal that arrives in that interval also ends the process at once. In those two cases the lifecycle's `stop()` does not run. That is acceptable only because nothing it releases survives the process — a locked vault, a worker pool, an open connection all die with it, and SQLite's journal makes an interrupted write safe — so a lifecycle must never be the sole guard of durable state. A deadline inside the kernel would duplicate the supervisor's, and arming the signal before start-up would need a second phase on the signal port; neither is worth its cost while those limits hold.

The third is the log. The kernel and the server each write a log record as their first act of stopping, and the log has one sink (see [0011](0011-a-service-logs-to-standard-output-only-without-blocking.md)). When that sink can no longer be written to, because whatever was reading the service's output has gone, the kernel's record fails and its shutdown runs all the same, but the server's own `stop()` fails at its first line, before it has closed a listener or called a stop callback. The drain therefore does not run. The lifecycle's `stop()` still does, because the kernel calls it whether or not the server's stop succeeded, and the failure then propagates and ends the process. This is the same trade as the other two: what `stop()` releases is released, in-flight work is cut short, and nothing durable depended on the drain.

This makes one demand of a `LifecycleInterface` implementation: `drain()` and `stop()` must be safe to call after a `start()` that failed or only partly ran, releasing whatever was acquired and doing nothing for what was not.

The registration carries a fence pointing at this record, and the kernel's test asserts the whole call order — `server.start`, `lifecycle.start`, `await`, `server.stop`, `lifecycle.drain`, `lifecycle.stop` — so undoing the design fails the suite rather than degrading silently. The same test class pins the failure paths: a server stop that throws, a lifecycle that fails to start, and a server that never starts.

## Consequences

- In-flight streams end cleanly on shutdown, and hosts get that without knowing anything about the server's internal stop sequence.
- `ServerInterface` carries an `onStop(Closure)` method that exists for this one purpose. An implementer of the port must forward it to whatever notion of "stopping" its server has; a server that cannot offer moment (2) cannot drain gracefully, and that is a real limitation of such a server rather than a gap in this design.
- The wiring reads like an over-complication of a three-line sequence, and the tempting "simplification" to `stop(); drain(); stop();` is precisely the bug this prevents. The fence, this record, and the ordering test are what hold it in place.
- `drain()` and `stop()` are separate methods on the lifecycle rather than one. A host that genuinely has nothing to release in one of the two moments implements it as a no-op, which is cheaper than the alternative: a single `stop()` that cannot be run at both moments and must therefore pick the wrong one for half of its work.
