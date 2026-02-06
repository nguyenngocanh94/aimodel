# Phase 01: Project Foundation & Working CLI Prototype

This phase sets up the entire project from scratch — Python project structure, dependency management, the two-layer architecture (Prompting Layer + API Adapter Layer), and a working CLI that demonstrates the end-to-end workflow. By the end of this phase, you'll have an executable CLI tool that can generate an optimized image prompt from a natural language description using an LLM, and then call a mock adapter to simulate image generation. This proves out the architecture and gives the user something real to run immediately.

## Tasks

- [x] Research existing project context and initialize the Python project:
  - Read `skills/image-prompting.md` to understand the S.A.P.E.L.T. framework and Textual DNA workflow already defined — these MUST be incorporated into the prompting layer, not reinvented
  - Read `skills/marketing-strategy.md` for content strategy context
  - Read `AGENTS.md` and `package.json` to understand the existing project structure
  - Create a new directory `kol-creator/` at the project root as the Python application home
  - Initialize with `pyproject.toml` using modern Python packaging (requires Python 3.11+):
    - Project name: `kol-creator`
    - Dependencies: `click` (CLI framework), `httpx` (async HTTP client), `pydantic` (data validation), `python-dotenv` (env config), `rich` (terminal output formatting)
    - Dev dependencies: `pytest`, `pytest-asyncio`, `ruff` (linter/formatter)
  - Create `.python-version` file set to `3.11`
  - Create `kol-creator/.env.example` with placeholder keys: `GEMINI_API_KEY`, `KLING_API_KEY`, `OPENAI_API_KEY` (for future use)
  - Create `kol-creator/.gitignore` to exclude `.env`, `__pycache__/`, `.venv/`, `*.pyc`, `output/`

- [x] Create the core application directory structure and base modules:
  <!-- COMPLETED: Created all core modules - __init__.py (v0.1.0), cli.py (Click groups: kol, generate, publish, config), config.py (Pydantic Settings with env loading), and models/ (kol.py, content.py, prompt.py with all Pydantic models). Fixed pyproject.toml build-backend from invalid setuptools.backends._legacy to setuptools.build_meta. All imports verified working, CLI commands registered correctly. -->
  - `kol-creator/src/kol_creator/__init__.py` — package init with version
  - `kol-creator/src/kol_creator/cli.py` — Click CLI entry point with command groups: `kol` (persona management), `generate` (content creation), `publish` (social posting)
  - `kol-creator/src/kol_creator/config.py` — Pydantic Settings class loading from `.env`, holding API keys and default preferences (default_image_provider, default_video_provider, output_dir)
  - `kol-creator/src/kol_creator/models/` directory with:
    - `__init__.py`
    - `kol.py` — Pydantic models: `KOLPersona` (name, description, textual_dna, style_guide, reference_images list), `KOLProfile` (persona + generated assets metadata)
    - `content.py` — Pydantic models: `ContentRequest` (description, content_type enum [image/video], aspect_ratio, provider), `ContentResult` (prompt_used, provider, output_path, metadata dict)
    - `prompt.py` — Pydantic models: `SAPELTPrompt` (subject, action, place, environment, lighting, technical fields), `PromptRequest` (user_description, persona reference, target_provider, content_type), `PromptResult` (optimized_prompt string, sapelt_breakdown, provider_specific_notes)

- [x] Build the Adapter Layer — abstract base and first concrete adapters:
  <!-- COMPLETED: Created full adapter layer with 4 files. base.py defines ImageAdapter(ABC), VideoAdapter(ABC), and CLIToolAdapter(ABC) with all specified abstract methods. mock.py implements MockImageAdapter and MockVideoAdapter returning realistic fake ContentResult with UUIDs, timestamps, resolution metadata. registry.py implements AdapterRegistry with auto-registration of mock adapters, get/register/list methods for both image and video adapters, with descriptive KeyError messages. All imports verified, ruff lint/format clean. -->
  - `kol-creator/src/kol_creator/adapters/__init__.py`
  - `kol-creator/src/kol_creator/adapters/base.py` — Define abstract base classes:
    - `ImageAdapter(ABC)` with methods: `async generate(prompt, reference_images, options) -> ContentResult`, `name() -> str`, `supports_reference_images() -> bool`
    - `VideoAdapter(ABC)` with methods: `async generate(prompt, reference_images, options) -> ContentResult`, `name() -> str`, `max_duration_seconds() -> int`
    - `CLIToolAdapter(ABC)` with methods: `async execute(command, args) -> str` — for shelling out to Gemini CLI, Claude Code, etc.
  - `kol-creator/src/kol_creator/adapters/mock.py` — `MockImageAdapter` and `MockVideoAdapter` that return fake successful results with realistic metadata (for testing without API keys)
  - `kol-creator/src/kol_creator/adapters/registry.py` — `AdapterRegistry` class that maps provider names to adapter instances, with `get_image_adapter(name)` and `get_video_adapter(name)` methods

