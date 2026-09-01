"""FastAPI HTTP service wrapping the Algerian Support crew.

Exposes POST /v1/reply which the Messenger gateway calls asynchronously.
Returns the deterministic AgentReply JSON produced by the CrewAI agent.
"""

from __future__ import annotations

import logging
import os
import sys
from pathlib import Path
from typing import Optional

from fastapi import FastAPI, File, HTTPException, UploadFile
from fastapi.responses import JSONResponse
from pydantic import BaseModel, Field

from dotenv import load_dotenv  # noqa: E402

# Make the crew package importable regardless of CWD.
CREW_DIR = Path(__file__).resolve().parents[1] / "crews" / "algerian_support_crew"
if str(CREW_DIR) not in sys.path:
    sys.path.insert(0, str(CREW_DIR))

_AI_ROOT = Path(__file__).resolve().parents[1]
load_dotenv(_AI_ROOT / ".env")

# Import the crew's main.py directly from its absolute path using a unique
# module name. This avoids the name clash with this file (both named ``main``)
# and the crew package's relative imports (agents/tasks/models).
import importlib.util  # noqa: E402

_CREW_MAIN = CREW_DIR / "main.py"
_spec = importlib.util.spec_from_file_location("crew_algerian_main", _CREW_MAIN)
_crew = importlib.util.module_from_spec(_spec)
sys.modules["crew_algerian_main"] = _crew
_spec.loader.exec_module(_crew)
AlgerianSupportCrew = _crew.AlgerianSupportCrew

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(
    title="Algerian School Support AI Engine",
    description="CrewAI (Gemini) agent exposing a structured reply endpoint.",
    version="1.0.0",
)


class ReplyRequest(BaseModel):
    message: str = Field(..., min_length=1, max_length=4000)
    conversation_id: str | None = None


class ReplyResponse(BaseModel):
    reply_text: str
    extracted_info: dict | None = None


@app.post("/v1/reply", response_model=ReplyResponse)
async def reply(req: ReplyRequest) -> ReplyResponse:
    model = os.getenv("GEMINI_MODEL", "gemini-3.6-flash")
    try:
        crew = AlgerianSupportCrew(
            message=req.message,
            google_api_key=os.getenv("GOOGLE_API_KEY"),
            model=model,
        )
        result = crew.handle()
    except Exception as exc:  # noqa: BLE001
        logger.exception("Crew execution failed")
        raise HTTPException(status_code=502, detail=f"AI engine error: {exc}")

    return ReplyResponse(
        reply_text=result.reply_text,
        extracted_info=(
            result.extracted_info.model_dump() if result.extracted_info else None
        ),
    )


@app.get("/health")
async def health() -> dict[str, str]:
    return {"status": "ok", "service": "ai_engine"}


# ---------------------------------------------------------------------------
# Voice Message Processing
# ---------------------------------------------------------------------------

class VoiceProcessRequest(BaseModel):
    """Request model for processing voice messages from webhook."""
    audio_url: Optional[str] = Field(
        None,
        description="URL to download audio file (for webhook integration)",
    )
    conversation_id: Optional[str] = Field(
        None,
        description="Conversation tracking ID",
    )
    language: Optional[str] = Field(
        None,
        description="Language hint for transcription (e.g., 'ar', 'fr')",
    )
    source: str = Field(
        default="whatsapp_voice",
        description="Source platform (whatsapp_voice, facebook_voice, etc.)",
    )


class VoiceProcessResponse(BaseModel):
    """Response model for voice message processing."""
    success: bool
    transcription: Optional[str] = None
    ai_reply: Optional[str] = None
    extracted_info: Optional[dict] = None
    lead_id: Optional[str] = None
    error: Optional[str] = None


