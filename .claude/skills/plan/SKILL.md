---
name: plan
description: "Show the current planning status from .claude/planning/. Activated by /plan. With no argument, shows PLANS.md current focus and the active plan's status/current-step/next-action. With an argument (e.g. /plan 02-permission-sets), reads that specific plan file instead."
license: MIT
metadata:
  author: Cameron
---

# /plan — Planning Status

When the user types `/plan [optional-plan-name]`:

1. Always read `.claude/planning/PLANS.md` to get the current focus and sequence.
2. Always update `.claude/planning/PLANS.md` after completing a phase implementation when necessaryto keep it current.
3. **If no argument was given:** identify the active plan from the "Current focus" line in PLANS.md, then read that plan file (e.g. `.claude/planning/02-permission-sets.md`).
4. **If an argument was given** (e.g. `02-permission-sets`): read `.claude/planning/<argument>.md` instead.
5. Output a tight summary:
   - **Current focus** — which plan is active and where it sits in the sequence
   - **Status** — from the plan file's 4-line header (`Status`, `Current step`, `Depends on`, `Summary`)
   - **Next action** — the immediate next step to execute, pulled from the plan body
   - **Exit checklist** — if one exists in the plan file, list unchecked items only

Keep the output to roughly one screen. Do not quote the full plan file verbatim — synthesize.

## Before Implementation

If the user follows `/plan` with implementation intent (e.g. "let's continue", "go ahead", "proceed", "start", "implement"), do NOT begin coding. Instead, conduct a pre-implementation interview:

1. **Read the next phase/step in full** from the plan file.
2. **Surface recommendations** — flag any decisions, trade-offs, naming choices, or ambiguities in the upcoming step that are worth discussing before writing code.
3. **Ask targeted questions** — present specific options with a recommended default pre-selected. Cover anything that would be hard to reverse (schema, API surface, naming conventions that spread across files).
4. **Wait for the user's answers** before writing any code.

Skip the interview only if the user has already answered all open questions for that step in the current conversation.
