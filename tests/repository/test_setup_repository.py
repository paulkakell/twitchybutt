import importlib.util
from pathlib import Path
import unittest
from unittest.mock import Mock, patch

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location("setup_repository", ROOT / "scripts/setup_repository.py")
sut = importlib.util.module_from_spec(spec)
spec.loader.exec_module(sut)


def connection(rows, more=False, cursor=None):
    return {"repository": {"discussions": {"nodes": rows, "pageInfo": {"hasNextPage": more, "endCursor": cursor}}}}


class RepositorySetupTests(unittest.TestCase):
    def setUp(self):
        self.env = {"GITHUB_REPOSITORY": sut.REPOSITORY, "GITHUB_REF": "refs/heads/main",
                    "GITHUB_EVENT_NAME": "push", "GITHUB_SHA": "a" * 40}
        self.repo = {"id": "R_test", "defaultBranchRef": {"name": "main", "target": {"oid": "a" * 40}},
                     "hasDiscussionsEnabled": True,
                     "discussionCategories": {"pageInfo": {"hasNextPage": False}, "nodes": [
                         {"slug": "q-a", "id": "qa", "isAnswerable": True},
                         {"slug": "ideas", "id": "ideas", "isAnswerable": False},
                         {"slug": "announcements", "id": "news", "isAnswerable": False}]}}

    def test_expected_main_is_allowed(self):
        sut.guard_apply(self.env, self.repo)

    def test_fork_is_rejected(self):
        self.env["GITHUB_REPOSITORY"] = "someone/fork"
        with self.assertRaises(RuntimeError): sut.guard_apply(self.env, self.repo)

    def test_non_main_is_rejected(self):
        self.env["GITHUB_REF"] = "refs/heads/development"
        with self.assertRaises(RuntimeError): sut.guard_apply(self.env, self.repo)

    def test_pull_request_event_is_rejected(self):
        self.env["GITHUB_EVENT_NAME"] = "pull_request"
        with self.assertRaises(RuntimeError): sut.guard_apply(self.env, self.repo)

    def test_stale_head_is_rejected(self):
        self.env["GITHUB_SHA"] = "b" * 40
        with self.assertRaises(RuntimeError): sut.guard_apply(self.env, self.repo)

    def test_changed_default_branch_is_rejected(self):
        self.repo["defaultBranchRef"]["name"] = "other"
        with self.assertRaises(RuntimeError): sut.guard_apply(self.env, self.repo)

    def test_read_only_rest_mutation_is_rejected(self):
        api = sut.GitHubAPI("synthetic-token")
        with patch.object(api, "_request") as request:
            with self.assertRaises(RuntimeError): api.rest("/repos/test/test/labels", "POST", {})
            request.assert_not_called()

    def test_read_only_graphql_mutation_is_rejected(self):
        api = sut.GitHubAPI("synthetic-token")
        with patch.object(api, "_request") as request:
            with self.assertRaises(RuntimeError): api.graphql(sut.CREATE, {})
            request.assert_not_called()

    def test_graphql_partial_error_is_rejected(self):
        api = sut.GitHubAPI("synthetic-token")
        with patch.object(api, "_request", return_value={"data": {"repository": {}}, "errors": [{}]}):
            with self.assertRaises(RuntimeError): api.graphql(sut.SNAPSHOT, {})

    def test_missing_token_is_rejected(self):
        with self.assertRaises(RuntimeError): sut.GitHubAPI("")

    def test_redirect_is_rejected(self):
        with self.assertRaises(RuntimeError): sut.NoRedirect().redirect_request(None, None, 302, "", {}, "https://example.com")

    def test_expected_categories(self):
        self.assertEqual("news", sut.verify_categories(self.repo))

    def test_missing_category_is_rejected(self):
        self.repo["discussionCategories"]["nodes"].pop(0)
        with self.assertRaises(RuntimeError): sut.verify_categories(self.repo)

    def test_non_answerable_qa_is_rejected(self):
        self.repo["discussionCategories"]["nodes"][0]["isAnswerable"] = False
        with self.assertRaises(RuntimeError): sut.verify_categories(self.repo)

    def test_incomplete_categories_are_rejected(self):
        self.repo["discussionCategories"]["pageInfo"]["hasNextPage"] = True
        with self.assertRaises(RuntimeError): sut.verify_categories(self.repo)

    def test_disabled_discussions_are_rejected(self):
        self.repo["hasDiscussionsEnabled"] = False
        with self.assertRaises(RuntimeError): sut.verify_categories(self.repo)

    def test_existing_labels_are_preserved(self):
        api = Mock()
        api.rest.return_value = [{"name": name} for name in sut.LABELS]
        self.assertEqual([], sut.ensure_labels(api))
        self.assertEqual(1, api.rest.call_count)

    def test_missing_labels_are_created(self):
        api = Mock()
        api.rest.return_value = []
        self.assertEqual(list(sut.LABELS), sut.ensure_labels(api))
        self.assertEqual(len(sut.LABELS) + 1, api.rest.call_count)

    def test_welcome_pagination_and_no_overwrite(self):
        api = Mock()
        row = {"body": sut.MARKER + " edited by maintainer", "author": {"login": "github-actions[bot]"}, "url": "existing"}
        api.graphql.side_effect = [connection([], True, "next"), connection([row])]
        self.assertEqual("existing", sut.ensure_welcome(api, self.repo, "news", sut.MARKER))
        self.assertEqual(2, api.graphql.call_count)
        self.assertEqual("next", api.graphql.call_args.args[1]["after"])

    def test_foreign_marker_does_not_impersonate_welcome(self):
        api = Mock()
        api.graphql.return_value = connection([{"body": sut.MARKER, "author": {"login": "stranger"}, "url": "foreign"}])
        self.assertIsNone(sut.find_welcome(api))

    def test_invalid_pagination_is_rejected(self):
        api = Mock()
        api.graphql.return_value = connection([], True, "same")
        with self.assertRaises(RuntimeError): sut.find_welcome(api)

    def test_missing_marker_is_rejected_before_network(self):
        api = Mock()
        with self.assertRaises(RuntimeError): sut.ensure_welcome(api, self.repo, "news", "not marked")
        api.graphql.assert_not_called()

    def test_create_welcome_uses_explicit_repository_category_and_body(self):
        api = Mock()
        api.graphql.side_effect = [connection([]), {"createDiscussion": {"discussion": {"url": "created"}}}]
        body = sut.MARKER + "\nWelcome"
        self.assertEqual("created", sut.ensure_welcome(api, self.repo, "news", body))
        data = api.graphql.call_args.args[1]["input"]
        self.assertEqual(("R_test", "news", body), (data["repositoryId"], data["categoryId"], data["body"]))


if __name__ == "__main__":
    unittest.main()
