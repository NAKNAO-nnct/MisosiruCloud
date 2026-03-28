import os
import tempfile
import unittest

from fastapi.testclient import TestClient


class SnippetApiTest(unittest.TestCase):
    def setUp(self) -> None:
        self.temp_dir = tempfile.TemporaryDirectory()
        os.environ["SNIPPET_API_TOKEN"] = "unit-token"
        os.environ["SNIPPET_PATH"] = self.temp_dir.name

        from main import app

        self.client = TestClient(app)

    def tearDown(self) -> None:
        self.temp_dir.cleanup()

    def test_health(self) -> None:
        response = self.client.get("/health")

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.json().get("status"), "ok")

    def test_reject_path_traversal_filename(self) -> None:
        response = self.client.post(
            "/snippets",
            headers={"Authorization": "Bearer unit-token"},
            json={"filename": "../etc/passwd", "content": "x"},
        )

        self.assertEqual(response.status_code, 400)

    def test_write_and_delete_snippet(self) -> None:
        response = self.client.post(
            "/snippets",
            headers={"Authorization": "Bearer unit-token"},
            json={"filename": "vm-100-user-data.yaml", "content": "#cloud-config\n"},
        )

        self.assertEqual(response.status_code, 200)

        delete_response = self.client.delete(
            "/snippets/vm-100-user-data.yaml",
            headers={"Authorization": "Bearer unit-token"},
        )

        self.assertEqual(delete_response.status_code, 204)


if __name__ == "__main__":
    unittest.main()