- [ ] Build the Prompting Layer — LLM-powered prompt generation using S.A.P.E.L.T.:
  - `kol-creator/src/kol_creator/prompting/__init__.py`
  - `kol-creator/src/kol_creator/prompting/sapelt.py` — The S.A.P.E.L.T. prompt engine:
    - Function `build_sapelt_system_prompt(persona: KOLPersona) -> str` that constructs the LLM system prompt incorporating the Textual DNA and S.A.P.E.L.T. framework from `skills/image-prompting.md`
    - Function `parse_sapelt_response(llm_response: str) -> SAPELTPrompt` that extracts structured S.A.P.E.L.T. components from the LLM response
    - Include the full S.A.P.E.L.T. framework instructions directly (Subject, Action, Place, Environment, Lighting, Technical) — copy the methodology from the skill file
  - `kol-creator/src/kol_creator/prompting/generator.py` — `PromptGenerator` class:
    - Takes a `PromptRequest` and calls an LLM (Gemini API via httpx) to generate an optimized image/video prompt
    - Uses the S.A.P.E.L.T. system prompt from `sapelt.py`
    - Falls back to a template-based approach if no API key is configured (using hardcoded S.A.P.E.L.T. templates)
    - Method: `async generate_prompt(request: PromptRequest) -> PromptResult`

- [ ] Build the Workflow Engine — sequential pipeline orchestrator:
  - `kol-creator/src/kol_creator/workflow/__init__.py`
  - `kol-creator/src/kol_creator/workflow/pipeline.py` — `Pipeline` class:
    - Holds ordered list of `Step` objects (each step: name, callable, dependencies)
    - Method `async run(context: dict) -> dict` that executes steps sequentially, passing context between them
    - Each step receives the accumulated context and returns updates to merge back
    - Rich console output showing step progress (spinner, checkmarks, timing)
  - `kol-creator/src/kol_creator/workflow/steps.py` — Pre-built pipeline steps:
    - `load_persona(context) -> context` — loads KOL persona from JSON file
    - `generate_prompt(context) -> context` — uses PromptGenerator to create optimized prompt
    - `generate_content(context) -> context` — uses appropriate adapter to generate image/video
    - `save_result(context) -> context` — saves output metadata to JSON

- [ ] Wire up the CLI commands to make everything executable:
  - Update `kol-creator/src/kol_creator/cli.py` to implement:
    - `kol create` — interactive-free command that creates a sample KOL persona JSON file in `output/personas/` with example Textual DNA (a pre-built demo persona so it runs without user input)
    - `kol show <name>` — displays persona details using Rich tables
    - `generate image <persona-name> "<description>"` — runs the full pipeline: load persona → generate prompt → call adapter → save result. Uses mock adapter by default, real adapter if API key configured
    - `generate prompt <persona-name> "<description>"` — just generates and displays the optimized prompt (useful for testing the prompting layer alone)
    - `config show` — displays current configuration and which adapters are available
  - Create `kol-creator/src/kol_creator/demo.py` — a `create_demo_persona()` function that returns a fully-populated `KOLPersona` with:
    - Name: "Luna Chen"
    - Textual DNA: "Early-20s East Asian female model, oval face with soft features, almond-shaped dark brown eyes with subtle double eyelids, small nose with a gentle slope, naturally full lips, long straight black hair with subtle auburn highlights, slim athletic build."
    - Style guide: "Modern street fashion meets minimalist aesthetic. Favors earth tones with occasional bold accent colors. Natural makeup look."
    - This demo persona allows Phase 1 to run fully autonomously without any user input

- [ ] Create the entry point and verify the full pipeline works end-to-end:
  - Create `kol-creator/src/kol_creator/__main__.py` so the app can run with `python -m kol_creator`
  - Add a `[project.scripts]` entry in `pyproject.toml`: `kol-creator = "kol_creator.cli:cli"`
  - Create `kol-creator/output/` directory with a `.gitkeep`
  - Install the project in development mode: `cd kol-creator && pip install -e ".[dev]"`
  - Run `kol-creator kol create` to generate the demo persona
  - Run `kol-creator generate prompt "Luna Chen" "sipping coffee at a rooftop cafe in Saigon at sunset"` and verify it outputs a S.A.P.E.L.T.-structured prompt
  - Run `kol-creator generate image "Luna Chen" "sipping coffee at a rooftop cafe in Saigon at sunset"` and verify the mock adapter returns a successful result
  - Run `kol-creator config show` and verify it displays the configuration
  - Fix any errors that arise — the goal is a fully working CLI demo

- [ ] Write tests for the core modules:
  - `kol-creator/tests/__init__.py`
  - `kol-creator/tests/test_models.py` — test Pydantic model validation for KOLPersona, ContentRequest, SAPELTPrompt
  - `kol-creator/tests/test_adapters.py` — test MockImageAdapter and MockVideoAdapter return valid ContentResult
  - `kol-creator/tests/test_registry.py` — test AdapterRegistry correctly maps provider names
  - `kol-creator/tests/test_pipeline.py` — test Pipeline executes steps in order and passes context
  - `kol-creator/tests/test_sapelt.py` — test build_sapelt_system_prompt generates valid prompts containing S.A.P.E.L.T. components
  - `kol-creator/tests/test_cli.py` — test CLI commands using Click's CliRunner (kol create, generate prompt, config show)

- [ ] Run all tests and fix any failures:
  - Execute `cd kol-creator && python -m pytest tests/ -v`
  - Fix any failing tests
  - Run `ruff check src/` and fix any linting issues
  - Run `ruff format src/ tests/` to ensure consistent formatting
  - Verify all tests pass and the CLI still works end-to-end
