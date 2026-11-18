# Remaining optional extensions

The requested core competition workflow is implemented. Potential future additions, outside this version:

- Transfer a saved session to another marker account.
- Configurable bonus/penalty or steal rules, with a separate audited score event model.
- Bank pagination and dedicated session/audit search for larger archives.
- A supervised, reference-aware media cleanup tool.
- Automatic image resizing/transcoding for exceptionally large media.
- Push synchronization (SSE/WebSockets) if polling becomes inadequate.
- Exportable competition result sheets and backups scheduled by the hosting environment.

Before public hosting, apply the web-server deny rules, HTTPS and least-privilege database credentials. The local classroom projector rehearsal is still necessary; automated tests cannot verify room lighting or network reliability.
