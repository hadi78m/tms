---
trigger: always_on
---

# Persistence of Planning Artifacts & Brain Files
- Whenever you create or modify planning artifacts (such as `implementation_plan.md`, `tasks.md`, `walkthrough.md`, architecture drafts, or execution plans), you MUST write/mirror them directly into the repository path:
  `project_context/brain/`
- Do NOT isolate planning documents exclusively inside `~/.gemini/antigravity-ide/brain/`.
- Ensure `project_context/brain/` exists before saving artifacts.
- Keep all documentation synchronized with Git tracking so that progress and context persist across conversations and IDE sessions.