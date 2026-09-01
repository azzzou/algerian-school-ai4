<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Leads Dashboard | Algerian School Support</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --bg-card-hover: #334155;
            --bg-input: #0f172a;
            --text-primary: #f8fafc;
            --text-muted: #94a3b8;
            --accent-blue: #3b82f6;
            --accent-red: #ef4444;
            --accent-green: #22c55e;
            --accent-yellow: #eab308;
            --accent-purple: #a855f7;
            --border-color: rgba(255,255,255,0.08);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg-dark); color: var(--text-primary); font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; min-height: 100vh; }

        /* Navbar */
        .navbar-custom { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 1px solid var(--border-color); padding: 1rem 0; }
        .navbar-custom .brand { display: flex; align-items: center; gap: 12px; text-decoration: none; color: var(--text-primary); }
        .navbar-custom .brand-icon { width: 42px; height: 42px; background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple)); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .navbar-custom .brand-text h4 { font-size: 1.15rem; font-weight: 700; margin: 0; }
        .navbar-custom .brand-text small { color: var(--text-muted); font-size: 0.75rem; }

        .container-main { padding: 2rem 1rem; max-width: 1400px; margin: 0 auto; }

        /* Cards */
        .glass-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; }
        .stat-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; text-align: center; transition: all 0.3s ease; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
        .stat-card.blue::before { background: linear-gradient(90deg, var(--accent-blue), var(--accent-purple)); }
        .stat-card.red::before { background: linear-gradient(90deg, var(--accent-red), #f97316); }
        .stat-card.green::before { background: linear-gradient(90deg, var(--accent-green), #10b981); }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.3); }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 1rem; }
        .stat-icon.blue { background: rgba(59,130,246,0.15); color: var(--accent-blue); }
        .stat-icon.red { background: rgba(239,68,68,0.15); color: var(--accent-red); }
        .stat-icon.green { background: rgba(34,197,94,0.15); color: var(--accent-green); }
        .stat-value { font-size: 2.2rem; font-weight: 800; line-height: 1; margin-bottom: 0.3rem; }
        .stat-label { color: var(--text-muted); font-size: 0.85rem; font-weight: 500; }

        /* Filters Panel */
        .filters-panel { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem; }
        .filter-group label { color: #475569; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; margin-bottom: 0.4rem; display: block; }
        .filter-group .form-select, .filter-group .form-control { background: #f8fafc; border: 1px solid #e2e8f0; color: #1e293b; border-radius: 8px; font-size: 0.85rem; padding: 0.6rem 0.8rem; }
        .filter-group .form-select:focus, .filter-group .form-control:focus { border-color: var(--accent-blue); box-shadow: 0 0 0 2px rgba(59,130,246,0.15); }
        .filter-group .form-select option { background: #ffffff; color: #1e293b; }
        .filter-group .form-control::placeholder { color: var(--text-muted); }

        /* Buttons */
        .btn-dark-custom { background: var(--accent-blue); color: white; border: none; border-radius: 8px; padding: 0.6rem 1.2rem; font-weight: 600; font-size: 0.85rem; }
        .btn-dark-custom:hover { background: #2563eb; color: white; }
        .btn-outline-custom { background: transparent; color: var(--text-muted); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.6rem 1.2rem; font-weight: 500; font-size: 0.85rem; }
        .btn-outline-custom:hover { background: var(--bg-card-hover); color: var(--text-primary); border-color: var(--text-muted); }
        .btn-export { background: rgba(34,197,94,0.15); color: #4ade80; border: 1px solid rgba(34,197,94,0.3); border-radius: 8px; padding: 0.6rem 1.2rem; font-weight: 600; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; }
        .btn-export:hover { background: rgba(34,197,94,0.25); color: #4ade80; }

        /* Table */
        .table-dark-custom { background: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; }
        .table-dark-custom thead th { background: #f1f5f9; color: #334155; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 0.9rem 1rem; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        .table-dark-custom thead th a { color: #334155; text-decoration: none; }
        .table-dark-custom thead th a:hover { color: var(--accent-blue); }
        .table-dark-custom tbody td { padding: 0.9rem 1rem; border-bottom: 1px solid #e2e8f0; vertical-align: middle; color: #212529; font-size: 0.88rem; }
        .table-dark-custom tbody tr:hover { background: #f8fafc; }
        .table-dark-custom tbody tr:last-child td { border-bottom: none; }
        .table-dark-custom tbody td strong { color: #1e293b; font-weight: 600; }

        /* Badges */
        .badge-hot { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 0.3rem 0.65rem; border-radius: 8px; font-weight: 600; font-size: 0.72rem; }
        .badge-warm { background: #fefce8; color: #ca8a04; border: 1px solid #fef08a; padding: 0.3rem 0.65rem; border-radius: 8px; font-weight: 600; font-size: 0.72rem; }
        .badge-cold { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; padding: 0.3rem 0.65rem; border-radius: 8px; font-weight: 600; font-size: 0.72rem; }
        .badge-new { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; padding: 0.3rem 0.65rem; border-radius: 8px; font-weight: 600; font-size: 0.72rem; }
        .badge-level { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 0.3rem 0.65rem; border-radius: 8px; font-weight: 600; font-size: 0.72rem; }
        .phone-text { font-family: 'Courier New', monospace; color: #059669; font-weight: 600; font-size: 0.85rem; }
        .id-text { font-family: 'Courier New', monospace; color: #64748b; font-size: 0.8rem; }
        .text-truncate-custom { max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #475569; }
        .table-dark-custom .text-muted { color: #64748b !important; }

        /* Pagination */
        .pagination-dark .page-link { background: #ffffff; border: 1px solid #e2e8f0; color: #475569; font-size: 0.85rem; padding: 0.5rem 0.85rem; margin: 0 2px; border-radius: 8px; }
        .pagination-dark .page-link:hover { background: #f1f5f9; color: #1e293b; border-color: var(--accent-blue); }
        .pagination-dark .page-item.active .page-link { background: var(--accent-blue); color: white; border-color: var(--accent-blue); }
        .pagination-dark .page-item.disabled .page-link { opacity: 0.4; }

        /* Reply Cards */
        .reply-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; height: 100%; transition: all 0.3s ease; }
        .reply-card:hover { border-color: var(--accent-blue); }
        .reply-card .reply-name { font-weight: 700; font-size: 0.95rem; margin-bottom: 0.5rem; color: #1e293b; }
        .reply-card .reply-text { color: #475569; font-size: 0.85rem; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .reply-card .reply-time { color: var(--accent-blue); font-size: 0.75rem; margin-top: 0.75rem; }

        .section-title { font-size: 0.85rem; font-weight: 700; color: #f8fafc; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1.25rem; }
        .empty-state { text-align: center; padding: 4rem 2rem; color: #64748b; }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.3; }
        .results-info { color: #94a3b8; font-size: 0.82rem; }

        /* Modal Dark */
        .modal-dark .modal-content { background: #ffffff; border: 1px solid #e2e8f0; color: #1e293b; border-radius: 16px; }
        .modal-dark .modal-header { border-bottom: 1px solid #e2e8f0; }
        .modal-dark .modal-title { font-weight: 700; color: #1e293b; }
        .modal-dark .btn-close { filter: none; }
        .modal-dark .modal-body dt { color: #64748b; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .modal-dark .modal-body dd { font-weight: 600; margin-bottom: 1rem; color: #1e293b; }
        .modal-dark .modal-footer { border-top: 1px solid #e2e8f0; }

        @media (max-width: 768px) { .stat-value { font-size: 1.6rem; } .container-main { padding: 1rem 0.5rem; } }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar-custom">
        <div class="container-main d-flex justify-content-between align-items-center">
            <a href="/" class="brand">
                <div class="brand-icon"><i class="fas fa-graduation-cap"></i></div>
                <div class="brand-text">
                    <h4>Algerian School Support</h4>
                    <small>AI-Powered Lead Management</small>
                </div>
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-success bg-opacity-25 text-success px-3 py-2">
                    <i class="fas fa-circle-check me-1"></i> System Online
                </span>
                <a href="/" class="btn btn-outline-light btn-sm rounded-pill px-3">
                    <i class="fas fa-home me-1"></i> Home
                </a>
            </div>
        </div>
    </nav>

    <div class="container-main">

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1"><i class="fas fa-robot text-primary me-2"></i>AI Leads Dashboard</h2>
                <p class="text-muted small mb-0">Real-time leads from CrewAI &amp; Gemini (Darija/French)</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('ai.dashboard.export', request()->query()) }}" class="btn-export">
                    <i class="fas fa-file-csv"></i> Export CSV
                </a>
                <button class="btn btn-outline-light rounded-pill px-4" onclick="location.reload()">
                    <i class="fas fa-rotate me-2"></i>Refresh
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card blue">
                    <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                    <div class="stat-value text-info">{{ $total }}</div>
                    <div class="stat-label">Total Leads</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card red">
                    <div class="stat-icon red"><i class="fas fa-fire"></i></div>
                    <div class="stat-value" style="color: #f87171;">{{ $hot }}</div>
                    <div class="stat-label">HOT Leads (Ready to Convert)</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card green">
                    <div class="stat-icon green"><i class="fas fa-thermometer-half"></i></div>
                    <div class="stat-value text-success">{{ $warm }}</div>
                    <div class="stat-label">WARM Leads (Interested)</div>
                </div>
            </div>
        </div>

        <!-- Filters Panel -->
        <form method="GET" action="{{ route('ai.dashboard') }}" id="filtersForm">
            <div class="filters-panel">
                <div class="row g-3 align-items-end">
                    <!-- Search -->
                    <div class="col-lg-3 col-md-6">
                        <div class="filter-group">
                            <label><i class="fas fa-search me-1"></i>Search</label>
                            <input type="text" name="q" class="form-control" placeholder="Name, phone, level..." value="{{ request('q') }}">
                        </div>
                    </div>
                    <!-- Level -->
                    <div class="col-lg-2 col-md-6">
                        <div class="filter-group">
                            <label><i class="fas fa-graduation-cap me-1"></i>Level</label>
                            <select name="level" class="form-select">
                                <option value="">All Levels</option>
                                @foreach($levels as $lvl)
                                    <option value="{{ $lvl }}" {{ request('level') === $lvl ? 'selected' : '' }}>{{ $lvl }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <!-- Lead Score -->
                    <div class="col-lg-2 col-md-6">
                        <div class="filter-group">
                            <label><i class="fas fa-fire me-1"></i>Lead Score</label>
                            <select name="score" class="form-select">
                                <option value="">All Scores</option>
                                <option value="HOT" {{ request('score') === 'HOT' ? 'selected' : '' }}>HOT</option>
                                <option value="WARM" {{ request('score') === 'WARM' ? 'selected' : '' }}>WARM</option>
                                <option value="COLD" {{ request('score') === 'COLD' ? 'selected' : '' }}>COLD</option>
                            </select>
                        </div>
                    </div>
                    <!-- From Date -->
                    <div class="col-lg-2 col-md-6">
                        <div class="filter-group">
                            <label><i class="fas fa-calendar me-1"></i>From</label>
                            <input type="date" name="from" class="form-control" value="{{ request('from') }}">
                        </div>
                    </div>
                    <!-- To Date -->
                    <div class="col-lg-2 col-md-6">
                        <div class="filter-group">
                            <label><i class="fas fa-calendar-check me-1"></i>To</label>
                            <input type="date" name="to" class="form-control" value="{{ request('to') }}">
                        </div>
                    </div>
                    <!-- Per Page -->
                    <div class="col-lg-1 col-md-6">
                        <div class="filter-group">
                            <label><i class="fas fa-list me-1"></i>Show</label>
                            <select name="per_page" class="form-select" onchange="this.form.submit()">
                                @foreach([10, 15, 25, 50, 100] as $n)
                                    <option value="{{ $n }}" {{ request('per_page', 15) == $n ? 'selected' : '' }}>{{ $n }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-dark-custom"><i class="fas fa-filter me-1"></i>Apply Filters</button>
                    <a href="{{ route('ai.dashboard') }}" class="btn btn-outline-custom"><i class="fas fa-times me-1"></i>Clear All</a>
                </div>
            </div>
            <input type="hidden" name="sort" value="{{ request('sort', 'created_at') }}">
            <input type="hidden" name="dir" value="{{ request('dir', 'desc') }}">
        </form>

        <!-- Results Info -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="results-info">
                Showing <strong>{{ $leads->firstItem() ?? 0 }}</strong>–<strong>{{ $leads->lastItem() ?? 0 }}</strong> of <strong>{{ $leads->total() }}</strong> leads
            </span>
        </div>

        <!-- Leads Table -->
        <div class="table-dark-custom mb-4">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>
                                @php $s = request('sort') === 'student_name' && request('dir') === 'asc' ? 'desc' : 'asc'; @endphp
                                <a href="?{{ http_build_query(array_merge(request()->query(), ['sort'=>'student_name','dir'=>$s])) }}">
                                    <i class="fas fa-user me-1"></i>Student Name
                                    @if(request('sort')==='student_name') <i class="fas fa-sort-{{ request('dir')==='asc'?'up':'down' }}"></i> @endif
                                </a>
                            </th>
                            <th><i class="fas fa-phone me-1"></i>Phone</th>
                            <th><i class="fas fa-code-branch me-1"></i>Branch / Level</th>
                            <th>
                                @php $s = request('sort') === 'lead_score' && request('dir') === 'asc' ? 'desc' : 'asc'; @endphp
                                <a href="?{{ http_build_query(array_merge(request()->query(), ['sort'=>'lead_score','dir'=>$s])) }}">
                                    <i class="fas fa-fire me-1"></i>Score
                                    @if(request('sort')==='lead_score') <i class="fas fa-sort-{{ request('dir')==='asc'?'up':'down' }}"></i> @endif
                                </a>
                            </th>
                            <th><i class="fas fa-comment me-1"></i>AI Reply</th>
                            <th>
                                @php $s = request('sort') === 'created_at' && request('dir') === 'asc' ? 'desc' : 'asc'; @endphp
                                <a href="?{{ http_build_query(array_merge(request()->query(), ['sort'=>'created_at','dir'=>$s])) }}">
                                    <i class="fas fa-clock me-1"></i>Date
                                    @if(request('sort')==='created_at') <i class="fas fa-sort-{{ request('dir')==='asc'?'up':'down' }}"></i> @endif
                                </a>
                            </th>
                            <th><i class="fas fa-cog me-1"></i></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($leads as $lead)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-primary bg-opacity-25 d-flex align-items-center justify-content-center me-2" style="width:32px;height:32px;flex-shrink:0;">
                                        <i class="fas fa-user text-primary" style="font-size:0.8rem;"></i>
                                    </div>
                                    <strong>{{ $lead->display_name ?? '-' }}</strong>
                                </div>
                            </td>
                            <td><span class="phone-text">{{ $lead->display_phone ?? '-' }}</span></td>
                            <td>
                                @if($lead->branch_or_level)
                                    <span class="badge-level">{{ $lead->branch_or_level }}</span>
                                @elseif($lead->level)
                                    <span class="badge-level">{{ $lead->level }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $score = strtoupper($lead->lead_score ?? 'COLD');
                                    $scoreClass = match($score) {
                                        'HOT'  => 'badge-hot',
                                        'WARM' => 'badge-warm',
                                        default => 'badge-cold',
                                    };
                                @endphp
                                <span class="{{ $scoreClass }}">
                                    <i class="fas fa-{{ $score === 'HOT' ? 'fire' : ($score === 'WARM' ? 'thermometer-half' : 'snowflake') }} me-1"></i>
                                    {{ $score }}
                                </span>
                            </td>
                            <td>
                                @if($lead->ai_reply)
                                    <span class="text-truncate-custom d-inline-block" title="{{ $lead->ai_reply }}">
                                        {{ \Illuminate\Support\Str::limit(strip_tags($lead->ai_reply), 60) }}
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-muted small" style="white-space:nowrap;">
                                {{ $lead->created_at ? \Carbon\Carbon::parse($lead->created_at)->format('Y-m-d H:i') : '-' }}
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-custom" title="View Details"
                                        onclick="showLead({{ json_encode([
                                            'student_name' => $lead->student_name ?? $lead->name,
                                            'phone_number' => $lead->phone_number ?? $lead->phone,
                                            'branch_or_level' => $lead->branch_or_level ?? $lead->level,
                                            'lead_score' => $lead->lead_score ?? 'COLD',
                                            'level' => $lead->level,
                                            'filiere' => $lead->filiere,
                                            'subject' => $lead->subject,
                                            'ai_reply' => $lead->ai_reply,
                                            'created_at' => $lead->created_at ? \Carbon\Carbon::parse($lead->created_at)->format('Y-m-d H:i:s') : '-'
                                        ]) }})">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-inbox d-block"></i>
                                    <p>No leads found</p>
                                    <small>Adjust your filters or leads will appear when students message the AI</small>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        @if($leads->hasPages())
        <div class="d-flex justify-content-center">
            <nav>
                <ul class="pagination pagination-dark mb-0">
                    @if($leads->onFirstPage())
                        <li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-left"></i></span></li>
                    @else
                        <li class="page-item"><a class="page-link" href="{{ $leads->previousPageUrl() }}"><i class="fas fa-chevron-left"></i></a></li>
                    @endif

                    @foreach($leads->getUrlRange(max(1, $leads->currentPage()-2), min($leads->lastPage(), $leads->currentPage()+2)) as $page => $url)
                        <li class="page-item {{ $page == $leads->currentPage() ? 'active' : '' }}">
                            <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                        </li>
                    @endforeach

                    @if($leads->currentPage() + 2 < $leads->lastPage())
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <li class="page-item"><a class="page-link" href="{{ $leads->url($leads->lastPage()) }}">{{ $leads->lastPage() }}</a></li>
                    @endif

                    @if($leads->hasMorePages())
                        <li class="page-item"><a class="page-link" href="{{ $leads->nextPageUrl() }}"><i class="fas fa-chevron-right"></i></a></li>
                    @else
                        <li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-right"></i></span></li>
                    @endif
                </ul>
            </nav>
        </div>
        @endif

        <!-- Latest AI Replies -->
        @if($leads->count() > 0 && !request('q') && !request('level') && !request('score'))
        <div class="section-title mt-5"><i class="fas fa-comments me-2"></i>Latest AI Replies (Darija)</div>
        <div class="row g-3 mb-4">
            @foreach($leads->take(3) as $lead)
            <div class="col-md-4">
                <div class="reply-card">
                    <div class="d-flex align-items-center mb-2">
                        <div class="rounded-circle bg-warning bg-opacity-25 d-flex align-items-center justify-content-center me-2" style="width:36px;height:36px;flex-shrink:0;">
                            <i class="fas fa-robot text-warning" style="font-size:0.85rem;"></i>
                        </div>
                        <div class="reply-name">{{ $lead->display_name ?? 'Unknown' }}</div>
                    </div>
                    <div class="reply-text">{{ \Illuminate\Support\Str::limit(strip_tags($lead->ai_reply ?? ''), 150) ?? 'No reply recorded' }}</div>
                    <div class="reply-time">
                        <i class="fas fa-clock me-1"></i>
                        {{ $lead->created_at ? \Carbon\Carbon::parse($lead->created_at)->diffForHumans() : 'N/A' }}
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        <!-- Footer -->
        <div class="text-center small py-4 border-top mt-4" style="border-color: rgba(255,255,255,0.1) !important; color: #94a3b8;">
            <i class="fas fa-graduation-cap me-1"></i>
            Algerian School Support &copy; {{ date('Y') }} &mdash; Powered by
            <span style="color: #60a5fa;">CrewAI</span> &amp; <span style="color: #facc15;">Gemini 3.6 Flash</span>
        </div>
    </div>

    <!-- Lead Detail Modal -->
    <div class="modal fade modal-dark" id="leadModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-circle me-2"></i>Lead Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dt>Student Name</dt><dd id="m-name">-</dd>
                            <dt>Phone Number</dt><dd id="m-phone" class="phone-text">-</dd>
                            <dt>Branch / Level</dt><dd id="m-branch">-</dd>
                        </div>
                        <div class="col-md-6">
                            <dt>Lead Score</dt><dd id="m-score">-</dd>
                            <dt>Level</dt><dd id="m-level">-</dd>
                            <dt>Stream</dt><dd id="m-filiere">-</dd>
                        </div>
                    </div>
                    <hr style="border-color: var(--border-color);">
                    <dt>Subject</dt><dd id="m-subject">-</dd>
                    <dt>AI Reply</dt>
                    <dd id="m-reply" style="white-space:pre-wrap; font-size:0.88rem; color: var(--text-muted); line-height:1.6;">-</dd>
                    <dt>Date</dt><dd id="m-date">-</dd>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-custom" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showLead(data) {
            document.getElementById('m-name').textContent = data.student_name || data.name || '-';
            document.getElementById('m-phone').textContent = data.phone_number || data.phone || '-';
            document.getElementById('m-branch').textContent = data.branch_or_level || data.level || '-';
            document.getElementById('m-level').textContent = data.level || '-';
            document.getElementById('m-filiere').textContent = data.filiere || '-';
            document.getElementById('m-subject').textContent = data.subject || '-';
            var score = (data.lead_score || 'COLD').toUpperCase();
            var scoreHtml = score === 'HOT'
                ? '<span class="badge-hot"><i class="fas fa-fire me-1"></i>HOT</span>'
                : score === 'WARM'
                ? '<span class="badge-warm"><i class="fas fa-thermometer-half me-1"></i>WARM</span>'
                : '<span class="badge-cold"><i class="fas fa-snowflake me-1"></i>COLD</span>';
            document.getElementById('m-score').innerHTML = scoreHtml;
            document.getElementById('m-reply').textContent = data.ai_reply || '-';
            document.getElementById('m-date').textContent = data.created_at || '-';
            new bootstrap.Modal(document.getElementById('leadModal')).show();
        }
    </script>
</body>
</html>
