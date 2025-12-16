# Creating Documents in Knowledge Base

> **Source:** Migrated from `.cursor/rules/old/knowledge-documentation.mdc`  
> **Category:** reference

## 📚 Knowledge Base - Document Creation Rules

### 🎯 When to create documents?

**ALWAYS** create documents in `docs/knowledge/` when:
1. **Solving a problem** → `knowledge/journal/`
2. **Creating a tutorial/introduction** → `knowledge/tutorials/`
3. **Analyzing technology/architecture** → `knowledge/technical/`
4. **Documenting a tool/configuration** → `knowledge/reference/`
5. **Answering a technical question** → `knowledge/journal/`
6. **Explaining a concept** → `knowledge/technical/`

### 📁 Directory Structure

```
docs/knowledge/
├── tutorials/    # Short tutorials and introductions
├── journal/      # Knowledge journal - problems and solutions
├── technical/    # Technical and analytical documents
└── reference/     # Reference documents (setup, config, tools)
```

### 📝 Document Format

Each document should contain:

```markdown
# Document Title

> **Creation Date:** YYYY-MM-DD  
> **Context:** Why the document was created  
> **Category:** tutorial/journal/technical/reference

## 🎯 Purpose

Brief description of the document's purpose.

## 📋 Content

[Document content]

## 🔗 Related Documents

- [Link to related document](./relative/path.md)
- [Link to task](../tasks/TASK_XXX.md)

## 📌 Notes

Additional notes, remarks, future considerations.

---

**Last updated:** YYYY-MM-DD
```

### 🎨 Document Categories

#### 📘 Tutorials (`knowledge/tutorials/`)
**When to use:**
- **General tutorials** - universal knowledge, useful in other projects
- Setup and configuration of tools (general, not specific to MovieMind API)
- Technology introductions (Laravel, Redis, Docker, etc.)
- Step-by-step guides (general)
- Quick start guides (general)

**DON'T use for:**
- ❌ Documents specific to MovieMind API (use `reference/`)
- ❌ Testing instructions for specific application (use `reference/`)
- ❌ Documents describing specific project functionalities (use `reference/`)

**Examples:**
- `OPENAI_SETUP_AND_TESTING.md` - general OpenAI API setup
- `HORIZON_SETUP.md` - general Laravel Horizon setup
- `INSOMNIA_SETUP.md` - general Insomnia tool setup

#### 📔 Journal (`knowledge/journal/`)
**When to use:**
- Encountered problems and their solutions
- Answers to technical questions
- Debugging notes
- Decision journal

**Examples:**
- `HORIZON_NOT_SHOWING_JOBS.md`
- `CHECK_QUEUE_STATUS.md`
- `TECHNICAL_QUESTIONS_ANSWERS.md`

#### 🔧 Technical (`knowledge/technical/`)
**When to use:**
- Architecture analyses
- Technology comparisons
- Refactoring proposals
- Technical concept explanations
- Security analyses

**Examples:**
- `ARCHITECTURE_ANALYSIS.md`
- `LARAVEL_EVENTS_JOBS_EXPLAINED.md`
- `REFACTORING_PROPOSAL.md`

#### 📚 Reference (`knowledge/reference/`)
**When to use:**
- **Reference documents specific to MovieMind API project**
- Tool configuration in the context of MovieMind API
- Environment setup for MovieMind API
- Testing instructions for specific application
- Documents describing specific project functionalities
- Project standards and conventions
- Workflow documentation specific to the project

**Use for:**
- ✅ Manual testing instructions for MovieMind API
- ✅ Feature flags documentation specific to the project
- ✅ Testing strategy specific to the project
- ✅ Deployment configuration specific to the project
- ✅ Developer tools documentation in the context of the project

**Examples:**
- `MANUAL_TESTING_GUIDE.md` - MovieMind API testing instructions
- `CODE_QUALITY_TOOLS.md` - code quality tools in the context of the project
- `TESTING_STRATEGY.md` - testing strategy specific to the project
- `DEPLOYMENT_SETUP.md` - deployment setup specific to the project
- `FEATURE_FLAGS.md` - feature flags specific to MovieMind API

### 🔄 Document Creation Workflow

1. **When asking AI questions:**
   - If solving a problem → automatically create document in `journal/`
   - If creating a tutorial → create document in `tutorials/`
   - If analyzing → create document in `technical/`

2. **When executing tasks:**
   - If documenting implementation → create in `tasks/` (related to task)
   - If documenting general knowledge → create in `knowledge/`

