"""Message signature verification for Meta Messenger webhooks.

Meta signs every webhook delivery with an HMAC-SHA256 of the raw request body
using the app secret. We verify this signature to guarantee the request really
came from Meta and was not tampered with in transit.
"""

from __future__ import annotations

import hashlib
import hmac
import logging

from fastapi import HTTPException, Request

logger = logging.getLogger(__name__)

# Header namesMeta sends on webhook deliveries.
SIGNATURE_HEADER = "X-Hub-Signature-256"

# Algorithm prefix used by Meta: "sha256=<hex digest>"
_ALGO_PREFIX = "sha256="


def compute_signature(payload: bytes, app_secret: str) -> str:
    """Compute the HMAC-SHA256 signature for a raw payload."""
    digest = hmac.new(
        key=app_secret.encode("utf-8"),
        msg=payload,
        digestmod=hashlib.sha256,
    ).hexdigest()
    return f"{_ALGO_PREFIX}{digest}"


def verify_signature(payload: bytes, signature_header: str, app_secret: str) -> bool:
    """Compare the provided signature header against the computed one.

    Uses compare_digest to avoid timing attacks.
    """
    expected = compute_signature(payload, app_secret)

    if not signature_header:
        return False

    # Be tolerant of "sha256=..." or just the bare hex digest.
    provided = signature_header
    if provided.startswith(_ALGO_PREFIX):
        provided = provided[len(_ALGO_PREFIX):]

    provided_hex = expected.split("=", 1)[1]
    # Normalise: we compare the hex digests.
    return hmac.compare_digest(provided, expected.split("=", 1)[1])


async def validate_signature(request: Request) -> bytes:
    """Validate the X-Hub-Signature-256 header against the app secret.

    Extracts the raw body (needed for exact HMAC), verifies it, and returns
    the body bytes so the caller can parse them once.
    """
    from starlette.background import BackgroundTasks  # noqa: F401

    app_secret = getattr(request.app.state, "messenger_app_secret", None)
    if not app_secret:
        raise HTTPException(status_code=500, detail="App secret not configured.")

    body = await request.body()

    signature = request.headers.get(SIGNATURE_HEADER, "")
    if not verify_signature(body, signature, app_secret):
        logger.warning("Invalid X-Hub-Signature-256 for incoming message.")
        raise HTTPException(status_code=401, detail="Invalid signature.")

    return body
