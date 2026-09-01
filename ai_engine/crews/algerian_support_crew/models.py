"""Structured output models for the Algerian school support agent.

These Pydantic models are enforced at the Gemini API level via
``langchain-google-genai`` structured output, guaranteeing a deterministic,
machine-readable response regardless of the underlying LLM temperature.

Extracted fields
----------------
- **student_name** — full name of the student (or parent).
- **phone_number** — Algerian phone in ``+213 XXX XX XX XX`` format.
- **branch_or_level** — combined level + stream, e.g. ``"BAC 3AS scientifique"``.
- **lead_score** — engagement signal: ``HOT``, ``WARM`` or ``COLD``.
"""

from __future__ import annotations

import re
from enum import Enum
from typing import Optional

from pydantic import BaseModel, Field, field_validator, model_validator


# ---------------------------------------------------------------------------
# Enums for strict categorical fields
# ---------------------------------------------------------------------------

class LeadScore(str, Enum):
    """Engagement temperature of the lead.

    - **HOT**  — student explicitly asks about pricing, registration, or
      provides contact details.  Ready to convert.
    - **WARM** — student asks about the school, schedule, subjects, but has
      not yet committed or provided contact info.
    - **COLD** — greeting, vague question, or off-topic message.  Needs
      nurturing.
    """
    HOT = "HOT"
    WARM = "WARM"
    COLD = "COLD"


class ExamLevel(str, Enum):
    """Allowed exam levels."""
    BEM = "BEM"
    BAC_1AS = "BAC 1AS"
    BAC_2AS = "BAC 2AS"
    BAC_3AS = "BAC 3AS"
    BAC = "BAC"


class Stream(str, Enum):
    """Allowed academic streams / filieres."""
    SCIENTIFIQUE = "scientifique"
    LITTERAIRE = "litteraire"
    MATH = "math"
    TECHNOLOGIQUE = "technologique"
    SPORT = "sport"
    LANGUES = "langues"


# ---------------------------------------------------------------------------
# Helper: map free-text to enums
# ---------------------------------------------------------------------------

_LEVEL_MAP = {
    "BEM": ExamLevel.BEM,
    "BAC": ExamLevel.BAC,
    "BAC 1AS": ExamLevel.BAC_1AS,
    "BAC1AS": ExamLevel.BAC_1AS,
    "1AS": ExamLevel.BAC_1AS,
    "BAC 2AS": ExamLevel.BAC_2AS,
    "BAC2AS": ExamLevel.BAC_2AS,
    "2AS": ExamLevel.BAC_2AS,
    "BAC 3AS": ExamLevel.BAC_3AS,
    "BAC3AS": ExamLevel.BAC_3AS,
    "3AS": ExamLevel.BAC_3AS,
}

_STREAM_MAP = {
    "scientifique": Stream.SCIENTIFIQUE,
    "science": Stream.SCIENTIFIQUE,
    "sciences": Stream.SCIENTIFIQUE,
    "litteraire": Stream.LITTERAIRE,
    "lettres": Stream.LITTERAIRE,
    "lettre": Stream.LITTERAIRE,
    "math": Stream.MATH,
    "mathematiques": Stream.MATH,
    "technologique": Stream.TECHNOLOGIQUE,
    "techno": Stream.TECHNOLOGIQUE,
    "sport": Stream.SPORT,
    "eps": Stream.SPORT,
    "langues": Stream.LANGUES,
    "langue": Stream.LANGUES,
}

_SCORE_MAP = {
    "hot": LeadScore.HOT,
    "warm": LeadScore.WARM,
    "cold": LeadScore.COLD,
    "chaud": LeadScore.HOT,
    "tiide": LeadScore.WARM,
    "barid": LeadScore.COLD,
}


# ---------------------------------------------------------------------------
# Core models
# ---------------------------------------------------------------------------

