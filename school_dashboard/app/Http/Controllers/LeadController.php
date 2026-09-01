<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LeadController extends Controller
{
    /**
     * Show the AI leads dashboard with filters, pagination, and export.
     */
    public function index(Request $request)
    {
        $query = Lead::query();

        // Text search (search across new + legacy columns)
        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('student_name', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('branch_or_level', 'like', "%{$search}%")
                  ->orWhere('level', 'like', "%{$search}%")
                  ->orWhere('filiere', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        // Level filter (BEM / BAC)
        if ($level = $request->get('level')) {
            $query->where('level', 'like', "%{$level}%");
        }

        // Lead score filter (HOT / WARM / COLD)
        if ($score = $request->get('score')) {
            $query->where('lead_score', strtoupper($score));
        }

        // Date range filter
        if ($from = $request->get('from')) {
            $query->where('created_at', '>=', $from . ' 00:00:00');
        }
        if ($to = $request->get('to')) {
            $query->where('created_at', '<=', $to . ' 23:59:59');
        }

        // Sorting
        $sort = $request->get('sort', 'created_at');
        $dir = $request->get('dir', 'desc');
        $allowed = ['created_at', 'student_name', 'name', 'level', 'phone_number', 'phone', 'lead_score'];
        if (!in_array($sort, $allowed)) $sort = 'created_at';
        $dir = $dir === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $dir);

        // Pagination
        $perPage = min((int) $request->get('per_page', 15), 100);
        $leads = $query->paginate($perPage)->withQueryString();

        // Aggregates (unfiltered totals)
        $total = Lead::count();
        $hot = Lead::where('lead_score', 'HOT')->count();
        $warm = Lead::where('lead_score', 'WARM')->count();
        $cold = Lead::where('lead_score', 'COLD')->count();

        // Unique levels for filter dropdown
        $levels = Lead::whereNotNull('level')->where('level', '!=', '')
                     ->distinct()->pluck('level')->sort()->values();

        return view('dashboard.leads', compact(
            'leads', 'total', 'hot', 'warm', 'cold', 'levels'
        ));
    }

    /**
     * Show a single lead detail.
     */
    public function show($id)
    {
        $lead = Lead::findOrFail($id);
        return view('dashboard.lead_detail', compact('lead'));
    }

    /**
     * Export leads as CSV.
     */
    public function export(Request $request)
    {
        $query = Lead::query();

        // Apply same filters
        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('student_name', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('branch_or_level', 'like', "%{$search}%")
                  ->orWhere('level', 'like', "%{$search}%")
                  ->orWhere('filiere', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }
        if ($level = $request->get('level')) {
            $query->where('level', 'like', "%{$level}%");
        }
        if ($score = $request->get('score')) {
            $query->where('lead_score', strtoupper($score));
        }
        if ($from = $request->get('from')) {
            $query->where('created_at', '>=', $from . ' 00:00:00');
        }
        if ($to = $request->get('to')) {
            $query->where('created_at', '<=', $to . ' 23:59:59');
        }

        $leads = $query->orderBy('created_at', 'desc')->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="leads_' . date('Y-m-d_His') . '.csv"',
        ];

        $callback = function () use ($leads) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Student Name', 'Phone Number', 'Branch/Level', 'Score', 'Level', 'Stream', 'Subject', 'AI Reply', 'Created At']);

            foreach ($leads as $lead) {
                fputcsv($file, [
                    $lead->id,
                    $lead->student_name ?? $lead->name ?? '',
                    $lead->phone_number ?? $lead->phone ?? '',
                    $lead->branch_or_level ?? '',
                    $lead->lead_score ?? 'COLD',
                    $lead->level ?? '',
                    $lead->filiere ?? '',
                    $lead->subject ?? '',
                    $lead->ai_reply ?? '',
                    $lead->created_at ?? '',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
