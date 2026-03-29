from __future__ import annotations

from pathlib import Path

from config import settings
from models.snippet import SnippetCreateRequest, SnippetResponse, now_iso


class FileService:
    def __init__(self, base_path: Path | None = None) -> None:
        self.base_path = base_path or settings.snippet_path

    def create(self, vm_id: int, payload: SnippetCreateRequest) -> SnippetResponse:
        file_map = {
            "user_data": self.base_path / f"{vm_id}-user.yaml",
            "network_config": self.base_path / f"{vm_id}-network.yaml",
            "meta_data": self.base_path / f"{vm_id}-meta.yaml",
        }

        file_map["user_data"].write_text(payload.user_data, encoding="utf-8")

        if payload.network_config is not None:
            file_map["network_config"].write_text(payload.network_config, encoding="utf-8")
        elif file_map["network_config"].exists():
            file_map["network_config"].unlink()

        if payload.meta_data is not None:
            file_map["meta_data"].write_text(payload.meta_data, encoding="utf-8")
        elif file_map["meta_data"].exists():
            file_map["meta_data"].unlink()

        timestamp = now_iso()

        return SnippetResponse(
            vm_id=str(vm_id),
            files={
                "user_data": str(file_map["user_data"]),
                "network_config": str(file_map["network_config"]),
                "meta_data": str(file_map["meta_data"]),
            },
            created_at=timestamp,
            updated_at=timestamp,
        )

    def get(self, vm_id: int) -> SnippetResponse | None:
        user = self.base_path / f"{vm_id}-user.yaml"
        network = self.base_path / f"{vm_id}-network.yaml"
        meta = self.base_path / f"{vm_id}-meta.yaml"

        if not user.exists() and not network.exists() and not meta.exists():
            return None

        timestamp = now_iso()

        return SnippetResponse(
            vm_id=str(vm_id),
            files={
                "user_data": str(user),
                "network_config": str(network),
                "meta_data": str(meta),
            },
            created_at=timestamp,
            updated_at=timestamp,
        )

    def delete(self, vm_id: int) -> None:
        for path in [
            self.base_path / f"{vm_id}-user.yaml",
            self.base_path / f"{vm_id}-network.yaml",
            self.base_path / f"{vm_id}-meta.yaml",
        ]:
            if path.exists():
                path.unlink()

    def list_all(self) -> list[SnippetResponse]:
        vm_ids: set[int] = set()

        for path in self.base_path.glob("*-user.yaml"):
            prefix = path.name.split("-", 1)[0]
            if prefix.isdigit():
                vm_ids.add(int(prefix))

        responses: list[SnippetResponse] = []

        for vm_id in sorted(vm_ids):
            response = self.get(vm_id)

            if response is not None:
                responses.append(response)

        return responses
