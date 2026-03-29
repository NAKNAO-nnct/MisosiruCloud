from __future__ import annotations

import os
from pathlib import Path


class Settings:
    def __init__(self) -> None:
        self.token = os.getenv("SNIPPET_API_TOKEN", "")
        self.snippet_path = Path(os.getenv("SNIPPET_PATH", "/var/lib/vz/snippets"))
        self.snippet_path.mkdir(parents=True, exist_ok=True)


settings = Settings()
