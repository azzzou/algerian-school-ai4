"""Gateway configuration loaded from environment variables."""

from __future__ import annotations

import os
from dataclasses import dataclass


@dataclass
class Settings:
    app_secret: str
    verify_token: str
    page_access_token: str
    ai_engine_url: str = "http://ai_engine:8000"

    @classmethod
    def from_env(cls) -> "Settings":
        return cls(
            app_secret=os.getenv("MESSENGER_APP_SECRET", ""),
            verify_token=os.getenv("MESSENGER_VERIFY_TOKEN", ""),
            page_access_token=os.getenv("MESSENGER_PAGE_ACCESS_TOKEN", ""),
            ai_engine_url=os.getenv("AI_ENGINE_URL", "http://ai_engine:8000"),
        )


def get_settings() -> Settings:
    return Settings.from_env()
