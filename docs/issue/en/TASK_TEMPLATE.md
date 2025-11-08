# 📋 Task Template

Use this template when creating new backlog items or issues.

---

## 📝 Template

```markdown
#### `TASK-XXX` – Task title
- **Status:** ⏳ PENDING
- **Priority:** 🔴 High / 🟡 Medium / 🟢 Low
- **Estimated time:** X hours (optional)
- **Start time:** --
- **End time:** --
- **Duration:** -- (enter `AUTO` for 🤖 tasks)
- **Execution:** 🤖 AI Agent / 👨‍💻 Manual / ⚙️ Hybrid
- **Description:** One–two sentence summary
- **Details:** [link to detailed brief](./FILE.md) or inline description
- **Dependencies:** TASK-XXX, TASK-YYY (if applicable)
- **Created:** YYYY-MM-DD
- **Completed:** YYYY-MM-DD (fill after finish)

**Subtasks (optional):**
- [ ] Subtask 1
- [ ] Subtask 2
```

---

## 🎯 Status legend
- `⏳ PENDING` – waiting to start  
- `🔄 IN_PROGRESS` – currently being executed  
- `✅ COMPLETED` – finished  
- `❌ CANCELLED` – dropped

---

## 🔴 Priority levels
- `🔴 High` – critical, handle ASAP  
- `🟡 Medium` – important but not blocking  
- `🟢 Low` – can be scheduled later

---

## 📝 Examples

### Example 1 – simple task
```markdown
#### `TASK-002` – Add API rate limiting
- **Status:** ⏳ PENDING
- **Priority:** 🟡 Medium
- **Estimated time:** 2 hours
- **Description:** Enable rate limiting using Laravel Throttle middleware
- **Details:** Add middleware to routes/api.php and configure limits in config/throttle.php
- **Dependencies:** none
- **Created:** 2025-01-27
```

### Example 2 – complex task with brief
```markdown
#### `TASK-003` – Implement caching layer
- **Status:** ⏳ PENDING
- **Priority:** 🟡 Medium
- **Estimated time:** 4–6 hours
- **Description:** Add cache layer for frequently used data (movies, people)
- **Details:** [docs/issue/en/CACHING_IMPLEMENTATION.md](./CACHING_IMPLEMENTATION.md)
- **Dependencies:** none
- **Created:** 2025-01-27

**Subtasks:**
- [ ] Create CacheService
- [ ] Add cache tags for movies & people
- [ ] Implement cache invalidation
- [ ] Add tests for cache layer
```

---

## 💡 Tips
1. Keep titles concise (≤60 characters).  
2. Summaries should quickly explain why the task matters.  
3. Move detailed specs to dedicated docs and link them.  
4. List dependencies explicitly.  
5. Break large tasks into subtasks for clarity.

---

**Last updated:** 2025-11-07
