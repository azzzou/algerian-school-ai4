import http.server
import socketserver
import sqlite3
from pathlib import Path

DB_PATH = Path(__file__).resolve().parent / "leads.db"

PORT = 8050

HTML_TEMPLATE = """<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لوحة تحكم مدرسة الدعم الجزائرية | AI Leads Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; }
        .glass-card { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 16px; padding: 24px; }
        .stat-label { color: #6c757d; font-size: 0.9rem; }
        .stat-value { font-size: 2rem; font-weight: 700; }
    </style>
</head>
<body class="p-4">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1"><i class="fa-solid fa-graduation-cap text-primary me-2"></i>لوحة تحكم مدرسة الدعم الجزائرية</h2>
                <p class="text-muted small mb-0">التسجيلات القادمة من الذكاء الاصطناعي (CrewAI &amp; Gemini)</p>
            </div>
            <button class="btn btn-primary rounded-pill px-4" onclick="location.reload()">
                <i class="fa-solid fa-rotate me-2"></i>تحديث الصفحة
            </button>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="glass-card">
                    <div class="stat-label">إجمالي التسجيلات (Leads)</div>
                    <div class="stat-value text-info">{total}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="glass-card">
                    <div class="stat-label">تسجيلات جاهزة (HOT Leads)</div>
                    <div class="stat-value text-danger">{total}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="glass-card">
                    <div class="stat-label">حالة النموذج</div>
                    <div class="fs-4 text-success fw-bold"><i class="fa-solid fa-circle-check me-1"></i> Gemini 3.6 Flash يعمل</div>
                </div>
            </div>
        </div>

        <div class="glass-card">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-users text-warning me-2"></i>قائمة التسجيلات (Leads)</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>الاسم</th>
                            <th>رقم الهاتف</th>
                            <th>المستوى</th>
                            <th>الشعبة / المادة</th>
                            <th>الحالة</th>
                            <th>تاريخ التسجيل</th>
                        </tr>
                    </thead>
                    <tbody>
                        __ROWS__
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
"""

ROW_TEMPLATE = """<tr>
    <td><code>{lead_id}</code></td>
    <td class="fw-bold">{name}</td>
    <td class="font-monospace text-warning">{phone}</td>
    <td><span class="badge text-bg-primary px-2 py-1">{level}</span></td>
    <td>{filiere} / {subject}</td>
    <td><span class="badge text-bg-danger px-2 py-1">HOT</span></td>
    <td class="text-muted small">{created}</td>
</tr>"""


def fetch_leads():
    conn = sqlite3.connect(str(DB_PATH))
    conn.row_factory = sqlite3.Row
    rows = conn.execute(
        "SELECT * FROM leads ORDER BY created_at DESC"
    ).fetchall()
    conn.close()
    return [dict(r) for r in rows]


class DashboardHandler(http.server.SimpleHTTPRequestHandler):
    def do_GET(self):
        if self.path != "/":
            self.send_error(404)
            return

        rows = fetch_leads()
        row_html = "".join(
            ROW_TEMPLATE.format(
                lead_id=str(r["id"])[:8],
                name=str(r["name"] or "-"),
                phone=str(r["phone"] or "-"),
                level=str(r["level"] or "-"),
                filiere=str(r["filiere"] or "-"),
                subject=str(r["subject"] or "-"),
                created=str(r["created_at"] or "-")[:19],
            )
            for r in rows
        )
        if not row_html:
            row_html = (
                '<tr><td colspan="7" class="text-center text-muted">'
                "لا توجد تسجيلات بعد</td></tr>"
            )

        html = (
            HTML_TEMPLATE.replace("__TOTAL__", str(len(rows)))
            .replace("__ROWS__", row_html)
        )
        body = html.encode("utf-8")
        self.send_response(200)
        self.send_header("Content-type", "text/html; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, format, *args):
        print("[dashboard] %s" % (format % args))


def main():
    socketserver.TCPServer.allow_reuse_address = True
    handler = DashboardHandler
    with socketserver.TCPServer(("", PORT), handler) as httpd:
        print(f"== Dashboard is live at http://localhost:{PORT} ==")
        httpd.serve_forever()


if __name__ == "__main__":
    main()
