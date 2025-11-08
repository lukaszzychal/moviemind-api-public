# GitHub Projects – Setup Guide

Use this guide to create a GitHub Project board for the MovieMind API repository.

## 🎯 What is GitHub Projects?
Kanban-style planning tool that lets you visualise progress, organise issues/PRs, plan sprints, and track priorities.

---

## 🚀 Step 1 – Create a project
1. Navigate to the repo: https://github.com/lukaszzychal/moviemind-api-public.  
2. Click **Projects** → **New project**.  
3. Choose a template (recommended: **Board**).  
4. Name it (e.g. `MovieMind Roadmap`).  
5. Select visibility (Public / Private) and create.

## 📊 Step 2 – Columns
Recommended columns:
| Column | Purpose | Automation |
|--------|---------|------------|
| 📋 Backlog | Ideas & future work | - |
| 🎯 To Do | Planned for upcoming sprint | - |
| 🚧 In Progress | Currently in work | Auto-move when issue assigned |
| 👀 In Review | Awaiting code review | Auto-move when PR opened |
| ✅ Done | Completed | Auto-move when issue/PR closed |
| 📌 Blocked (optional) | Blocked items | - |

## 🔗 Step 3 – Link issues
- From an Issue: use the **Projects** sidebar → “add to project”.  
- From the board: **+ Add item** → select existing Issue or create new.

## 🗺️ Step 4 – Seed with roadmap tasks
Example issues:
- Admin panel for content management.  
- Webhook system for real-time notifications.  
- Advanced analytics and metrics.  
- Multi-tenant support.  
- Content versioning / A/B testing.  
- Integrations with movie databases.
For each, create an issue, add relevant labels (`enhancement`, `future`, etc.), then link to the project.

## 🏷️ Step 5 – Labels
Create a consistent label set under **Settings → Labels** (e.g. `bug`, `enhancement`, `documentation`, `testing`, `refactoring`, `future`, `priority-high`, `priority-medium`, `priority-low`).

## ⚙️ Step 6 – Automation
In project settings go to **Workflows** and add automations such as:
- Move to *In Progress* when an issue gets an assignee.  
- Move to *In Review* when a PR opens.  
- Move to *Done* when issue/PR closes.

## 📈 Step 7 – Insights
Use the **Insights** tab for burndown charts, velocity, filtering by labels, assignees, milestones.

## 💡 Typical flow
1. Add tasks to **Backlog** and prioritise.  
2. Move to **To Do**, assign owner, automation pushes to **In Progress**.  
3. PR opens → goes to **In Review**.  
4. Merge/close → moves to **Done**.

## 🔗 Helpful links
- [GitHub Projects docs](https://docs.github.com/en/issues/planning-and-tracking-with-projects)  
- [Automation](https://docs.github.com/en/issues/planning-and-tracking-with-projects/automating-your-project)

## ✅ Quick checklist
- [ ] Create board.  
- [ ] Configure columns.  
- [ ] Define labels.  
- [ ] Create roadmap issues.  
- [ ] Add issues to board.  
- [ ] Set automation.  
- [ ] Link project from README.

**Polish source:** [`../pl/GITHUB_PROJECTS_SETUP.md`](../pl/GITHUB_PROJECTS_SETUP.md)
