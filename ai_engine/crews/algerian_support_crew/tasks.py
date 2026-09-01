"""Tasks for the Algerian school support crew.

The primary production path uses ``AlgerianSupportAgents.get_structured_reply``
(direct Gemini structured call).  The CrewAI Task path is kept for backward
compatibility and complex multi-agent scenarios.
"""

from textwrap import dedent

from crewai import Task

from models import AgentReply
from prompts import SYSTEM_PROMPT


class AlgerianSupportTasks:
    """Tasks de l'agent de l'ecole de soutien (production-grade)."""

    def handle_message(self, agent, message: str) -> Task:
        """Repond a un message et retourne un JSON structure AgentReply.

        This task is used in the CrewAI pipeline path. The primary production
        path uses ``agents.get_structured_reply()`` directly.
        """
        return Task(
            description=dedent(
                f"""
                L'eleve ou le parent vient d'ecrire :

                MESSAGE ENTRANT : "{message}"

                1) Reponds de facon claire, chaleureuse et complete a la question
                   (utilise les bons tarifs, horaires et adresse de l'ecole).
                   Reponds en darija algerienne sauf si l'eleve ecrit en francais.
                2) Extrais les donnees de pre-inscription s'il y en a (nom,
                   telephone, niveau, filiere, matiere). Si des champs manquent,
                   demande-les poliment dans ta reponse et mets-les a null.

                Le telephone doit etre au format +213 XXX XX XX XX.
                Le niveau doit etre: BEM, BAC 1AS, BAC 2AS, BAC 3AS, ou BAC.
                La filiere doit etre: scientifique, litteraire, math,
                technologique, sport, ou langues.
                """
            ),
            expected_output=(
                "Un objet JSON strictement conforme au schema AgentReply "
                "(reply_text + extracted_info avec les Enums exactes)."
            ),
            output_pydantic=AgentReply,
            agent=agent,
        )
