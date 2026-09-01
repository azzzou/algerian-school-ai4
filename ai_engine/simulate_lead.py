"""End-to-end simulation: student message -> Gemini Structured Output -> SQLite lead.

Run directly with the project venv python:
    python simulate_lead.py [--message "..."]
"""

from __future__ import annotations

import argparse
import io
import os
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

# Ensure UTF-8 output on Windows
if hasattr(sys.stdout, "buffer"):
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding="utf-8")


def main() -> None:
    parser = argparse.ArgumentParser(description="Simulate an incoming student lead.")
    parser.add_argument(
        "--message",
        default=(
            "Saha ana yassine, 0770123456, BEM sciences, bghit nchrik. "
            "Chhal thaman douroos el math?"
        ),
    )
    args = parser.parse_args()

    from leads_store import insert_lead, fetch_all_leads

    # 1) Route the message through the Gemini structured output agent.
    import importlib.util as _ilu

    _crew_main = Path(__file__).resolve().parent / "crews" / "algerian_support_crew" / "main.py"
    sys.path.insert(0, str(_crew_main.parent))
    _spec = _ilu.spec_from_file_location("crew_algerian_main", _crew_main)
    _crew_mod = _ilu.module_from_spec(_spec)
    sys.modules["crew_algerian_main"] = _crew_mod
    _spec.loader.exec_module(_crew_mod)
    AlgerianSupportCrew = _crew_mod.AlgerianSupportCrew

    crew = AlgerianSupportCrew(
        message=args.message,
        google_api_key=os.getenv("GOOGLE_API_KEY"),
        model=os.getenv("GEMINI_MODEL", "gemini-3.6-flash"),
        verbose=False,
    )
    result = crew.handle()
    info = result.extracted_info

    print("\n=== AI REPLY (darija) ===")
    print(result.reply_text)
    print("\n=== AI EXTRACTED INFO ===")
    info_dict = info.model_dump() if info else {}
    for k, v in info_dict.items():
        print(f"  {k}: {v}")

    # 2) Store the lead in SQLite.
    data = info.model_dump() if info and hasattr(info, "model_dump") else {}
    insert_lead(
        student_name=data.get("student_name"),
        phone_number=data.get("phone_number"),
        branch_or_level=data.get("branch_or_level"),
        lead_score=data.get("lead_score", "COLD").value if hasattr(data.get("lead_score", "COLD"), "value") else str(data.get("lead_score", "COLD")),
        level=data.get("level").value if data.get("level") and hasattr(data.get("level"), "value") else None,
        filiere=data.get("filiere").value if data.get("filiere") and hasattr(data.get("filiere"), "value") else None,
        subject=data.get("subject"),
        raw_message=args.message,
        ai_reply=result.reply_text,
        conversation_id="sim-001",
        source="simulation",
    )

    print("\n=== LEAD SAVED. Now dumping table ===")
    for row in fetch_all_leads():
        print(_fmt_row(row))


def _fmt_row(r: dict) -> str:
    return (
        f"[{r['id']}] {r['created_at']} | "
        f"score={r.get('lead_score','?')} | "
        f"name={r.get('student_name','-')} | "
        f"phone={r.get('phone_number','-')} | "
        f"branch={r.get('branch_or_level','-')}"
    )


if __name__ == "__main__":
    main()
