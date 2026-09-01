"""Algerian School Support Crew — main entry point.

Production-grade handler that routes every incoming student/parent message
through Gemini structured output, guaranteeing a validated ``AgentReply``
JSON on every call.
"""

from __future__ import annotations

import os
from typing import Optional

from pydantic import BaseModel

from agents import AlgerianSupportAgents
from models import AgentReply, ExtractedInfo, LeadScore
from tasks import AlgerianSupportTasks


class AlgerianSupportCrew:
    """Crew de l'ecole de soutien algerienne (BAC/BEM), production-grade.

    Expose ``handle`` which returns a deterministic ``AgentReply`` (Pydantic
    model) ready to be consumed by the Messenger gateway or any downstream
    service.

    **Primary path**: direct ``langchain-google-genai`` structured output
    (``with_structured_output(AgentReply)``).  The CrewAI pipeline is kept as
    a fallback for complex multi-agent scenarios.
    """

    def __init__(
        self,
        message: str,
        google_api_key: Optional[str] = None,
        model: str = "gemini-3.6-flash",
        verbose: bool = False,
    ):
        self.message = message
        self.google_api_key = google_api_key or os.getenv("GOOGLE_API_KEY")
        self.model = model
        self.verbose = verbose

    # ------------------------------------------------------------------
    # Primary path: direct structured Gemini call
    # ------------------------------------------------------------------

    def _structured_reply(self) -> AgentReply:
        """Call Gemini via langchain structured output — most reliable path."""
        agents = AlgerianSupportAgents(
            api_key=self.google_api_key,
            model=self.model,
            temperature=0.4,
        )
        return agents.get_structured_reply(self.message)

    # ------------------------------------------------------------------
    # Fallback path: CrewAI pipeline
    # ------------------------------------------------------------------

    def _crewai_reply(self) -> AgentReply:
        """Run the CrewAI pipeline as a fallback."""
        from crewai import Crew

        agents = AlgerianSupportAgents(
            api_key=self.google_api_key,
            model=self.model,
        )
        tasks = AlgerianSupportTasks()

        advisor = agents.support_advisor_crewai()
        task = tasks.handle_message(advisor, self.message)
        crew = Crew(agents=[advisor], tasks=[task], verbose=self.verbose)

        result = crew.kickoff()

        if hasattr(result, "pydantic") and isinstance(result.pydantic, BaseModel):
            return result.pydantic
        if isinstance(result, BaseModel):
            return result

        raw = getattr(result, "raw", result)
        try:
            import json
            data = json.loads(raw) if isinstance(raw, str) else raw
            return AgentReply.model_validate(data)
        except Exception:
            return AgentReply(reply_text=str(raw), extracted_info={})

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------

    def handle(self) -> AgentReply:
        """Run the agent and return a validated AgentReply.

        Primary: structured Gemini call.  On failure, falls back to CrewAI,
        then to a safe error reply.
        """
        try:
            reply = self._structured_reply()
        except Exception:
            try:
                reply = self._crewai_reply()
            except Exception as exc:  # noqa: BLE001
                return AgentReply(
                    reply_text=(
                        "Saha lik! Yallah daba 3andna 3akla takniya, jari "
                        "ngssem9 l wajda. 3aytelna baad chwiya wala t3awed "
                        f"b messajek. ({exc.__class__.__name__})"
                    ),
                    extracted_info={},
                )

        # Final sanitization — drop noisy / fake extracted values.
        reply.extracted_info = self._clean_extracted_info(reply.extracted_info)
        return reply

    def run(self) -> AgentReply:
        """Alias for handle() — backward compatibility with test scripts."""
        return self.handle()

    # ------------------------------------------------------------------
    # Extracted-info sanitization
    # ------------------------------------------------------------------

    @staticmethod
    def _clean_extracted_info(info) -> ExtractedInfo:
        """Sanitise the model's extracted_info to drop fake/noisy values.

        Gemini sometimes fills a field with the *name of another field* (e.g.
        student_name="phone_number", phone_number="name") when no real data
        is present.  We normalise each field to None unless it looks like
        genuine user data.
        """
        if info is None:
            return ExtractedInfo()

        data = info.model_dump() if isinstance(info, BaseModel) else dict(info or {})

        # Field names that must never be accepted as *values*.
        _field_names = {
            "name", "phone", "level", "filiere", "subject",
            "student_name", "phone_number", "branch_or_level", "lead_score",
            "extracted_info", "reply_text", "null", "none", "unknown",
        }

        def _clean(key: str, value) -> Optional[str]:
            if value is None:
                return None
            v = str(value).strip()
            if not v or v.lower() in _field_names:
                return None
            if key == "phone_number":
                digits = "".join(ch for ch in v if ch.isdigit())
                if len(digits) < 8:
                    return None
            if key == "student_name":
                _bad = {
                    "bac", "bem", "math", "physics", "sciences",
                    "scientifique", "litteraire", "bac 1as",
                    "bac 2as", "bac 3as", "hot", "warm", "cold",
                }
                if v.lower() in _bad:
                    return None
            return v

        cleaned = {
            "student_name": _clean("student_name", data.get("student_name")),
            "phone_number": _clean("phone_number", data.get("phone_number")),
            "branch_or_level": data.get("branch_or_level"),
            "lead_score": data.get("lead_score"),
            "level": data.get("level"),
            "filiere": data.get("filiere"),
            "subject": _clean("subject", data.get("subject")),
        }
        return ExtractedInfo(**cleaned)


# ======================================================================
# Interactive CLI
# ======================================================================
if __name__ == "__main__":
    print("\n## Ecole de soutien Algerienne (BAC/BEM) - mode interactif")
    print("-----------------------------------------------------------")
    q = input("Pose ta question (darija ou francais) : ").strip() or (
        "Saha, bech nesjel walahdead lel BAC? Chhal el thaman?"
    )
    crew = AlgerianSupportCrew(message=q)
    result = crew.handle()
    print("\n\n########################")
    print("## Reponse structuree :")
    print("########################\n")
    print(result.model_dump_json(indent=2))
