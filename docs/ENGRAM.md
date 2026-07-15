# Engram

[Engram](https://github.com/gannonh/engram) provides persistent memory for AI assistants via MCP.

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
