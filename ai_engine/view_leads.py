"""Display the leads stored in leads.db as a table in the Terminal."""

from __future__ import annotations

import io
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

if hasattr(sys.stdout, "buffer"):
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding="utf-8")

from leads_store import fetch_all_leads  # noqa: E402


def render_table(rows: list[dict]) -> str:
    headers = ["ID", "Created", "Level", "Name", "Phone", "Filiere", "Subject"]
    widths = {h: len(h) for h in headers}

    formatted = []
    for r in rows:
        vals = [
            r.get("id") or "-",
            (r.get("created_at") or "-")[:19],
            r.get("level") or "-",
            r.get("name") or "-",
            r.get("phone") or "-",
            r.get("filiere") or "-",
            r.get("subject") or "-",
        ]
        formatted.append(vals)
        for h, v in zip(headers, vals):
            widths[h] = max(widths[h], len(str(v)))

    sep = "+" + "+".join("-" * (widths[h] + 2) for h in headers) + "+"
    line = "| " + " | ".join(str(h).ljust(widths[h]) for h in headers) + " |"

    lines = [sep, line, sep]
    for vals in formatted:
        row_line = "| " + " | ".join(
            str(v).ljust(widths[h]) for h, v in zip(headers, vals)
        ) + " |"
        lines.append(row_line)
    lines.append(sep)
    return "\n".join(lines)


def main() -> None:
    rows = fetch_all_leads()
    if not rows:
        print("No leads recorded yet.")
        return
    print(f"\n{len(rows)} lead(s) recorded:\n")
    print(render_table(rows))


if __name__ == "__main__":
    main()
