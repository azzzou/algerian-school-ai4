"""FastAPI Messenger webhook gateway.

Features
--------
- Verifies every webhook delivery via X-Hub-Signature-256 (HMAC-SHA256).
- Returns HTTP 200 immediately and processes the message in the background
  (Starlette BackgroundTasks) to avoid Messenger's 20s timeout.
- Forwards the student message to the CrewAI AI Engine and sends the reply
  back to the user through the Messenger Send API.
"""

from __future__ import annotations

import logging
from typing import Any, Optional

from fastapi import BackgroundTasks, FastAPI, HTTPException, Query, Request
from fastapi.responses import JSONResponse

from app.config import Settings, get_settings
from app.security import validate_signature
from app.services.ai_client import AIClient
from app.services.messenger import MessengerClient

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


app = FastAPI(
    title="Algerian School Support Messenger Gateway",
    description=(
        "Meta Messenger webhook gateway that verifies signatures, replies "
        "async, and routes to the CrewAI engine."
    ),
    version="1.0.0",
)


@app.on_event("startup")
async def startup() -> None:
    settings = get_settings()
    app.state.settings = settings
    app.state.messenger = MessengerClient(page_access_token=settings.page_access_token)
    app.state.ai_client = AIClient(base_url=settings.ai_engine_url)


async def _process_message(sender_id: str, text: str) -> None:
    """Background processing: ask AI engine, then send the reply to the user."""
    settings: Settings = app.state.settings
    messenger: MessengerClient = app.state.messenger
    ai: AIClient = app.state.ai_client

    result = await ai.get_reply(message=text, conversation_id=sender_id)
    reply_text = result.get("reply_text", "")

    if reply_text:
        await messenger.send_text(recipient_id=sender_id, text=reply_text)
    else:
        logger.warning("Empty reply for sender %s", sender_id)


@app.get("/webhook", include_in_schema=False)
async def verify_webhook(
    hub_mode: Optional[str] = Query(None, alias="hub.mode"),
    hub_verify_token: Optional[str] = Query(None, alias="hub.verify_token"),
    hub_challenge: Optional[str] = Query(None, alias="hub.challenge"),
) -> JSONResponse:
    """GET endpoint Facebook uses to validate the webhook subscription."""
    settings: Settings = app.state.settings

    if hub_mode == "subscribe" and hub_verify_token == settings.verify_token:
        logger.info("Webhook verified.")
        return JSONResponse(content=hub_challenge, status_code=200)

    logger.warning(
        "Webhook verification failed (mode=%s)", hub_mode,
    )
    raise HTTPException(status_code=403, detail="Verification token mismatch.")


@app.post("/webhook", include_in_schema=False)
async def receive_webhook(
    request: Request,
    background_tasks: BackgroundTasks,
) -> JSONResponse:
    """POST endpoint receiving Messenger webhook events."""
    # Verify signature using the raw body (required for exact HMAC).
    await validate_signature(request)

    try:
        data: dict[str, Any] = await request.json()
    except Exception as exc:  # noqa: BLE001
        logger.warning("Invalid JSON body: %s", exc)
        raise HTTPException(status_code=400, detail="Invalid JSON body.")

    for entry in data.get("entry", []):
        for messaging in entry.get("messaging", []):
            sender_id = messaging.get("sender", {}).get("id")
            message = messaging.get("message", {})
            text = message.get("text", "")

            if sender_id and text:
                # Offload heavy AI work to the background so we reply 200 fast.
                background_tasks.add_task(_process_message, sender_id, text)

    # Always ack quickly; the payload is processed in the background.
    return JSONResponse(content={"status": "EVENT_RECEIVED"}, status_code=200)


@app.get("/health", include_in_schema=False)
async def health() -> dict[str, str]:
    return {"status": "ok", "service": "meta_gateway"}


@app.get("/v1/status", tags=["ops"])
async def status() -> dict[str, str]:
    return {"status": "ok"}
