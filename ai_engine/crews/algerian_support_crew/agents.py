"""Agents for the Algerian school support crew.

Provides two invocation paths:
1. **CrewAI Agent** — traditional multi-agent path (used when full Crew pipeline
   is desired).
2. **Structured Gemini call** — direct ``langchain-google-genai`` call with
   ``with_structured_output()``, guaranteeing the LLM returns a JSON that
   matches ``AgentReply`` exactly. This is the *primary* path used in
   production for maximum reliability.
"""

from __future__ import annotations

from typing import Optional

from langchain_google_genai import ChatGoogleGenerativeAI

from config import SCHOOL_INFO
from models import AgentReply
from prompts import SYSTEM_PROMPT


class AlgerianSupportAgents:
    """Agents de l'ecole de soutien algerienne (BAC/BEM), production-grade."""

    def __init__(
        self,
        api_key: str = None,
        model: str = "gemini-3.6-flash",
        temperature: float = 0.4,
    ):
        self._api_key = api_key
        self._model = model
        self._temperature = temperature

    # ------------------------------------------------------------------
    # Internal helpers
    # ------------------------------------------------------------------

    def _build_llm(self) -> ChatGoogleGenerativeAI:
        return ChatGoogleGenerativeAI(
            model=self._model,
            google_api_key=self._api_key,
            temperature=self._temperature,
            max_output_tokens=1024,
        )

    def _build_structured_llm(self):
        """Return a langchain LLM bound to the AgentReply response schema.

        The returned object accepts a plain string prompt and *always* returns
        an ``AgentReply`` instance — no JSON parsing on our side.
        """
        llm = self._build_llm()
        return llm.with_structured_output(AgentReply)

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------

    def get_structured_reply(self, message: str) -> AgentReply:
        """Direct structured call to Gemini — primary production path.

        Returns an ``AgentReply`` validated by Pydantic. If the LLM fails to
        produce a valid schema (extremely rare with structured output), a
        defensive fallback is returned.
        """
        structured_llm = self._build_structured_llm()
        prompt = (
            f"{SYSTEM_PROMPT}\n\n"
            f"---\n"
            f"Message de l'eleve/parent :\n"
            f'"{message}"\n\n'
            f"Reponds et extrais les donnees de pre-inscription."
        )
        try:
            reply: AgentReply = structured_llm.invoke(prompt)
            return reply
        except Exception as exc:  # noqa: BLE001
            return AgentReply(
                reply_text=(
                    "Saha lik! Yallah daba 3andna 3akla takniya, jari ngssem9 "
                    "l wajda. 3aytelna baad chwiya wala t3awed b messajek. "
                    f"({exc.__class__.__name__})"
                ),
                extracted_info={},
            )

    # ------------------------------------------------------------------
    # CrewAI path (kept for backward compat / complex multi-agent flows)
    # ------------------------------------------------------------------

    def support_advisor_crewai(self):
        """Return a CrewAI Agent (used only in the Crew pipeline path)."""
        from crewai import Agent, LLM

        gemini = LLM(
            model=f"gemini/{self._model}" if not self._model.startswith("gemini/") else self._model,
            api_key=self._api_key,
            temperature=self._temperature,
        )
        return Agent(
            role=(
                "Conseiller pedagogique et commercial de l'ecole de soutien "
                "algerienne specialisee dans le BAC et le BEM"
            ),
            backstory=SYSTEM_PROMPT,
            goal=(
                "Repondre de maniere chaleureuse et professionnelle a chaque "
                "question des eleves/parents en darija (ou francais), puis "
                "extraire les donnees de pre-inscription (nom, telephone, "
                "niveau, filiere) et les retourner dans le format JSON impose."
            ),
            allow_delegation=False,
            verbose=True,
            llm=gemini,
        )
