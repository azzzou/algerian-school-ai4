"""
Script de test local : simule des questions deja posees par des eleves
(en darija algerienne) a l'ecole de soutien pour BAC/BEM.

Utilise le crew AlgerianSupportAgents avec Gemini via CrewAI, et affiche
le resultat structure (JSON) + le reply_text en darija.
"""

import os
import sys
from pathlib import Path

from dotenv import load_dotenv

# Charge .env depuis la racine de ai_engine (et en secours depuis le dossier crew)
_AI_ENGINE_ROOT = Path(__file__).resolve().parents[3]
load_dotenv(_AI_ENGINE_ROOT / ".env")
load_dotenv()

from main import AlgerianSupportCrew  # noqa: E402

QUESTIONS_DARIJA = [
    "Saha, anisegh tamrin BAC bech nchrik fih? Chhal el thaman l chahr?",
    "Wach kayn cours BEM l madat el math? Chhal?",
    "Saha, wach l'ecole kayna fel 3asima? Wach les cours toujours yejiw nhar jem3a w sebt?",
    "Chhal thiwwlha revizion intensive? Wach 5000 ou 6000 DA?",
    "Saha ana BAC 3AS scientifique, bghit nchrik. Chhal el bois w les horaires?",
    "Saha ana Yacine, n7eb nchrik l BEM.fisya. 0770123456. Bghit sciences.",
]

MODEL = "gemini-3.6-flash"


def run_test(google_api_key: str = None):
    api_key = google_api_key or os.getenv("GOOGLE_API_KEY")
    if not api_key:
        print("ERREUR : GOOGLE_API_KEY introuvable. Mets ta cle dans .env ou l'env.")
        sys.exit(1)

    for i, question in enumerate(QUESTIONS_DARIJA, 1):
        print("=" * 70)
        print(f"\n### QUESTION {i}/{len(QUESTIONS_DARIJA)} : {question}\n")

        crew = AlgerianSupportCrew(message=question, google_api_key=api_key, model=MODEL)
        reply = crew.handle()

        print(">>> REPONSE (darija) :")
        print(reply.reply_text)
        print("\n>>> JSON structure :")
        print(reply.model_dump_json(indent=2))
        print(f"\n# Fin de la question {i}\n")


if __name__ == "__main__":
    run_test()
