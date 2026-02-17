# AI Readability Audit

Date: 2026-02-13
Source guidance: [Writing Guidelines for Coding Agents](https://fieldguide.automattic.com/coding-agents-guidelines/)

## Scope

This audit evaluates how easily coding agents can complete tasks correctly in this repository with minimal back-and-forth.

Scoring scale:
- `5` Excellent: likely correct first pass with minimal prompts.
- `4` Strong: mostly correct, occasional prompt clarifications.
- `3` Adequate: frequent clarifications needed.
- `2` Weak: recurring incorrect assumptions.
- `1` Poor: high failure risk without heavy human steering.

## Current Scores

| Category | Score | Evidence | Gap |
| --- | --- | --- | --- |
| Root agent instructions (`AGENTS.md` + `CLAUDE.md`) | 4 | Root file exists and `CLAUDE.md` points to it. | Needed stronger priority language and explicit pitfalls. |
| Project knowledge and architecture context | 4 | Core architecture, hierarchy, and patterns already documented. | Needs sharper "do not violate" constraints and faster task routing. |
| Commands and execution guidance | 4 | Common commands are present and useful. | Needed a task-to-command matrix for lowest-cost command selection. |
| Conventions and testing expectations | 3 | PHPUnit `@dataProvider` convention documented. | Needed clearer behavior-change verification expectations. |
| Architectural decisions that differ from defaults | 3 | Some rationale exists in subsystem READMEs (for example Blocks AJAX choices). | Rationale not yet mapped into agent-facing guardrails. |
| Common pitfalls | 2 | Pitfalls were implicit, not centralized in root instructions. | Required a dedicated pitfalls section. |
| Directory-specific context (progressive disclosure) | 4 | Subtree `AGENTS.md` files now exist for high-context areas. | Expand coverage if additional complex subsystems emerge. |
| Instruction maintenance process | 2 | No explicit loop for updating instructions after failures. | Needed a mandatory update trigger list. |

Overall score: `3.3` (strong baseline with improved context routing and safety guidance).

## Improvements Implemented in This Pass

1. Strengthened root instructions in `AGENTS.md` with explicit **CRITICAL** rules, a task-to-command matrix, a dedicated common pitfalls list, and mandatory instruction maintenance triggers.
1. Added directory-level `AGENTS.md` files: `includes/AGENTS.md`, `client/AGENTS.md`, `tests/e2e/AGENTS.md`, and `includes/agentic-commerce/AGENTS.md`.
1. Preserved `CLAUDE.md` single-source setup (`@AGENTS.md`).
1. Added guidance informed by six-month repo history review (293 merged PRs, 390 commits): checkout parity gate, null/type guard emphasis, E2E iframe-readiness guidance, release hygiene, and feed contract checklist.

## Next Improvements (Planned)

1. Add benchmark prompts and measurement rubric by creating `docs/ai-benchmarks.md` with representative tasks and pass/fail criteria.
2. Add process hooks, including a PR checklist item for instruction updates and a lightweight monthly review cadence.

## Definition of Done for AI Readability Upgrade

- Root and subdirectory agent instructions explicitly separate project-wide rules, subsystem-specific context, and critical pitfalls/verification expectations.
- Benchmark prompts exist and can be rerun after instruction updates.
- Team has a repeatable maintenance trigger for guidance quality.
