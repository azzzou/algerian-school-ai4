#!/usr/bin/env python3
"""CLI script to process a message through the AI engine.

This script is called by Laravel's MessengerWebhookController to process
messages without requiring the FastAPI service to be running.

Usage:
    python process_message.py --message "Bonjour, je veux m'inscrire"
    python process_message.py --message "..." --conversation-id "12345" --json
"""

from __future__ import annotations

import argparse
import json
import os
import sys
from pathlib import Path

# Add the crew directory to path
CREW_DIR = Path(__file__).resolve().parent / "crews" / "algerian_support_crew"
sys.path.insert(0, str(CREW_DIR))

from dotenv import load_dotenv

load_dotenv(Path(__file__).resolve().parent / ".env")


def process_message(message: str, conversation_id: str = "cli") -> dict:
    """Process a message through the AI engine and return structured result."""
    try:
        import importlib.util

        _spec = importlib.util.spec_from_file_location(
            "crew_algerian_main", CREW_DIR / "main.py"
        )
        _crew = importlib.util.module_from_spec(_spec)
        sys.modules["crew_algerian_main"] = _crew
        _spec.loader.exec_module(_crew)

        crew = _crew.AlgerianSupportCrew(
            message=message,
            google_api_key=os.getenv("GOOGLE_API_KEY"),
            model=os.getenv("GEMINI_MODEL", "gemini-3.6-flash"),
        )
        result = crew.handle()

        return {
            "reply_text": result.reply_text,
            "extracted_info": (
                result.extracted_info.model_dump()
                if result.extracted_info
                else {}
            ),
        }

    except Exception as exc:
        return {
            "reply_text": (
                "Saha lik! Yallah daba 3andna 3akla takniya, jari ngssem9 "
                "l wajda. 3aytelna baad chwiya wala t3awed b messajek. "
                f"({exc.__class__.__name__})"
            ),
            "extracted_info": {},
            "error": str(exc),
        }


def main():
    parser = argparse.ArgumentParser(
        description="Process a message through the AI engine"
    )
    parser.add_argument(
        "--message", "-m",
        required=True,
        help="Message text to process",
    )
    parser.add_argument(
        "--conversation-id", "-c",
        default="cli",
        help="Conversation ID (optional)",
    )
    parser.add_argument(
        "--json", "-j",
        action="store_true",
        help="Output as JSON",
    )

    args = parser.parse_args()

    result = process_message(args.message, args.conversation_id)

    if args.json:
        print(json.dumps(result, ensure_ascii=False, indent=2))
    else:
        print("\n" + "=" * 60)
        print("AI Reply:")
        print("=" * 60)
        print(result["reply_text"])

        if result.get("extracted_info"):
            info = result["extracted_info"]
            print("\n" + "=" * 60)
            print("Extracted Info:")
            print("=" * 60)
            print(f"  Name: {info.get('student_name', 'N/A')}")
            print(f"  Phone: {info.get('phone_number', 'N/A')}")
            print(f"  Level: {info.get('level', 'N/A')}")
            print(f"  Stream: {info.get('filiere', 'N/A')}")
            print(f"  Lead Score: {info.get('lead_score', 'N/A')}")

        if result.get("error"):
            print(f"\nError: {result['error']}")


if __name__ == "__main__":
    main()
