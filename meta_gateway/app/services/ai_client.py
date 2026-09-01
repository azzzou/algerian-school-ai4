"""Client for the CrewAI AI Engine service.

The AI Engine exposes a REST endpoint that accepts a student message and
returns a structured JSON reply (reply_text + extracted_info). This client
calls it asynchronously so the webhook handler can return immediately.
"""

from __future__ import annotations

import logging

import httpx

logger = logging.getLogger(__name__)


class AIClient:
    def __init__(self, base_url: str = "http://ai_engine:8000"):
        # ai_engine is the docker-compose service name.
        self.base_url = base_url.rstrip("/")

    async def get_reply(self, message: str, conversation_id: str | None = None) -> dict:
        """Ask the AI engine for a structured reply to a student message."""
        url = f"{self.base_url}/v1/reply"
        payload = {"message": message}
        if conversation_id:
            payload["conversation_id"] = conversation_id

        try:
            async with httpx.AsyncClient(timeout=120.0) as client:
                resp = await client.post(url, json=payload)
            resp.raise_for_status()
            return resp.json()
        except Exception as exc:  # noqa: BLE001
            logger.exception("AI engine request failed: %s", exc)
            return {
                "reply_text": (
                    "Saha lik! Jaxit l khidma takniya. 3aytelna baad chwiya "
                    "wala messaji. (AI unavailable)"
                ),
                "extracted_info": None,
            }
