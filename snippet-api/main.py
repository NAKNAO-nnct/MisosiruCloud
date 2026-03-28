from __future__ import annotations

import os
from pathlib import Path

from fastapi import Depends, FastAPI, Header, HTTPException, Response
from pydantic import BaseModel

app = FastAPI(title="Snippet Sidecar API")


class SnippetPayload(BaseModel):
    filename: str
    content: str


def _token() -> str:
    return os.getenv("SNIPPET_API_TOKEN", "")


def _snippet_path() -> Path:
    raw = os.getenv("SNIPPET_PATH", "/var/lib/vz/snippets")
    path = Path(raw)
    path.mkdir(parents=True, exist_ok=True)
    return path


def _validate_filename(filename: str) -> str:
    if filename.strip() == "":
        raise HTTPException(status_code=400, detail="filename is required")

    if ".." in filename or "/" in filename or "\\" in filename:
        raise HTTPException(status_code=400, detail="invalid filename")

    return filename


def _authorize(authorization: str | None = Header(default=None)) -> None:
    expected = _token()

    if expected == "":
        raise HTTPException(status_code=500, detail="SNIPPET_API_TOKEN is not configured")

    if authorization is None or not authorization.startswith("Bearer "):
        raise HTTPException(status_code=401, detail="unauthorized")

    token = authorization.removeprefix("Bearer ").strip()

    if token != expected:
        raise HTTPException(status_code=401, detail="unauthorized")


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/snippets", dependencies=[Depends(_authorize)])
def create_snippet(payload: SnippetPayload) -> dict[str, str]:
    filename = _validate_filename(payload.filename)
    target = _snippet_path() / filename

    target.write_text(payload.content, encoding="utf-8")

    return {"filename": filename, "path": str(target)}


@app.delete("/snippets/{filename}", dependencies=[Depends(_authorize)], status_code=204)
def delete_snippet(filename: str) -> Response:
    normalized = _validate_filename(filename)
    target = _snippet_path() / normalized

    if target.exists():
        target.unlink()

    return Response(status_code=204)
