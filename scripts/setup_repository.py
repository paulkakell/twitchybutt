"""Inspect community settings; explicitly apply only on this repository's main branch."""
from __future__ import annotations

import argparse
import json
import os
from pathlib import Path
from typing import Any
import urllib.error
import urllib.request

REPOSITORY = "paulkakell/twitchybutt"
OWNER, NAME = REPOSITORY.split("/")
MARKER = "<!-- twitchybutt-community-welcome-v1 -->"
TITLE = "Welcome: start here, ask questions and propose ideas"
ROOT = Path(__file__).resolve().parents[1]
LABELS = {
    "bug": ("d73a4a", "A reproducible defect"),
    "documentation": ("0075ca", "Documentation correction or improvement"),
    "enhancement": ("a2eeef", "An accepted feature or improvement proposal"),
    "needs-triage": ("ededed", "Needs maintainer assessment"),
    "release-blocked": ("b60205", "Required release evidence or implementation is incomplete"),
    "dependencies": ("0366d6", "Dependency maintenance"),
    "security": ("d93f0b", "Public-safe security policy or remediation tracking"),
}
SNAPSHOT = """query($owner:String!,$name:String!){
  repository(owner:$owner,name:$name){id hasDiscussionsEnabled
    defaultBranchRef{name target{oid}} fundingLinks{platform url}
    discussionCategories(first:100){nodes{id name slug isAnswerable} pageInfo{hasNextPage}}
  }
  user(login:$owner){hasSponsorsListing sponsorsListing{isPublic}}
}"""
DISCUSSIONS = """query($owner:String!,$name:String!,$after:String){
  repository(owner:$owner,name:$name){discussions(first:100,after:$after){
    nodes{url body author{login}} pageInfo{hasNextPage endCursor}
  }}
}"""
CREATE = """mutation($input:CreateDiscussionInput!){
  createDiscussion(input:$input){discussion{url}}
}"""


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        raise RuntimeError("Refusing an API redirect; credentials remain on api.github.com")


class GitHubAPI:
    def __init__(self, token: str, apply: bool = False):
        if not token:
            raise RuntimeError("GH_TOKEN is required")
        self.token = token
        self.apply = apply

    def _request(self, path: str, method: str, payload: Any = None) -> Any:
        if not path.startswith("/") or path.startswith("//"):
            raise ValueError("Expected a GitHub API path")
        request = urllib.request.Request(
            "https://api.github.com" + path,
            data=None if payload is None else json.dumps(payload).encode(),
            method=method,
            headers={"Authorization": "Bearer " + self.token,
                     "Accept": "application/vnd.github+json",
                     "X-GitHub-Api-Version": "2022-11-28",
                     "Content-Type": "application/json", "User-Agent": "twitchybutt-community-setup"},
        )
        try:
            with urllib.request.build_opener(NoRedirect).open(request, timeout=30) as response:
                return json.load(response)
        except urllib.error.HTTPError as error:
            # Never print request headers, tokens, or arbitrary API response bodies.
            raise RuntimeError(f"GitHub API {method} failed with HTTP {error.code}") from None

    def rest(self, path: str, method: str = "GET", payload: Any = None) -> Any:
        if method != "GET" and not self.apply:
            raise RuntimeError("Read-only inspection cannot mutate repository state")
        return self._request(path, method, payload)

    def graphql(self, query: str, variables: dict[str, Any]) -> Any:
        if not query.lstrip().startswith("query") and not self.apply:
            raise RuntimeError("Read-only inspection cannot execute a mutation")
        response = self._request("/graphql", "POST", {"query": query, "variables": variables})
        if response.get("errors") or not response.get("data"):
            raise RuntimeError("GraphQL failed; no successful or complete result is claimed")
        return response["data"]


def guard_apply(env: dict[str, str], repository: dict[str, Any]) -> None:
    if env.get("GITHUB_REPOSITORY") != REPOSITORY or env.get("GITHUB_REF") != "refs/heads/main":
        raise RuntimeError("Writes are restricted to the expected repository's main branch")
    if env.get("GITHUB_EVENT_NAME") not in {"push", "workflow_dispatch"}:
        raise RuntimeError("Writes are not permitted for this workflow event")
    branch = repository.get("defaultBranchRef") or {}
    if branch.get("name") != "main" or branch.get("target", {}).get("oid") != env.get("GITHUB_SHA"):
        raise RuntimeError("Default branch or head changed; inspect the current main candidate")


