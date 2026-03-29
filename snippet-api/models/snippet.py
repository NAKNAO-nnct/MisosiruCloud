from __future__ import annotations

from datetime import datetime, timezone
from typing import Any

from pydantic import BaseModel, Field, field_validator


class SnippetCreateRequest(BaseModel):
    user_data: str = Field(min_length=1, max_length=1024 * 1024)
    network_config: str | None = Field(default=None, max_length=256 * 1024)
    meta_data: str | None = Field(default=None, max_length=256 * 1024)

    @field_validator("user_data", "network_config", "meta_data")
    @classmethod
    def validate_yaml_like_content(cls, value: str | None) -> str | None:
        if value is None:
            return value

        text = value.strip()
        if text == "":
            return value

        # Dependency-free lightweight guard: reject content without any YAML-like key/value marker.
        if ":" not in text:
            raise ValueError("content must look like YAML")

        return value


class SnippetResponse(BaseModel):
    vm_id: str
    files: dict[str, str]
    created_at: str
    updated_at: str


class ErrorResponse(BaseModel):
    error: dict[str, Any]


def now_iso() -> str:
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z")
