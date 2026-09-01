"""Messenger Send API client.

Minimal client for sending text replies back through the Meta Messenger
Send API. Uses httpx so it can be awaited from async FastAPI handlers.
"""

from __future__ import annotations

import logging

import httpx

logger = logging.getLogger(__name__)

GRAPH_API_BASE = "https://graph.facebook.com/v19.0"


class MessengerClient:
    def __init__(self, page_access_token: str, base_url: str = GRAPH_API_BASE):
        self.page_access_token = page_access_token
        self.base_url = base_url

    async def send_text(self, recipient_id: str, text: str) -> bool:
        """Send a plain text message to a user via the Send API."""
        url = f"{self.base_url}/me/messages"
        payload = {
            "recipient": {"id": recipient_id},
            "message": {"text": text},
            "messaging_type": "RESPONSE",
        }
        headers = {
            "Authorization": f"Bearer {self.page_access_token}",
            "Content-Type": "application/json",
        }
        try:
            async with httpx.AsyncClient(timeout=10.0) as client:
                resp = await client.post(url, json=payload, headers=headers)
            resp.raise_for_status()
            logger.info("Sent message to %s", recipient_id)
            return True
        except Exception as exc:  # noqa: BLE001
            logger.exception("Failed to send message to %s: %s", recipient_id, exc)
            return False
