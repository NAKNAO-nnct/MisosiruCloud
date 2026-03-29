import os
import tempfile
import unittest

from fastapi.testclient import TestClient


class SnippetApiTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.temp_dir = tempfile.TemporaryDirectory()
        os.environ["SNIPPET_API_TOKEN"] = "unit-token"
        os.environ["SNIPPET_PATH"] = cls.temp_dir.name

        from main import app

        cls.client = TestClient(app)

    @classmethod
    def tearDownClass(cls) -> None:
        cls.temp_dir.cleanup()

    def test_health(self) -> None:
        response = self.client.get("/health")

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.json().get("status"), "ok")

    def test_create_get_list_and_delete_snippet(self) -> None:
        create_response = self.client.post(
            "/snippets/100",
            headers={"Authorization": "Bearer unit-token"},
            json={
                "user_data": "#cloud-config\nhostname: vm-100\n",
                "network_config": "version: 2\n",
                "meta_data": "instance-id: vm-100\n",
            },
        )

        self.assertEqual(create_response.status_code, 200)
        self.assertEqual(create_response.json().get("vm_id"), "100")

        get_response = self.client.get(
            "/snippets/100",
            headers={"Authorization": "Bearer unit-token"},
        )

        self.assertEqual(get_response.status_code, 200)
        self.assertEqual(get_response.json().get("vm_id"), "100")

        list_response = self.client.get(
            "/snippets",
            headers={"Authorization": "Bearer unit-token"},
        )

        self.assertEqual(list_response.status_code, 200)
        self.assertTrue(any(item.get("vm_id") == "100" for item in list_response.json()))

        delete_response = self.client.delete(
            "/snippets/100",
            headers={"Authorization": "Bearer unit-token"},
        )

        self.assertEqual(delete_response.status_code, 200)
        self.assertEqual(delete_response.json().get("status"), "deleted")

    def test_reject_invalid_vm_id(self) -> None:
        response = self.client.post(
            "/snippets/99",
            headers={"Authorization": "Bearer unit-token"},
            json={"user_data": "hostname: vm-99\n"},
        )

        self.assertEqual(response.status_code, 400)

        negative = self.client.post(
            "/snippets/-1",
            headers={"Authorization": "Bearer unit-token"},
            json={"user_data": "hostname: vm-negative\n"},
        )

        self.assertEqual(negative.status_code, 400)

        overflow = self.client.post(
            "/snippets/1000000000",
            headers={"Authorization": "Bearer unit-token"},
            json={"user_data": "hostname: vm-overflow\n"},
        )

        self.assertEqual(overflow.status_code, 400)

    def test_reject_oversized_user_data(self) -> None:
        too_large = "a: " + ("x" * (1024 * 1024 + 1))

        response = self.client.post(
            "/snippets/102",
            headers={"Authorization": "Bearer unit-token"},
            json={"user_data": too_large},
        )

        self.assertEqual(response.status_code, 400)
        self.assertEqual(response.json().get("error", {}).get("code"), "VALIDATION_ERROR")

    def test_reject_invalid_yaml_like_payload(self) -> None:
        response = self.client.post(
            "/snippets/101",
            headers={"Authorization": "Bearer unit-token"},
            json={"user_data": "not-yaml-content"},
        )

        self.assertEqual(response.status_code, 400)
        self.assertEqual(response.json().get("error", {}).get("code"), "VALIDATION_ERROR")


if __name__ == "__main__":
    unittest.main()
