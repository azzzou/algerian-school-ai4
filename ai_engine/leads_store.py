"""SQLite storage for AI-extracted leads (no PHP / no MySQL required).

Uses Python's built-in ``sqlite3`` module, so it works anywhere Python runs.
The DB file lives next to this module as ``leads.db``.

Schema columns match the Pydantic ``ExtractedInfo`` field names:
``student_name``, ``phone_number``, ``branch_or_level``, ``lead_score``.
"""

from __future__ import annotations

import sqlite3
import uuid
from datetime import datetime, timezone
from pathlib import Path

DB_PATH = Path(__file__).resolve().parent / "leads.db"

SCHEMA = """
CREATE TABLE IF NOT EXISTS leads (
    id               TEXT PRIMARY KEY,
    created_at       TEXT NOT NULL,
    source           TEXT NOT NULL DEFAULT 'simulation',
    conversation_id  TEXT,
    raw_message      TEXT,
    student_name     TEXT,
    phone_number     TEXT,
    branch_or_level  TEXT,
    lead_score       TEXT DEFAULT 'COLD',
    level            TEXT,
    filiere          TEXT,
    subject          TEXT,
    ai_reply         TEXT
);
"""


def _connect() -> sqlite3.Connection:
    conn = sqlite3.connect(str(DB_PATH))
    conn.row_factory = sqlite3.Row
    return conn


def init_db() -> None:
    with _connect() as conn:
        conn.executescript(SCHEMA)
        # Migrate old schema: add missing columns if they don't exist.
        existing = {row[1] for row in conn.execute("PRAGMA table_info(leads)").fetchall()}
        migrations = [
            ("ALTER TABLE leads ADD COLUMN student_name TEXT",    "student_name"),
            ("ALTER TABLE leads ADD COLUMN phone_number TEXT",    "phone_number"),
            ("ALTER TABLE leads ADD COLUMN branch_or_level TEXT", "branch_or_level"),
            ("ALTER TABLE leads ADD COLUMN lead_score TEXT DEFAULT 'COLD'", "lead_score"),
        ]
        for sql, col in migrations:
            if col not in existing:
                conn.execute(sql)
        # Back-fill new columns from old ones where possible.
        conn.execute("""
            UPDATE leads
            SET student_name    = COALESCE(student_name, name),
                phone_number    = COALESCE(phone_number, phone),
                branch_or_level = COALESCE(branch_or_level, level)
            WHERE student_name IS NULL OR phone_number IS NULL OR branch_or_level IS NULL
        """)
        # Fix enum string values like "LeadScore.HOT" -> "HOT"
        conn.execute("UPDATE leads SET lead_score = 'HOT'  WHERE lead_score LIKE '%HOT%'")
        conn.execute("UPDATE leads SET lead_score = 'WARM' WHERE lead_score LIKE '%WARM%'")
        conn.execute("UPDATE leads SET lead_score = 'COLD' WHERE lead_score LIKE '%COLD%' OR lead_score IS NULL")


def insert_lead(
    *,
    student_name: str | None = None,
    phone_number: str | None = None,
    branch_or_level: str | None = None,
    lead_score: str = "COLD",
    level: str | None = None,
    filiere: str | None = None,
    subject: str | None = None,
    raw_message: str | None = None,
    ai_reply: str | None = None,
    conversation_id: str | None = None,
    source: str = "simulation",
    # -- backward-compat aliases --
    name: str | None = None,
    phone: str | None = None,
) -> dict:
    """Insert a lead and return the row dict (ready to display)."""
    init_db()

    # Resolve backward-compat aliases.
    student_name = student_name or name
    phone_number = phone_number or phone

    lead_id = str(uuid.uuid4())[:8]
    created_at = datetime.now(timezone.utc).isoformat(timespec="seconds")
    row = {
        "id": lead_id,
        "created_at": created_at,
        "source": source,
        "conversation_id": conversation_id,
        "raw_message": raw_message,
        "student_name": student_name,
        "phone_number": phone_number,
        "branch_or_level": branch_or_level,
        "lead_score": lead_score,
        "level": level,
        "filiere": filiere,
        "subject": subject,
        "ai_reply": ai_reply,
    }
    with _connect() as conn:
        conn.execute(
            """
            INSERT INTO leads (
                id, created_at, source, conversation_id, raw_message,
                student_name, phone_number, branch_or_level, lead_score,
                level, filiere, subject, ai_reply
            ) VALUES (
                :id, :created_at, :source, :conversation_id, :raw_message,
                :student_name, :phone_number, :branch_or_level, :lead_score,
                :level, :filiere, :subject, :ai_reply
            )
            """,
            row,
        )
    return row


def fetch_all_leads() -> list[dict]:
    init_db()
    with _connect() as conn:
        rows = conn.execute(
            "SELECT * FROM leads ORDER BY created_at DESC"
        ).fetchall()
    return [dict(r) for r in rows]
