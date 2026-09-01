"""Voice Transcription Module for Algerian School AI Engine.

Handles audio messages from WhatsApp/Facebook, transcribes them using
OpenAI Whisper, and processes them through the AI engine to extract
student information and generate responses.

Supported formats: .ogg, .mp3, .wav, .m4a, .webm, .opus
"""

from __future__ import annotations

import logging
import os
import sys
import tempfile
from pathlib import Path
from typing import Optional

logger = logging.getLogger(__name__)

# Make the crew package importable.
CREW_DIR = Path(__file__).resolve().parent / "crews" / "algerian_support_crew"
if str(CREW_DIR) not in sys.path:
    sys.path.insert(0, str(CREW_DIR))

from dotenv import load_dotenv

_AI_ROOT = Path(__file__).resolve().parent
load_dotenv(_AI_ROOT / ".env")

# Lazy imports for heavy dependencies (whisper, torch)
_whisper_model = None
_whisper_module = None


def _ensure_whisper():
    """Lazy-load whisper module and model to avoid slow startup."""
    global _whisper_module, _whisper_model

    if _whisper_module is None:
        try:
            import whisper as whisper_mod
            _whisper_module = whisper_mod
        except ImportError:
            raise ImportError(
                "openai-whisper is not installed. "
                "Install it with: pip install openai-whisper torch"
            )

    if _whisper_model is None:
        model_size = os.getenv("WHISPER_MODEL", "tiny")
        logger.info("Loading Whisper model: %s", model_size)
        _whisper_model = _whisper_module.load_model(model_size)
        logger.info("Whisper model loaded successfully")

    return _whisper_module, _whisper_model


def transcribe_audio(
    audio_path: str | Path,
    language: str | None = None,
) -> dict:
    """Transcribe an audio file using OpenAI Whisper.

    Args:
        audio_path: Path to the audio file (.ogg, .mp3, .wav, etc.)
        language: Optional language code (e.g., 'ar', 'fr'). Auto-detect if None.

    Returns:
        dict with keys: text, language, duration, segments
    """
    whisper_mod, model = _ensure_whisper()

    audio_path = Path(audio_path)
    if not audio_path.exists():
        raise FileNotFoundError(f"Audio file not found: {audio_path}")

    logger.info("Transcribing audio: %s", audio_path.name)

    # Transcribe with optional language hint
    options = {}
    if language:
        options["language"] = language

    result = model.transcribe(str(audio_path), **options)

    # Extract segments info
    segments = []
    for seg in result.get("segments", []):
        segments.append({
            "start": seg["start"],
            "end": seg["end"],
            "text": seg["text"].strip(),
        })

    return {
        "text": result["text"].strip(),
        "language": result.get("language", "unknown"),
        "segments": segments,
        "segment_count": len(segments),
    }


def process_voice_message(
    audio_path: str | Path,
    conversation_id: str | None = None,
    language: str | None = None,
    source: str = "whatsapp_voice",
) -> dict:
    """Full pipeline: transcribe audio -> process with AI -> store lead.

    This is the main entry point for processing voice messages from
    WhatsApp/Facebook. It:
    1. Transcribes the audio to text using Whisper
    2. Sends the transcribed text to the AI engine (Gemini)
    3. Extracts student information (name, phone, level, etc.)
    4. Stores the lead in the database
    5. Returns the complete result

    Args:
        audio_path: Path to the audio file
        conversation_id: Optional conversation tracking ID
        language: Optional language code for transcription
        source: Source identifier (e.g., 'whatsapp_voice', 'facebook_voice')

    Returns:
        dict with keys:
            - transcription: raw transcribed text
            - ai_reply: AI-generated response
            - extracted_info: student information (name, phone, score, etc.)
            - lead: stored lead data (if applicable)
            - error: error message (if any)
    """
    result = {
        "transcription": None,
        "ai_reply": None,
        "extracted_info": None,
        "lead": None,
        "error": None,
    }

    try:
        # Step 1: Transcribe audio
        logger.info("Step 1: Transcribing audio file")
        transcription = transcribe_audio(audio_path, language=language)
        result["transcription"] = transcription["text"]

        if not transcription["text"].strip():
            result["error"] = "Audio transcription was empty"
            return result

        logger.info("Transcribed text: %s", transcription["text"][:100])

        # Step 2: Process with AI engine
        logger.info("Step 2: Processing with AI engine")
        import importlib.util

        _spec = importlib.util.spec_from_file_location(
            "crew_algerian_main", CREW_DIR / "main.py"
        )
        _crew = importlib.util.module_from_spec(_spec)
        sys.modules["crew_algerian_main"] = _crew
        _spec.loader.exec_module(_crew)

        crew = _crew.AlgerianSupportCrew(
            message=transcription["text"],
            google_api_key=os.getenv("GOOGLE_API_KEY"),
            model=os.getenv("GEMINI_MODEL", "gemini-3.6-flash"),
        )
        ai_result = crew.handle()

        result["ai_reply"] = ai_result.reply_text
        result["extracted_info"] = (
            ai_result.extracted_info.model_dump()
            if ai_result.extracted_info
            else None
        )

        # Step 3: Store lead in database
        logger.info("Step 3: Storing lead in database")
        import leads_store

        extracted = result["extracted_info"] or {}
        lead = leads_store.insert_lead(
            student_name=extracted.get("student_name"),
            phone_number=extracted.get("phone_number"),
            branch_or_level=extracted.get("branch_or_level"),
            lead_score=extracted.get("lead_score", "COLD"),
            level=extracted.get("level"),
            filiere=extracted.get("filiere"),
            subject=extracted.get("subject"),
            raw_message=transcription["text"],
            ai_reply=result["ai_reply"],
            conversation_id=conversation_id,
            source=source,
        )
        result["lead"] = lead

        logger.info("Voice message processed successfully: lead_id=%s", lead["id"])

    except Exception as exc:
        logger.exception("Failed to process voice message")
        result["error"] = f"{exc.__class__.__name__}: {str(exc)}"

    return result


