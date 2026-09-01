"""Centralised, single-source-of-truth configuration for the Algerian
school support business logic. Keeping pricing, hours and location here makes
it trivial to update without touching the prompt/agent code.
"""

SCHOOL_INFO = {
    "name": "Ecole de soutien - Alger (el 3asima)",
    "city": "Alger (El 3asima), Algerie",
    "address": "Alger, la capitale algerienne",
    # Course fees (Algerian Dinar, per subject per month)
    "bem_fee": "2000 DA par matiere par mois",
    "bac_fee": "2500 DA par matiere par mois",
    "intensive_revision_fee": "entre 5000 et 6000 DA",
    # Weekly schedule
    "hours": "vendredi (jeudi soir), samedi et soirees en semaine",
    "details": {
        "bem": "Brevet d'Enseignement Moyen - niveau 4eme moyenne",
        "bac": "Baccalaureat - niveaux 1AS, 2AS et 3AS secondaire",
    },
}