3. **After completion:**
   - Update last update date
   - Add links to related documents
   - Check for duplicates

### ⚠️ Important Rules

1. **Don't duplicate** - check if similar document already exists
2. **Use descriptive names** - file name should clearly describe content
3. **Add links** - connect related documents
4. **Update dates** - always update last modification date
5. **Categorize correctly** - use appropriate category according to the following rules:
   - **Tutorials** = general, universal, useful in other projects
   - **Reference** = specific to MovieMind API, project reference documents
   - **Technical** = technical analyses, comparisons, concept explanations
   - **Journal** = problems and solutions, debugging notes
6. **Format tables** - when generating Markdown tables always align headers and columns (add spaces where needed), so that **each field is readable and vertically aligned**.

### 🔍 Key Difference: Tutorials vs Reference

**Tutorials (`tutorials/`):**
- ✅ **General, universal tutorials** - knowledge useful in other projects
- ✅ Example: "How to configure Laravel Horizon" (general tutorial)
- ✅ Example: "How to use OpenAI API" (general tutorial)
- ❌ **NOT:** Testing instructions for specific MovieMind API application
- ❌ **NOT:** Documents describing specific project functionalities

**Reference (`reference/`):**
- ✅ **Documents specific to MovieMind API project**
- ✅ Instructions and documentation of project functionalities
- ✅ Example: "Manual testing instructions for MovieMind API" (project-specific)
- ✅ Example: "MovieMind API feature flags" (project-specific)
- ❌ **NOT:** General tutorials about technologies

**Decision rule:**
- If document describes **specific MovieMind API functionality** or **project-specific instructions** → `reference/`
- If document describes **general knowledge about technology/tool** → `tutorials/`

**Decision examples:**
- ❌ `MANUAL_TESTING_GUIDE.md` in `tutorials/` - ERROR (project-specific)
- ✅ `MANUAL_TESTING_GUIDE.md` in `reference/` - CORRECT (project-specific)
- ✅ `HORIZON_SETUP.md` in `tutorials/` - CORRECT (general tutorial)
- ✅ `FEATURE_FLAGS.md` in `reference/` - CORRECT (project-specific)

### 🤖 Automatically Generated Drafts

1. **Working location:** All automatically created documents save in `docs/auto-generated/` (or in subdirectories of this folder). In this directory they remain marked as drafts.
2. **Metadata:** Each automatically generated file should start with a block with source and date information, e.g.:
   ```
   > Source: auto (YYYY-MM-DD)
   ```
3. **Periodic review:** At least once per sprint/week conduct a manual review of `docs/auto-generated/`. For each file decide:
   - ✅ move to `docs/knowledge/...` (after full adaptation to template and adding PL/EN versions),
   - ♻️ update and leave as draft (awaits clarification),
   - 🗑️ delete, if content doesn't add value.
4. **Promotion to knowledge base:** File moved to `docs/knowledge/` should be treated as a new document – fill required template sections, add translations, and links to related materials.
5. **Quality control:** Automatic drafts **never** go directly to `docs/knowledge/` without manual review. Lack of review should be treated as documentation debt to be closed in the nearest cycle.

### 🔗 Relations to Tasks

- Documents related to **specific task** → `docs/tasks/TASK_XXX.md`
- Documents with **general knowledge** → `docs/knowledge/`
- **Task backlog** → `docs/issue/TASKS.md`

### 📌 Examples

#### Example: Journal Entry
```markdown
# Problem with Horizon not showing jobs

> **Creation Date:** 2025-11-06  
> **Context:** During debugging it was noticed that Horizon dashboard doesn't show jobs  
> **Category:** journal

## 🎯 Problem

Horizon dashboard doesn't display jobs despite them being processed.

## 🔍 Analysis

[Analysis details]

## ✅ Solution

[Problem solution]

## 📌 Notes

- Check Redis configuration
- Verify permissions
```

#### Example: Technical Document
```markdown
# Analysis: Laravel Events vs Jobs

> **Creation Date:** 2025-11-06  
> **Context:** Analysis of choice between Events and Jobs for asynchronous processing  
> **Category:** technical

## 🎯 Purpose

Comparison of Events vs Jobs approaches in Laravel.

## 📋 Analysis

[Detailed analysis]

## 🔗 Related Documents

- [Laravel Events Explained](./LARAVEL_EVENTS_JOBS_EXPLAINED.md)
- [Refactoring Proposal](./REFACTORING_PROPOSAL.md)
```

---

**Remember:** Documentation is an investment in the project's future. Create documents systematically!

