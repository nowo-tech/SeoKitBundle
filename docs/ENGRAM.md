# Engram

[Engram](https://github.com/gannonh/engram) provides persistent memory for AI assistants via MCP.

## Table of contents

- [Setup](#setup)
- [Usage in Cursor](#usage-in-cursor)
- [Configuration](#configuration)
- [Privacy](#privacy)
- [Spec-driven development](#spec-driven-development)

## Setup

1. Install Engram CLI on your machine.
2. This repository ships `.cursor/mcp.json` with the `engram` MCP server.
3. Restart Cursor or reload MCP servers after installation.

## Usage in Cursor

Once connected, the agent can store and recall project-specific context (configuration decisions, release notes, spec links) across sessions.

## Configuration

Default MCP entry:

```json
{
  "mcpServers": {
    "engram": {
      "command": "engram",
      "args": ["mcp"]
    }
  }
}
```

Adjust if your `engram` binary is not on `PATH`.

## Privacy

Do not store secrets, credentials, or customer PII in Engram memory.

## Spec-driven development

Engram is the cross-session memory plane; it does not define product behavior. Local `REQ-*` traceability, user stories and the Spec Kit baseline are documented in [SPEC-DRIVEN-DEVELOPMENT.md](SPEC-DRIVEN-DEVELOPMENT.md) (see also [SPEC-KIT.md](SPEC-KIT.md)).
