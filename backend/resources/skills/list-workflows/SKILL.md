---
name: list-workflows
description: List the catalog of triggerable workflows with their slugs, names, and param schemas.
mode: lite
tools:
  - App\Services\TelegramAgent\Tools\ListWorkflowsTool
---

# List Workflows

Gọi skill này khi cần biết workflow nào có sẵn. Tool trả về slug + nl_description + param_schema.
Sau đó chọn slug và gọi skill `run-workflow`.
