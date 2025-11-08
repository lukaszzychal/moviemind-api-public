# 📋 Issues – Task & Refactor Documentation

This directory stores task backlogs, refactor notes, and implementation details for the project.

---

## 📁 Structure

### Main files
- **[`../pl/TASKS.md`](../pl/TASKS.md)** – primary backlog (⚠️ **always start here**)  
- **[`../pl/TASK_TEMPLATE.md`](../pl/TASK_TEMPLATE.md)** – template for creating new tasks  
- **[`../pl/REFACTOR_CONTROLLERS_SOLID.md`](../pl/REFACTOR_CONTROLLERS_SOLID.md)** – detailed API controller refactor plan

> 🇬🇧 English equivalents:  
> - [`TASKS.en.md`](./TASKS.en.md)  
> - [`TASK_TEMPLATE.md`](./TASK_TEMPLATE.md)  
> - [`REFACTOR_CONTROLLERS_SOLID.en.md`](./REFACTOR_CONTROLLERS_SOLID.en.md)

### Detailed task descriptions
- `REFACTOR_CONTROLLERS_SOLID` – API controller refactor following SOLID

---

## 🚀 Working with the AI Agent

### For the AI agent
1. **Read `TASKS.pl.md` or `TASKS.en.md`** – find an item with status `⏳ PENDING`.  
2. **Switch status to `🔄 IN_PROGRESS`** when you start working.  
3. **Review task details:**
   - Follow the link to a dedicated write-up if provided.  
   - If all details are embedded in the backlog, use them directly.  
4. **Implement the task** according to the description.  
5. **Upon completion:**
   - Mark status as `✅ COMPLETED` and capture start/end time + duration.  
   - Move the entry to the “Completed” section.  
   - Update the “Last updated” timestamp and optionally note key results.

### For maintainers
1. **Add new tasks** via the backlog:
   - Edit `TASKS.pl.md` (and optionally the English version) inside `docs/issue`.  
   - Follow the template in `TASK_TEMPLATE.pl.md` / `.en.md`.  
2. **Need a longer brief?**
   - Create a new file (e.g. `docs/issue/pl/TASK_XXX_DESCRIPTION.md`).  
   - Link to it from both language versions of `TASKS`.
3. **The AI agent will** discover the task, run the implementation, and update the status automatically once finished.

---

## 📝 Document Conventions

### Task backlogs
- Statuses: `⏳ PENDING`, `🔄 IN_PROGRESS`, `✅ COMPLETED`, `❌ CANCELLED`.  
- Priorities: `🔴 High`, `🟡 Medium`, `🟢 Low`.  
- Always provide cross-links between language variants.

### Detailed task specs
- Include the problem statement, proposed solution, implementation steps, benefits, and checklists.  
- Keep both PL/EN copies aligned – update them together.

---

## 🎯 Status legend
- ⏳ PENDING – waiting to start.  
- 🔄 IN_PROGRESS – currently being executed.  
- ✅ COMPLETED – finished.  
- ❌ CANCELLED – dropped.

---

## 📊 Example workflow
1. Maintainer adds a backlog entry using the template.  
2. AI agent picks it up, moves status to progress, and follows linked docs.  
3. Agent finishes, moves entry to completed, fills in timing, and updates the timestamp.

---

## 💡 Tips
1. Always begin with the backlog (`TASKS.*`).  
2. Use the template – it guarantees all required metadata.  
3. Break complex work into subtasks.  
4. Keep statuses and timestamps accurate.  
5. Provide clear links to additional specs.

---

**Last updated:** 2025-11-07