@app.post("/v1/voice/process", response_model=VoiceProcessResponse)
async def process_voice_webhook(req: VoiceProcessRequest) -> VoiceProcessResponse:
    """Process voice message from webhook (URL-based).

    This endpoint is called by the Messenger gateway when a voice message
    is received. It downloads the audio from the provided URL, transcribes
    it, and processes it through the AI engine.
    """
    if not req.audio_url:
        raise HTTPException(
            status_code=400,
            detail="audio_url is required for webhook-based processing"
        )

    try:
        import httpx

        # Download audio from URL
        async with httpx.AsyncClient() as client:
            response = await client.get(req.audio_url, timeout=30.0)
            response.raise_for_status()

        # Import audio processor
        audio_processor_path = Path(__file__).resolve().parents[1] / "audio_processor.py"
        import importlib.util
        spec = importlib.util.spec_from_file_location("audio_processor", audio_processor_path)
        audio_proc = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(audio_proc)

        # Process the audio bytes
        result = audio_proc.process_voice_bytes(
            audio_bytes=response.content,
            filename=req.audio_url.split("/")[-1] or "voice.ogg",
            conversation_id=req.conversation_id,
            language=req.language,
            source=req.source,
        )

        return VoiceProcessResponse(
            success=result["error"] is None,
            transcription=result["transcription"],
            ai_reply=result["ai_reply"],
            extracted_info=result["extracted_info"],
            lead_id=result["lead"]["id"] if result.get("lead") else None,
            error=result["error"],
        )

    except httpx.HTTPError as exc:
        logger.exception("Failed to download audio from URL")
        raise HTTPException(
            status_code=502,
            detail=f"Failed to download audio: {exc}"
        )
    except Exception as exc:
        logger.exception("Voice processing failed")
        raise HTTPException(
            status_code=500,
            detail=f"Voice processing error: {exc}"
        )


@app.post("/v1/voice/upload", response_model=VoiceProcessResponse)
async def process_voice_upload(
    file: UploadFile = File(...),
    conversation_id: Optional[str] = None,
    language: Optional[str] = None,
    source: str = "whatsapp_voice",
) -> VoiceProcessResponse:
    """Process voice message via file upload.

    This endpoint allows direct upload of audio files for testing
    or manual processing. Accepts .ogg, .mp3, .wav, .m4a, .webm, .opus
    """
    # Validate file type
    supported_formats = {".ogg", ".mp3", ".wav", ".m4a", ".webm", ".opus", ".flac", ".aac"}
    file_ext = Path(file.filename or "").suffix.lower()

    if file_ext not in supported_formats:
        raise HTTPException(
            status_code=400,
            detail=f"Unsupported audio format: {file_ext}. "
                   f"Supported: {', '.join(sorted(supported_formats))}"
        )

    try:
        # Read file content
        audio_bytes = await file.read()

        if len(audio_bytes) == 0:
            raise HTTPException(status_code=400, detail="Empty audio file")

        if len(audio_bytes) > 50 * 1024 * 1024:  # 50MB limit
            raise HTTPException(
                status_code=400,
                detail="Audio file too large (max 50MB)"
            )

        # Import audio processor
        audio_processor_path = Path(__file__).resolve().parents[1] / "audio_processor.py"
        import importlib.util
        spec = importlib.util.spec_from_file_location("audio_processor", audio_processor_path)
        audio_proc = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(audio_proc)

        # Process the audio
        result = audio_proc.process_voice_bytes(
            audio_bytes=audio_bytes,
            filename=file.filename or "voice.ogg",
            conversation_id=conversation_id,
            language=language,
            source=source,
        )

        return VoiceProcessResponse(
            success=result["error"] is None,
            transcription=result["transcription"],
            ai_reply=result["ai_reply"],
            extracted_info=result["extracted_info"],
            lead_id=result["lead"]["id"] if result.get("lead") else None,
            error=result["error"],
        )

    except HTTPException:
        raise
    except Exception as exc:
        logger.exception("Voice upload processing failed")
        raise HTTPException(
            status_code=500,
            detail=f"Voice processing error: {exc}"
        )


@app.get("/v1/voice/formats")
async def get_supported_formats() -> dict[str, list[str]]:
    """Return list of supported audio file formats."""
    return {
        "formats": [".ogg", ".mp3", ".wav", ".m4a", ".webm", ".opus", ".flac", ".aac"],
        "whisper_model": os.getenv("WHISPER_MODEL", "tiny"),
        "max_file_size_mb": 50,
    }