def process_voice_bytes(
    audio_bytes: bytes,
    filename: str = "voice_message.ogg",
    conversation_id: str | None = None,
    language: str | None = None,
    source: str = "whatsapp_voice",
) -> dict:
    """Process voice message from raw bytes (e.g., from webhook).

    Args:
        audio_bytes: Raw audio file bytes
        filename: Original filename (used for format detection)
        conversation_id: Optional conversation tracking ID
        language: Optional language code for transcription
        source: Source identifier

    Returns:
        Same as process_voice_message
    """
    with tempfile.NamedTemporaryFile(
        suffix=Path(filename).suffix or ".ogg",
        delete=False,
    ) as tmp:
        tmp.write(audio_bytes)
        tmp_path = tmp.name

    try:
        return process_voice_message(
            tmp_path,
            conversation_id=conversation_id,
            language=language,
            source=source,
        )
    finally:
        # Clean up temp file
        try:
            os.unlink(tmp_path)
        except OSError:
            pass


def get_supported_formats() -> list[str]:
    """Return list of supported audio file extensions."""
    return [".ogg", ".mp3", ".wav", ".m4a", ".webm", ".opus", ".flac", ".aac"]


# ======================================================================
# CLI test interface
# ======================================================================
if __name__ == "__main__":
    import argparse

    parser = argparse.ArgumentParser(
        description="Test voice transcription and AI processing"
    )
    parser.add_argument(
        "audio_file",
        nargs="?",
        help="Path to audio file to process",
    )
    parser.add_argument(
        "--transcribe-only",
        action="store_true",
        help="Only transcribe, don't process with AI",
    )
    parser.add_argument(
        "--language",
        type=str,
        default=None,
        help="Language hint (e.g., 'ar', 'fr')",
    )
    parser.add_argument(
        "--model",
        type=str,
        default="tiny",
        help="Whisper model size (tiny, base, small, medium, large)",
    )
    parser.add_argument(
        "--list-formats",
        action="store_true",
        help="List supported audio formats",
    )

    args = parser.parse_args()

    if args.list_formats:
        print("Supported audio formats:")
        for fmt in get_supported_formats():
            print(f"  - {fmt}")
        sys.exit(0)

    if not args.audio_file:
        parser.print_help()
        sys.exit(1)

    # Set model if specified
    os.environ["WHISPER_MODEL"] = args.model

    print(f"\n{'='*60}")
    print(f"Audio Processor Test")
    print(f"{'='*60}")
    print(f"File: {args.audio_file}")
    print(f"Model: {args.model}")
    print(f"Language: {args.language or 'auto-detect'}")
    print(f"{'='*60}\n")

    if args.transcribe_only:
        print("Transcribing audio...")
        result = transcribe_audio(args.audio_file, language=args.language)
        print(f"\nTranscription ({result['language']}):")
        print(f"  {result['text']}")
        print(f"\nSegments: {result['segment_count']}")
        for seg in result["segments"]:
            print(f"  [{seg['start']:.1f}s - {seg['end']:.1f}s] {seg['text']}")
    else:
        print("Processing voice message (transcribe + AI)...")
        result = process_voice_message(
            args.audio_file,
            language=args.language,
        )

        print(f"\n{'='*60}")
        print("RESULTS")
        print(f"{'='*60}")

        if result["error"]:
            print(f"\nError: {result['error']}")
        else:
            print(f"\nTranscription:")
            print(f"  {result['transcription']}")

            print(f"\nAI Reply:")
            print(f"  {result['ai_reply']}")

            if result["extracted_info"]:
                info = result["extracted_info"]
                print(f"\nExtracted Info:")
                print(f"  Name: {info.get('student_name', 'N/A')}")
                print(f"  Phone: {info.get('phone_number', 'N/A')}")
                print(f"  Level: {info.get('level', 'N/A')}")
                print(f"  Stream: {info.get('filiere', 'N/A')}")
                print(f"  Lead Score: {info.get('lead_score', 'N/A')}")

            if result["lead"]:
                print(f"\nLead Stored: ID={result['lead']['id']}")
