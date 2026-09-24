# 14. An error message reaches the client only through the template

## Status

Accepted

## Context

An HTTP response can say what went wrong in two places: the reason phrase on its status line, and its body. Sending the message as the reason phrase is the natural thing to do — `404 Unknown app: reader` tells a client more than `404 Not Found` — and an error message is exactly the kind of string that echoes a request: a path, an identifier, a name.

The server writes a reason phrase into the status line verbatim. A message carrying a line break therefore ends the status line and writes headers of its author's choosing: a request for `/app?nope%0d%0aSet-Cookie:%20session=attacker` comes back with that cookie set. This is response splitting, and it happens in the one place template escaping never sees (see [0002](0002-templates-escape-by-default-with-context-aware-escaping.md)). A header *value* with a line break is rejected by the server; a reason phrase is not.

## Decision

`ErrorPageResponder` sends the status with its standard reason phrase, and the message only as a template parameter, where it is escaped for its position like any other value. No class in the kit sets a reason phrase from a caller's text.

## Consequences

A caller may pass a message that echoes untrusted input, which is what a useful error message does, and nothing it contains can reach the response head. The responder's tests pin that a message with a line break reaches neither the status line nor the headers.

The status line carries less information than it could. A client that needs the detail reads the body. Do not restore the message to the reason phrase for the sake of a more descriptive status line.
