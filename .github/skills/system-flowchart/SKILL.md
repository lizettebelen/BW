---
name: system-flowchart
description: 'Create or update a full-system Mermaid flowchart for this BW PHP app. Use when mapping page navigation, API call paths, shared dataset context, and database/table read-write relationships. Triggers: flowchart, architecture map, system diagram, Mermaid, data flow, page-to-API mapping.'
argument-hint: 'Flowchart scope (full system, module, or changed files)'
user-invocable: true
disable-model-invocation: false
---

# BW System Flowchart

## Outcome
Produce a clear, accurate Mermaid flowchart that shows how users move through pages, how pages call APIs, and how APIs read/write core data tables for the BW system.

## When To Use
- You need to create a new architecture flowchart for this project.
- You changed page navigation, API endpoints, or database targets and need to refresh the diagram.
- You are onboarding someone and need a single visual of system behavior.
- You need a review artifact before refactoring a module.

## Inputs
- Optional argument: scope such as `full system`, `inquiry + delivery`, or `only changed files`.
- Current workspace source files (pages, `api/` endpoints, and DB config).
- Existing flowchart file if present: `SYSTEM_FLOWCHART.md`.

## Default Policy
- Exclude debug/test scripts and diagnostic utilities by default.
- Include debug/test paths only when explicitly requested or when the task is diagnostics-oriented.

## Procedure
1. Define scope and level of detail.
   - If scope is missing, default to full-system coverage of main user flows.
   - Decide whether to include only operational pages or also test/debug tools.

2. Build the page map first.
   - Identify entry points (`login.php`, dashboard/home page, major feature pages).
   - Map global navigation links between primary pages.
   - Keep names user-facing in node labels and file-accurate in node IDs.

3. Map actions from pages to API endpoints.
   - For each primary page, list user actions (add, update, delete, upload, export).
   - Connect each action to concrete endpoints under `api/`.
   - Reuse endpoint nodes when multiple pages call the same API.

4. Map data entities and DB strategy.
   - Connect API endpoints to the data tables they read/write.
   - Include critical shared tables (for this app typically `delivery_records`, `warranty_replacements`, `users`).
   - Show DB selection/fallback behavior if present (for this app: MySQL vs SQLite fallback through `db_config.php`).

5. Add shared system context.
   - Add cross-cutting context nodes that influence multiple pages (for this app: dataset filter/query context).
   - Link this context to all pages/modules that depend on it.

6. Apply visual grouping.
   - Use class definitions to distinguish pages, API endpoints, and data entities.
   - Keep styling legible and consistent; avoid excessive color complexity.

7. Validate and tighten.
   - Ensure every edge corresponds to a real navigation path, API invocation, or data relationship.
   - Remove orphan nodes or stale endpoints.
   - Check Mermaid syntax correctness and readability.

8. Document assumptions and exclusions.
   - Add short notes under the diagram for scope, conventions, and known omissions.

## Decision Points
- Full-system vs module view:
  - If broad architecture communication is needed, use full-system.
  - If a change request is module-specific, generate a focused module flowchart.

- Include debug/test scripts or not:
  - Exclude by default to reduce noise.
  - Include only when the request is diagnostics-oriented.

- Granularity of data layer:
  - Use table-level nodes by default.
  - Use service/component-level nodes only when needed for implementation planning.

## Quality Checks (Done Criteria)
- The diagram renders in Mermaid without syntax errors.
- Main user journey is visible from login to core features.
- Each mapped page action points to a real API file.
- Each mapped API node has justified data connections.
- Shared contexts (for example dataset selection) are represented where applicable.
- Styles/classes are consistent and improve readability.
- Notes explain scope and any intentional exclusions.

## Output Format
- Primary artifact: `SYSTEM_FLOWCHART.md` containing one Mermaid `flowchart` block.
- Follow-up summary:
  - What changed
  - Why those changes were made
  - What was intentionally not included

## Reuse Prompt Examples
- `/system-flowchart full system`
- `/system-flowchart inquiry and delivery records only`
- `/system-flowchart update diagram from files changed in this branch`

## Quick Checklist
- Use [quick checklist](./references/quick-checklist.md) before finalizing the diagram.
