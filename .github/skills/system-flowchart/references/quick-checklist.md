# System Flowchart Quick Checklist

Use this for a fast pass before publishing updates to SYSTEM_FLOWCHART.md.

- Scope is explicit: full system or module-specific.
- Entry/auth flow is represented (user, login, auth result path).
- Core pages and navigation links are included.
- Page actions map to real API endpoints.
- API nodes map to real data entities/tables.
- Shared context nodes are included where applicable (for BW: dataset context).
- DB selection/fallback behavior is represented when relevant.
- Debug/test scripts are excluded unless requested.
- Mermaid syntax renders without errors.
- Diagram notes state assumptions, exclusions, and boundaries.