def verify_categories(repository: dict[str, Any]) -> str:
    categories = repository["discussionCategories"]
    if not repository["hasDiscussionsEnabled"] or categories["pageInfo"]["hasNextPage"]:
        raise RuntimeError("Discussions disabled or category inventory incomplete; administrator action required")
    by_slug = {row["slug"]: row for row in categories["nodes"]}
    if not {"q-a", "ideas"}.issubset(by_slug) or not by_slug["q-a"]["isAnswerable"]:
        raise RuntimeError("Q&A/Ideas category setup needs administrator attention")
    category = by_slug.get("announcements") or by_slug.get("general")
    if category is None:
        raise RuntimeError("No Announcements or General category; no welcome post created")
    return category["id"]


def ensure_labels(api: GitHubAPI) -> list[str]:
    existing: set[str] = set()
    for page in range(1, 101):
        rows = api.rest(f"/repos/{REPOSITORY}/labels?per_page=100&page={page}")
        existing.update(row["name"].lower() for row in rows)
        if len(rows) < 100:
            break
    else:
        raise RuntimeError("Label inventory exceeded safety bound; no labels changed")
    created = []
    for name, (color, description) in LABELS.items():
        if name not in existing:
            api.rest(f"/repos/{REPOSITORY}/labels", "POST",
                     {"name": name, "color": color, "description": description})
            created.append(name)
    return created


def find_welcome(api: GitHubAPI) -> str | None:
    cursor = None
    seen: set[str] = set()
    for _ in range(100):
        connection = api.graphql(DISCUSSIONS, {"owner": OWNER, "name": NAME, "after": cursor})["repository"]["discussions"]
        for row in connection["nodes"]:
            author = (row.get("author") or {}).get("login")
            if MARKER in row["body"] and author in {OWNER, "github-actions[bot]"}:
                return row["url"]
        page = connection["pageInfo"]
        if not page["hasNextPage"]:
            return None
        cursor = page.get("endCursor")
        if not cursor or cursor in seen:
            raise RuntimeError("Invalid discussion pagination; refusing to create a duplicate")
        seen.add(cursor)
    raise RuntimeError("Discussion inventory exceeded safety bound; no post created")


def ensure_welcome(api: GitHubAPI, repository: dict[str, Any], category_id: str, body: str) -> str:
    if MARKER not in body:
        raise RuntimeError("Welcome source is missing its idempotency marker")
    existing = find_welcome(api)
    if existing:
        return existing  # Preserve edits and replies; never overwrite an existing discussion.
    data = api.graphql(CREATE, {"input": {"repositoryId": repository["id"], "categoryId": category_id,
                                         "title": TITLE, "body": body}})
    return data["createDiscussion"]["discussion"]["url"]


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--apply", action="store_true", help="Create missing labels and one welcome discussion")
    args = parser.parse_args()
    api = GitHubAPI(os.environ.get("GH_TOKEN", ""), apply=args.apply)
    snapshot = api.graphql(SNAPSHOT, {"owner": OWNER, "name": NAME})
    repository = snapshot["repository"]
    if not repository:
        raise RuntimeError("Repository metadata unavailable")
    report = {"repository": REPOSITORY, "applied": args.apply,
              "discussions_enabled": repository["hasDiscussionsEnabled"],
              "category_slugs": [row["slug"] for row in repository["discussionCategories"]["nodes"]],
              "sponsors": snapshot["user"], "funding_links": repository["fundingLinks"],
              "native_dependabot_rules": "not configured or verified by this workflow",
              "admin_security_settings": "not configured or verified by this workflow"}
    if args.apply:
        guard_apply(dict(os.environ), repository)
        category_id = verify_categories(repository)
        body = (ROOT / ".github/DISCUSSION_WELCOME.md").read_text()
        report["created_labels"] = ensure_labels(api)
        report["welcome_url"] = ensure_welcome(api, repository, category_id, body)
    text = json.dumps(report, indent=2, sort_keys=True)
    print(text)
    if os.environ.get("GITHUB_STEP_SUMMARY"):
        with open(os.environ["GITHUB_STEP_SUMMARY"], "a") as summary:
            summary.write("## Repository setup evidence\n\n```json\n" + text + "\n```\n")


if __name__ == "__main__":
    try:
        main()
    except (RuntimeError, ValueError, KeyError, OSError) as error:
        raise SystemExit(str(error)) from None
