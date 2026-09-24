# 6. An event stream is never compressed

## Status

Accepted

## Context

The shared HTTP kit (see [0005](0005-core-provides-the-shared-http-host-kit.md)) puts a compression middleware in front of every response, which is the right default for services whose responses are HTML, CSS and JSON.

That middleware decides whether to compress by content type, and its default set is `text/*` — which matches `text/event-stream`. For a response with no `content-length`, deciding also means *reading the body*: the middleware buffers from the stream, under a short timeout, until it has enough bytes to judge whether compressing is worthwhile.

That assumption — a body produces its bytes promptly — is exactly what a server-sent-event stream breaks. An SSE body emits a frame when something happens and then stays silent, possibly for minutes. So the middleware's read is still outstanding when its timeout fires, it proceeds to compress anyway, and the compressing reader starts a second read on the same body. The stream refuses a concurrent read, and the connection dies with `PendingReadError` — the response never reaches the browser.

Compression is also pointless here even when it works: frames are small and must be flushed immediately to be useful, which is the opposite of what a compressor wants to do.

A host cannot fix this for itself. The middleware stack is assembled here, and the host has no way to remove or reconfigure an entry it did not add.

## Decision

The compression middleware is constructed with a content-type pattern that is the library default minus `text/event-stream`. Everything else compresses exactly as the default would have it; an event stream is written through untouched.

The pattern is a public constant on the factory so the rule can be asserted directly, and the construction carries a fence pointing here. Asserting the pattern alone does not prove the factory uses it, so the kit's integration tests also send an event stream and a page through a real server with `Accept-Encoding: gzip`: the page comes back compressed and the stream does not.

## Consequences

- A host can stream server-sent events through the shared kit and they arrive, with no per-host middleware surgery.
- Event streams are never compressed. Given frame sizes and the need to flush each one, that costs nothing worth having.
- The pattern is the kit's own rather than the library's default. If a future version of the middleware changes its default set, the kit does not inherit the change; the constant is where that is decided, and the test states what it must and must not match.
- A reader may be tempted to restore the plain `new CompressionMiddleware()` as a simplification. That reintroduces a hang-then-crash on any SSE route; the fence and the test are what stop it.
- This is about the *content type*, not about one host's endpoint: any streaming response declaring `text/event-stream` is covered, and nothing else changes.
