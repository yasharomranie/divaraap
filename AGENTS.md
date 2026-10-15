# Architecture & Data
* **Modular Monolith:** Strict boundaries; zero cross-module JOINs.
* **Design:** SOLID, KISS, MVC. Clean Apple style: minimalist, text-minimal, no animations.
* **Database:** PostgreSQL and MySQL/MariaDB for shared hosting, with SoftDeletes everywhere. Preserve portable queries and active-record uniqueness on both engines.
* **Logic:** Fat Models handle all DB interactions.
* **Shared Code:** Keep global helpers in the Laravel root.

# Code Standards
* **Types:** `declare(strict_types=1);` in every file.
* **Language:** : English codebase. Translate each module inside of. 
* **Comments:** Zero. Code must be entirely self-documenting.

## UI/UX design policy

- Use `.agents/skills/ui-ux-pro-max/SKILL.md` for every UI/UX design, implementation, review, and visual fix, including Persian localization and RTL layouts.
- Read the skill and run its local search for the relevant design concern before choosing layouts, typography, colors, components, or interactions. Use `--design-system` for new pages or a shared visual direction, `--domain` for focused concerns, and `--stack laravel` for this project's implementation guidance.
- Keep the existing Laravel, Blade, Filament, TypeScript, and CSS architecture. Preserve the minimalist design and no-animation rule. Skill recommendations do not authorize framework changes or override project requirements.
- Verify Persian font coverage, RTL and mixed-direction content, responsive layout, contrast, keyboard access, visible focus, and labeled inputs for applicable UI changes. Apply web guidance to this web project.
- Use Graphify first when design work requires codebase structure or impact analysis; use UI/UX Pro Max for design decisions. Verify recommendations against the actual product and record relevant checks.

## graphify

Project policy: Use Graphify first for codebase architecture, dependency, impact, and relationship analysis. Run it through `bash scripts/graphify.sh` so the pinned project tool is available. Refresh a stale graph before analysis. Verify source details when the graph is incomplete, especially translation arrays and CSS. Graph analysis does not replace application tests.

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

When the user types `/graphify`, use the installed graphify skill or instructions before doing anything else.

Rules:
- When graphify-out/graph.json exists, run `graphify query "<question>"` before your first grep, search or source-file read for a codebase question, and put this rule in every subagent prompt that explores code. Read raw files only for lines the graph or an earlier read already located. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- Dirty graphify-out/ files are expected after hooks or incremental updates; dirty graph files are not a reason to skip graphify. Only skip graphify if the task is about stale or incorrect graph output, or the user explicitly says not to use it.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
- A committed graphify-out/graph.json is rewritten whole on every build, so a large diff to it in a pull request is expected. Do not flag it as churn or suggest skipping the graph rebuild or its hooks.