class ExtractedInfo(BaseModel):
    """Normalized pre-registration data extracted from the student's message.

    Every field is optional (``null`` = not mentioned).  A ``model_validator``
    runs *before* individual fields to coerce free-text strings coming from
    the LLM into the correct enum values.

    Field naming follows the downstream dashboard / CRM convention:
    ``student_name``, ``phone_number``, ``branch_or_level``, ``lead_score``.
    """
    model_config = {"extra": "forbid"}

    student_name: Optional[str] = Field(
        default=None,
        description=(
            "Full name of the student or parent. "
            "Only include if explicitly mentioned. Must be at least 2 chars."
        ),
    )
    phone_number: Optional[str] = Field(
        default=None,
        description=(
            "Algerian phone number. MUST be converted to E.164 format: "
            "+213 followed by 9 digits (e.g. +213 555 12 34 56). "
            "Only include if explicitly mentioned."
        ),
    )
    branch_or_level: Optional[str] = Field(
        default=None,
        description=(
            "Combined exam level and stream, e.g. 'BAC 3AS scientifique', "
            "'BEM litteraire'.  Built from level + filiere when available. "
            "If only level is known, return just the level (e.g. 'BAC')."
        ),
    )
    lead_score: LeadScore = Field(
        default=LeadScore.COLD,
        description=(
            "Engagement temperature of this lead. "
            "HOT = asks about pricing/registration or provides contact info. "
            "WARM = asks about schedule, subjects, or school details. "
            "COLD = greeting, vague, or off-topic."
        ),
    )

    # -- Internal fields kept for backward compat / fine-grained storage ----

    level: Optional[ExamLevel] = Field(
        default=None,
        description="Exam level: BEM, BAC 1AS, BAC 2AS, BAC 3AS, BAC.",
    )
    filiere: Optional[Stream] = Field(
        default=None,
        description="Stream: scientifique, litteraire, math, etc.",
    )
    subject: Optional[str] = Field(
        default=None,
        description="Subject of interest if mentioned (math, physics, ...).",
    )

    # -- Pre-validation: coerce strings to enums before field validation ----

    @model_validator(mode="before")
    @classmethod
    def _coerce_enums(cls, values):
        """Map free-text strings to proper enum values."""
        if not isinstance(values, dict):
            return values

        # Level coercion
        raw_level = values.get("level")
        if isinstance(raw_level, str) and raw_level not in {e.value for e in ExamLevel}:
            values["level"] = _LEVEL_MAP.get(raw_level.strip().upper())

        # Filiere coercion
        raw_filiere = values.get("filiere")
        if isinstance(raw_filiere, str) and raw_filiere not in {e.value for e in Stream}:
            values["filiere"] = _STREAM_MAP.get(raw_filiere.strip().lower())

        # Lead score coercion
        raw_score = values.get("lead_score")
        if isinstance(raw_score, str) and raw_score not in {e.value for e in LeadScore}:
            values["lead_score"] = _SCORE_MAP.get(raw_score.strip().lower())

        return values

    # -- Post-validation: compute branch_or_level --------------------------

    @model_validator(mode="after")
    def _build_branch_or_level(self):
        """Auto-fill ``branch_or_level`` from ``level`` + ``filiere``."""
        if not self.branch_or_level:
            parts = []
            if self.level:
                parts.append(self.level.value if isinstance(self.level, ExamLevel) else str(self.level))
            if self.filiere:
                parts.append(self.filiere.value if isinstance(self.filiere, Stream) else str(self.filiere))
            if parts:
                self.branch_or_level = " ".join(parts)
        return self

    # -- Field validators ---------------------------------------------------

    @field_validator("student_name")
    @classmethod
    def _clean_name(cls, v: Optional[str]) -> Optional[str]:
        if v is None:
            return None
        v = v.strip()
        if len(v) < 2:
            return None
        _reject = {
            "name", "phone", "level", "filiere", "subject",
            "student_name", "phone_number", "branch_or_level", "lead_score",
            "null", "none", "unknown", "n/a", "na",
        }
        if v.lower() in _reject:
            return None
        _bad_prefixes = ("bac", "bem", "math", "physic", "science")
        if v.lower().startswith(_bad_prefixes):
            return None
        return v

    @field_validator("phone_number")
    @classmethod
    def _clean_phone(cls, v: Optional[str]) -> Optional[str]:
        if v is None:
            return None
        digits = re.sub(r"[^\d]", "", v)
        if v.startswith("+"):
            digits = "+" + digits
        if digits.startswith("00213"):
            digits = "+213" + digits[5:]
        elif digits.startswith("0") and len(digits) == 10:
            digits = "+213" + digits[1:]
        elif not digits.startswith("+213") and len(digits) == 9:
            digits = "+213" + digits
        if not re.match(r"^\+213\d{9}$", digits):
            return None
        d = digits[4:]
        return f"+213 {d[:3]} {d[3:5]} {d[5:7]} {d[7:9]}"

    @field_validator("subject")
    @classmethod
    def _clean_subject(cls, v: Optional[str]) -> Optional[str]:
        if v is None:
            return None
        v = v.strip()
        if len(v) < 2:
            return None
        if v.lower() in {"null", "none", "unknown", "n/a"}:
            return None
        return v

    # -- Properties ---------------------------------------------------------

    @property
    def is_complete(self) -> bool:
        """True when at least the core registration fields are present."""
        return bool(self.student_name and self.phone_number)

    def has_any_data(self) -> bool:
        """True when at least one field was extracted."""
        return any([
            self.student_name, self.phone_number,
            self.level, self.filiere, self.subject,
            self.branch_or_level,
        ])


class AgentReply(BaseModel):
    """Deterministic structured reply produced by the support agent.

    This model is used as the ``response_schema`` for Gemini structured
    output, meaning the API will ALWAYS return a JSON matching this schema.
    """
    model_config = {"extra": "forbid"}

    reply_text: str = Field(
        description=(
            "The warm, professional reply to the student, written in Algerian "
            "Darija (or French if the student wrote in French). "
            "Must be at least 20 characters."
        ),
        min_length=20,
    )
    extracted_info: ExtractedInfo = Field(
        description="Normalized pre-registration data extracted from the message.",
    )

    @field_validator("reply_text")
    @classmethod
    def _clean_reply(cls, v: str) -> str:
        v = v.strip()
        if v.startswith("```"):
            v = re.sub(r"^```\w*\n?", "", v)
            v = re.sub(r"\n?```$", "", v)
            v = v.strip()
        return v
