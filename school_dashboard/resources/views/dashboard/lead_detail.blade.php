<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead: {{ $lead->name ?? 'Detail' }} | Algerian School Support</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        :root { --bg-dark:#0f172a; --bg-card:#1e293b; --bg-card-hover:#334155; --text-primary:#f8fafc; --text-muted:#94a3b8; --accent-blue:#3b82f6; --accent-red:#ef4444; --accent-green:#22c55e; --accent-yellow:#eab308; --border-color:rgba(255,255,255,0.08); }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:var(--bg-dark); color:var(--text-primary); font-family:'Segoe UI',system-ui,sans-serif; min-height:100vh; }
        .navbar-custom { background:linear-gradient(135deg,#1e293b,#0f172a); border-bottom:1px solid var(--border-color); padding:1rem 0; }
        .navbar-custom .brand { display:flex; align-items:center; gap:12px; text-decoration:none; color:var(--text-primary); }
        .navbar-custom .brand-icon { width:42px; height:42px; background:linear-gradient(135deg,var(--accent-blue),#a855f7); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; }
        .container-main { padding:2rem 1rem; max-width:900px; margin:0 auto; }
        .detail-card { background:var(--bg-card); border:1px solid var(--border-color); border-radius:16px; padding:2rem; }
        .detail-label { color:var(--text-muted); font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; font-weight:600; margin-bottom:0.3rem; }
        .detail-value { font-size:1.05rem; font-weight:600; margin-bottom:1.25rem; }
        .detail-value.phone { font-family:'Courier New',monospace; color:var(--accent-yellow); }
        .reply-box { background:var(--bg-dark); border:1px solid var(--border-color); border-radius:12px; padding:1.5rem; white-space:pre-wrap; font-size:0.92rem; line-height:1.7; color:var(--text-muted); }
        .badge-hot { background:rgba(239,68,68,0.15); color:#f87171; border:1px solid rgba(239,68,68,0.3); padding:0.35rem 0.75rem; border-radius:8px; font-weight:600; }
        .badge-new { background:rgba(34,197,94,0.15); color:#4ade80; border:1px solid rgba(34,197,94,0.3); padding:0.35rem 0.75rem; border-radius:8px; font-weight:600; }
        .btn-outline-custom { background:transparent; color:var(--text-muted); border:1px solid var(--border-color); border-radius:8px; padding:0.6rem 1.2rem; font-weight:500; text-decoration:none; display:inline-flex; align-items:center; gap:0.4rem; }
        .btn-outline-custom:hover { background:var(--bg-card-hover); color:var(--text-primary); border-color:var(--text-muted); }
    </style>
</head>
<body>
    <nav class="navbar-custom">
        <div class="container-main d-flex justify-content-between align-items-center">
            <a href="/ai-dashboard" class="brand">
                <div class="brand-icon"><i class="fas fa-graduation-cap"></i></div>
                <div><h4 style="font-size:1.1rem;font-weight:700;">Algerian School Support</h4></div>
            </a>
            <a href="/ai-dashboard" class="btn-outline-custom"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
        </div>
    </nav>
    <div class="container-main">
        <h3 class="fw-bold mb-4"><i class="fas fa-user-circle text-primary me-2"></i>Lead Details</h3>
        <div class="detail-card">
            <div class="row">
                <div class="col-md-6">
                    <div class="detail-label">Name</div>
                    <div class="detail-value">{{ $lead->name ?? '-' }}</div>

                    <div class="detail-label">Phone</div>
                    <div class="detail-value phone">{{ $lead->phone ?? '-' }}</div>

                    <div class="detail-label">Level</div>
                    <div class="detail-value">{{ $lead->level ?? '-' }}</div>

                    <div class="detail-label">Stream (Filiere)</div>
                    <div class="detail-value">{{ $lead->filiere ?? '-' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="detail-label">Subject</div>
                    <div class="detail-value">{{ $lead->subject ?? '-' }}</div>

                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        @if($lead->is_interested)
                            <span class="badge-hot"><i class="fas fa-fire me-1"></i>HOT (Interested)</span>
                        @else
                            <span class="badge-new"><i class="fas fa-circle me-1"></i>NEW</span>
                        @endif
                    </div>

                    <div class="detail-label">Created At</div>
                    <div class="detail-value">{{ $lead->created_at ? \Carbon\Carbon::parse($lead->created_at)->format('Y-m-d H:i:s') : '-' }}</div>

                    <div class="detail-label">Lead ID</div>
                    <div class="detail-value" style="font-family:monospace;font-size:0.85rem;color:var(--text-muted);">{{ $lead->id }}</div>
                </div>
            </div>
            <hr style="border-color:var(--border-color);">
            <div class="detail-label"><i class="fas fa-robot me-1"></i>AI Reply (Darija)</div>
            <div class="reply-box">{{ $lead->ai_reply ?? 'No AI reply recorded.' }}</div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
