from __future__ import annotations

from fastapi import APIRouter, Depends, Header, HTTPException

from config import settings
from models.snippet import SnippetCreateRequest, SnippetResponse
from services.file_service import FileService

router = APIRouter(prefix="/snippets", tags=["snippets"])
service = FileService()


def _authorize(authorization: str | None = Header(default=None)) -> None:
    expected = settings.token

    if expected == "":
        raise HTTPException(status_code=500, detail="SNIPPET_API_TOKEN is not configured")

    if authorization is None or not authorization.startswith("Bearer "):
        raise HTTPException(status_code=401, detail="unauthorized")

    token = authorization.removeprefix("Bearer ").strip()

    if token != expected:
        raise HTTPException(status_code=401, detail="unauthorized")


def _validate_vm_id(vm_id: int) -> int:
    if vm_id < 100 or vm_id > 999_999_999:
        raise HTTPException(status_code=400, detail="vm_id must be between 100 and 999999999")

    return vm_id


@router.post("/{vm_id}", response_model=SnippetResponse, dependencies=[Depends(_authorize)])
def create_snippet(vm_id: int, payload: SnippetCreateRequest) -> SnippetResponse:
    normalized = _validate_vm_id(vm_id)

    return service.create(normalized, payload)


@router.get("/{vm_id}", response_model=SnippetResponse, dependencies=[Depends(_authorize)])
def get_snippet(vm_id: int) -> SnippetResponse:
    normalized = _validate_vm_id(vm_id)
    result = service.get(normalized)

    if result is None:
        raise HTTPException(status_code=404, detail="snippet not found")

    return result


@router.delete("/{vm_id}", dependencies=[Depends(_authorize)])
def delete_snippet(vm_id: int) -> dict[str, str]:
    normalized = _validate_vm_id(vm_id)
    service.delete(normalized)

    return {"status": "deleted"}


@router.get("", response_model=list[SnippetResponse], dependencies=[Depends(_authorize)])
def list_snippets() -> list[SnippetResponse]:
    return service.list_all()
