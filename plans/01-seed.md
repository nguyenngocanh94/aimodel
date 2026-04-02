# Project Seed: AI Video Workflow Builder

## What
A visual workflow builder where users drag-and-drop nodes onto a canvas, connect them together, and each node performs an action in an AI video generation pipeline. Similar to Freepik Space but simpler and focused. Lightweight — designed for simple workflows, not hundreds of elements.

## Who
Developers building AI video pipelines visually. Could expand to creators/marketers later, but developer-first for now.

## Success
A user opens the builder, drags out a few nodes (e.g., "Script Writer" → "Image Generator" → "Video Composer"), connects them, sees data flowing between nodes visually, and gets a generated video at the end — all without writing code.

## Core Criteria
- Drag-and-drop nodes on a canvas
- Connect nodes together (output → input)
- Each node defines its inputs and outputs
- Data transformation is visible in the UI at each step
- Purpose-built for AI video generation workflows
- Simple — not built for complex workflows with hundreds of elements

## Tech Stack
- React + Vite (lightweight SPA, no SSR needed)
- React Flow / xyflow (mature node-based canvas library)
- Tailwind CSS (styling)
- TypeScript

## Non-goals
- NOT a general-purpose automation tool (like n8n or Zapier)
- NOT a code editor
- NOT enterprise-scale (no need for hundreds of nodes)
- NOT a mobile app
