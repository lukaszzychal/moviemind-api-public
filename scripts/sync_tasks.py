#!/usr/bin/env python3
"""Synchronise backlog file with GitHub Issues."""
from __future__ import annotations

import json
import os
import re
import textwrap
import urllib.error
import urllib.request
from typing import Any

TASK_FILES = [
    "docs/issue/pl/TASKS.md",
    "docs/issue/TASKS.md",
]  # allow backward compatibility until all branches updated
TASK_HEADER_PATTERN = re.compile(r"^#### `(?P<id>TASK-\d+)` - (?P<title>.+)$")


class GitHubClient:
    def __init__(self, repo: str, token: str) -> None:
        self.repo = repo
        self.headers = {
            "Authorization": f"Bearer {token}",
            "Accept": "application/vnd.github+json",
            "User-Agent": "task-sync-script",
        }

    def _request(self, method: str, url: str, payload: dict[str, Any] | None = None) -> Any:
        data = None
        headers = dict(self.headers)
        if payload is not None:
            data = json.dumps(payload).encode("utf-8")
            headers["Content-Type"] = "application/json"

        req = urllib.request.Request(url, data=data, headers=headers, method=method)
        with urllib.request.urlopen(req) as resp:
            body = resp.read().decode("utf-8")
            return json.loads(body) if body else None

    def ensure_label(self, name: str, color: str = "0e8a16") -> None:
        url = f"https://api.github.com/repos/{self.repo}/labels/{name}"
        try:
            self._request("GET", url)
            return
        except urllib.error.HTTPError as exc:  # pragma: no cover
            if exc.code != 404:
                raise
        self._request("POST", f"https://api.github.com/repos/{self.repo}/labels", {"name": name, "color": color})

    def list_issues(self) -> dict[str, dict[str, Any]]:
        issues: dict[str, dict[str, Any]] = {}
        page = 1
        while True:
            url = f"https://api.github.com/repos/{self.repo}/issues?state=all&per_page=100&page={page}"
            page_data = self._request("GET", url)
            if not page_data:
                break
            for issue in page_data:
                if "pull_request" in issue:
                    continue
                match = re.match(r"\[(TASK-\d+)]", issue["title"])
                if match:
                    issues[match.group(1)] = issue
            page += 1
        return issues

    def create_issue(self, task: dict[str, Any]) -> None:
        self.ensure_label("task")
        
        labels = ["task"]
        if task.get("priority"):
            priority_label = f"priority-{task['priority']}"
            priority_colors = {
                "high": "d73a4a",    # Red
                "medium": "fbca04",  # Yellow
                "low": "0e8a16"      # Green
            }
            self.ensure_label(priority_label, priority_colors.get(task["priority"], "0e8a16"))
            labels.append(priority_label)
        
        payload = {
            "title": task["title_formatted"],
            "body": task["body"],
            "labels": labels,
        }
        self._request("POST", f"https://api.github.com/repos/{self.repo}/issues", payload)

    def update_issue(self, issue: dict[str, Any], task: dict[str, Any]) -> None:
        labels = ["task"]
        if task.get("priority"):
            priority_label = f"priority-{task['priority']}"
            priority_colors = {
                "high": "d73a4a",    # Red
                "medium": "fbca04",  # Yellow
                "low": "0e8a16"      # Green
            }
            self.ensure_label(priority_label, priority_colors.get(task["priority"], "0e8a16"))
            labels.append(priority_label)
        
        payload = {
            "title": task["title_formatted"],
            "body": task["body"],
            "state": "closed" if task["status"].startswith(("✅", "❌")) else "open",
            "labels": labels,
        }
        self._request("PATCH", f"https://api.github.com/repos/{self.repo}/issues/{issue['number']}", payload)


def parse_tasks(content: str) -> list[dict[str, Any]]:
    tasks: list[dict[str, Any]] = []
    lines = content.splitlines()
    i = 0
    while i < len(lines):
        match = TASK_HEADER_PATTERN.match(lines[i])
        if match:
            task_id = match.group("id")
            title = match.group("title")
            block: list[str] = []
            i += 1
            while i < len(lines):
                line = lines[i]
                if line.startswith("#### `TASK-") or line.startswith("## ") or line.strip() == "---":
                    break
                block.append(line)
                i += 1
            raw_block = "\n".join(block).strip()
            status = extract_field(block, "Status") or ""
            tasks.append(
                {
                    "id": task_id,
                    "title": title,
                    "title_formatted": f"[{task_id}] {title}",
                    "raw_block": raw_block,
                    "status": status,
                    "priority": extract_priority(block),
                    "body": build_issue_body(raw_block),
                }
            )
            continue
        i += 1
    return tasks


def extract_field(block: list[str], field: str) -> str | None:
    prefix = f"- **{field}:**"
    for line in block:
        if line.strip().startswith(prefix):
            return line.split("**", 2)[2].strip().lstrip(":").strip()
    return None


def extract_priority(block: list[str]) -> str | None:
    """Extract priority emoji from block and map to label name."""
    for line in block:
        if "**Priorytet:**" in line or "**Priority:**" in line:
            if "🔴" in line:
                return "high"
            elif "🟡" in line:
                return "medium"
            elif "🟢" in line:
                return "low"
    return None


def build_issue_body(raw_block: str) -> str:
    return textwrap.dedent(
        f"""
        ## Task metadata

        ```md
        {raw_block}
        ```
        """
    ).strip()


def main() -> None:
    repo = os.environ["GITHUB_REPOSITORY"]
    token = os.environ["GITHUB_TOKEN"]

    for candidate in TASK_FILES:
        if os.path.exists(candidate):
            with open(candidate, "r", encoding="utf-8") as fh:
                content = fh.read()
            break
    else:  # pragma: no cover - fail fast in CI
        available = ", ".join(TASK_FILES)
        raise FileNotFoundError(f"None of the task files exist. Checked: {available}")

    tasks = parse_tasks(content)
    client = GitHubClient(repo, token)
    existing = client.list_issues()

    for task in tasks:
        issue = existing.get(task["id"])
        if issue:
            client.update_issue(issue, task)
        else:
            client.create_issue(task)


if __name__ == "__main__":
    main()
