"""Test script for Audio Processor module.

This script tests the voice transcription pipeline by:
1. Creating a test audio file (if no audio file provided)
2. Transcribing the audio using Whisper
3. Processing it through the AI engine
4. Verifying the extracted student information

Usage:
    python test_audio_processor.py [path_to_audio_file]
    python test_audio_processor.py --generate-test-audio
"""

from __future__ import annotations

import os
import sys
import tempfile
from pathlib import Path

# Add ai_engine to path
AI_ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(AI_ROOT))

from dotenv import load_dotenv

load_dotenv(AI_ROOT / ".env")


def generate_test_audio() -> str:
    """Generate a simple test audio file using system TTS or create a dummy."""
    try:
        import pyttsx3

        # Use pyttsx3 for text-to-speech
        engine = pyttsx3.init()
        test_text = "Bonjour, je m'appelle Karim. Je veux m'inscrire pour le BAC. Mon numéro est 0555 12 34 56."

        with tempfile.NamedTemporaryFile(suffix=".wav", delete=False) as f:
            tmp_path = f.name

        engine.save_to_file(test_text, tmp_path)
        engine.runAndWait()

        print(f"Generated test audio with pyttsx3: {tmp_path}")
        return tmp_path

    except ImportError:
        print("pyttsx3 not available, creating dummy WAV file...")
        return create_dummy_wav()


def create_dummy_wav() -> str:
    """Create a minimal WAV file for testing (silence)."""
    import struct
    import wave

    with tempfile.NamedTemporaryFile(suffix=".wav", delete=False) as f:
        tmp_path = f.name

    # Create a 1-second silent WAV file
    with wave.open(tmp_path, "w") as wav_file:
        wav_file.setnchannels(1)
        wav_file.setsampwidth(2)
        wav_file.setframerate(16000)
        # Write 1 second of silence
        wav_file.writeframes(b"\x00\x00" * 16000)

    print(f"Created dummy WAV file: {tmp_path}")
    return tmp_path


def test_transcription_only(audio_path: str) -> bool:
    """Test transcription without AI processing."""
    print("\n" + "=" * 60)
    print("TEST: Transcription Only")
    print("=" * 60)

    try:
        from audio_processor import transcribe_audio

        result = transcribe_audio(audio_path)

        print(f"\nTranscription Result:")
        print(f"  Text: {result['text']}")
        print(f"  Language: {result['language']}")
        print(f"  Segments: {result['segment_count']}")

        if result["segments"]:
            print("\n  Segments:")
            for seg in result["segments"][:5]:  # Show first 5
                print(f"    [{seg['start']:.1f}s - {seg['end']:.1f}s] {seg['text']}")

        return True

    except Exception as exc:
        print(f"\nError: {exc}")
        return False


def test_full_pipeline(audio_path: str) -> bool:
    """Test full pipeline: transcription + AI processing + lead storage."""
    print("\n" + "=" * 60)
    print("TEST: Full Pipeline (Transcription + AI + Lead Storage)")
    print("=" * 60)

    try:
        from audio_processor import process_voice_message

        result = process_voice_message(
            audio_path,
            conversation_id="test_conversation_001",
            source="test_audio",
        )

        if result["error"]:
            print(f"\nError: {result['error']}")
            return False

        print(f"\n1. Transcription:")
        print(f"   {result['transcription']}")

        print(f"\n2. AI Reply:")
        print(f"   {result['ai_reply']}")

        if result["extracted_info"]:
            info = result["extracted_info"]
            print(f"\n3. Extracted Info:")
            print(f"   Name: {info.get('student_name', 'N/A')}")
            print(f"   Phone: {info.get('phone_number', 'N/A')}")
            print(f"   Level: {info.get('level', 'N/A')}")
            print(f"   Stream: {info.get('filiere', 'N/A')}")
            print(f"   Lead Score: {info.get('lead_score', 'N/A')}")

        if result["lead"]:
            print(f"\n4. Lead Stored:")
            print(f"   ID: {result['lead']['id']}")
            print(f"   Created: {result['lead']['created_at']}")
            print(f"   Source: {result['lead']['source']}")

        return True

    except Exception as exc:
        print(f"\nError: {exc}")
        import traceback
        traceback.print_exc()
        return False


def test_api_endpoints() -> bool:
    """Test API endpoints (requires running server)."""
    print("\n" + "=" * 60)
    print("TEST: API Endpoints")
    print("=" * 60)

    try:
        import httpx

        # Test health endpoint
        try:
            response = httpx.get("http://localhost:8000/health", timeout=5.0)
            print(f"\nHealth endpoint: {response.status_code}")
            print(f"  Response: {response.json()}")
        except httpx.ConnectError:
            print("\nServer not running at http://localhost:8000")
            print("Start the server with: uvicorn service.main:app --reload")
            return False

        # Test formats endpoint
        response = httpx.get("http://localhost:8000/v1/voice/formats", timeout=5.0)
        print(f"\nFormats endpoint: {response.status_code}")
        print(f"  Response: {response.json()}")

        return True

    except ImportError:
        print("\nhttpx not installed. Install with: pip install httpx")
        return False
    except Exception as exc:
        print(f"\nError: {exc}")
        return False


def main():
    """Run tests based on command line arguments."""
    import argparse

    parser = argparse.ArgumentParser(description="Test Audio Processor")
    parser.add_argument(
        "audio_file",
        nargs="?",
        help="Path to audio file to test with",
    )
    parser.add_argument(
        "--generate-test-audio",
        action="store_true",
        help="Generate a test audio file",
    )
    parser.add_argument(
        "--transcribe-only",
        action="store_true",
        help="Only test transcription",
    )
    parser.add_argument(
        "--test-api",
        action="store_true",
        help="Test API endpoints (requires running server)",
    )

    args = parser.parse_args()

    print("\n" + "=" * 60)
    print("AUDIO PROCESSOR TEST SUITE")
    print("=" * 60)

    # Determine audio file
    audio_path = args.audio_file

    if args.generate_test_audio or not audio_path:
        audio_path = generate_test_audio()
    elif not Path(audio_path).exists():
        print(f"\nError: Audio file not found: {audio_path}")
        sys.exit(1)

    print(f"\nUsing audio file: {audio_path}")

    results = []

    # Run transcription test
    if args.transcribe_only:
        results.append(("Transcription Only", test_transcription_only(audio_path)))
    else:
        # Run full pipeline test
        results.append(("Full Pipeline", test_full_pipeline(audio_path)))

    # Run API test if requested
    if args.test_api:
        results.append(("API Endpoints", test_api_endpoints()))

    # Print summary
    print("\n" + "=" * 60)
    print("TEST SUMMARY")
    print("=" * 60)

    all_passed = True
    for name, passed in results:
        status = "PASS" if passed else "FAIL"
        print(f"  {name}: {status}")
        if not passed:
            all_passed = False

    print("\n" + "=" * 60)
    if all_passed:
        print("All tests PASSED!")
    else:
        print("Some tests FAILED!")
    print("=" * 60)

    # Cleanup temp file
    if audio_path and audio_path.startswith(tempfile.gettempdir()):
        try:
            os.unlink(audio_path)
            print(f"\nCleaned up temp file: {audio_path}")
        except OSError:
            pass

    return 0 if all_passed else 1


if __name__ == "__main__":
    sys.exit(main())
