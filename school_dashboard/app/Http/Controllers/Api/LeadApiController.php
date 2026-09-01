<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * API controller for receiving AI-generated leads from the Gemini/CrewAI
 * engine and storing them in the leads database.
 *
 * All endpoints require a valid `X-API-Key` header.
 */
class LeadApiController extends Controller
{
    /**
     * POST /api/leads
     *
     * Accept a single lead (AgentReply JSON) and store it.
     *
     * Expected JSON body:
     * {
     *   "reply_text": "...",
     *   "extracted_info": {
     *     "student_name": "...",
     *     "phone_number": "+213 ...",
     *     "branch_or_level": "BAC 3AS scientifique",
     *     "lead_score": "HOT",
     *     "level": "BAC 3AS",
     *     "filiere": "scientifique",
     *     "subject": "math"
     *   },
     *   "raw_message": "...",          // optional
     *   "conversation_id": "...",      // optional
     *   "source": "messenger"          // optional, default "api"
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reply_text'              => 'required|string|min:1',
            'extracted_info'          => 'required|array',
            'extracted_info.student_name'     => 'nullable|string|max:255',
            'extracted_info.phone_number'     => 'nullable|string|max:30',
            'extracted_info.branch_or_level'  => 'nullable|string|max:255',
            'extracted_info.lead_score'       => 'nullable|string|in:HOT,WARM,COLD',
            'extracted_info.level'            => 'nullable|string|max:50',
            'extracted_info.filiere'          => 'nullable|string|max:50',
            'extracted_info.subject'          => 'nullable|string|max:100',
            'raw_message'            => 'nullable|string',
            'conversation_id'        => 'nullable|string|max:255',
            'source'                 => 'nullable|string|max:50',
        ]);

        $info = $validated['extracted_info'];

        $lead = Lead::create([
            'id'               => Str::uuid()->toString(),
            'created_at'       => now('UTC')->toIso8601String(),
            'source'           => $validated['source'] ?? 'api',
            'conversation_id'  => $validated['conversation_id'] ?? null,
            'raw_message'      => $validated['raw_message'] ?? null,
            'student_name'     => $info['student_name'] ?? null,
            'phone_number'     => $info['phone_number'] ?? null,
            'branch_or_level'  => $info['branch_or_level'] ?? null,
            'lead_score'       => strtoupper($info['lead_score'] ?? 'COLD'),
            'level'            => $info['level'] ?? null,
            'filiere'          => $info['filiere'] ?? null,
            'subject'          => $info['subject'] ?? null,
            'ai_reply'         => $validated['reply_text'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lead stored successfully.',
            'lead_id' => $lead->id,
            'data'    => [
                'student_name'    => $lead->student_name,
                'phone_number'    => $lead->phone_number,
                'branch_or_level' => $lead->branch_or_level,
                'lead_score'      => $lead->lead_score,
                'created_at'      => $lead->created_at,
            ],
        ], 201);
    }

    /**
     * POST /api/leads/batch
     *
     * Accept an array of leads and store them all.
     *
     * Expected JSON body:
     * {
     *   "leads": [
     *     { "reply_text": "...", "extracted_info": {...}, ... },
     *     ...
     *   ]
     * }
     */
    public function storeBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'leads'                 => 'required|array|min:1|max:50',
            'leads.*.reply_text'    => 'required|string|min:1',
            'leads.*.extracted_info' => 'required|array',
            'leads.*.extracted_info.student_name'     => 'nullable|string|max:255',
            'leads.*.extracted_info.phone_number'     => 'nullable|string|max:30',
            'leads.*.extracted_info.branch_or_level'  => 'nullable|string|max:255',
            'leads.*.extracted_info.lead_score'       => 'nullable|string|in:HOT,WARM,COLD',
            'leads.*.extracted_info.level'            => 'nullable|string|max:50',
            'leads.*.extracted_info.filiere'          => 'nullable|string|max:50',
            'leads.*.extracted_info.subject'          => 'nullable|string|max:100',
            'leads.*.raw_message'      => 'nullable|string',
            'leads.*.conversation_id'  => 'nullable|string|max:255',
            'leads.*.source'           => 'nullable|string|max:50',
        ]);

        $stored = [];

        foreach ($validated['leads'] as $item) {
            $info = $item['extracted_info'];

            $lead = Lead::create([
                'id'               => Str::uuid()->toString(),
                'created_at'       => now('UTC')->toIso8601String(),
                'source'           => $item['source'] ?? 'api',
                'conversation_id'  => $item['conversation_id'] ?? null,
                'raw_message'      => $item['raw_message'] ?? null,
                'student_name'     => $info['student_name'] ?? null,
                'phone_number'     => $info['phone_number'] ?? null,
                'branch_or_level'  => $info['branch_or_level'] ?? null,
                'lead_score'       => strtoupper($info['lead_score'] ?? 'COLD'),
                'level'            => $info['level'] ?? null,
                'filiere'          => $info['filiere'] ?? null,
                'subject'          => $info['subject'] ?? null,
                'ai_reply'         => $item['reply_text'],
            ]);

            $stored[] = [
                'lead_id'      => $lead->id,
                'student_name' => $lead->student_name,
                'lead_score'   => $lead->lead_score,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => count($stored) . ' leads stored successfully.',
            'count'   => count($stored),
            'leads'   => $stored,
        ], 201);
    }

    /**
     * GET /api/leads
     *
     * List leads with optional filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Lead::query();

        if ($score = $request->query('score')) {
            $query->where('lead_score', strtoupper($score));
        }
        if ($level = $request->query('level')) {
            $query->where('level', 'like', "%{$level}%");
        }
        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('student_name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%")
                  ->orWhere('branch_or_level', 'like', "%{$search}%");
            });
        }

        $leads = $query->orderBy('created_at', 'desc')
                       ->limit(100)
                       ->get()
                       ->map(function ($lead) {
                           return [
                               'id'               => $lead->id,
                               'student_name'     => $lead->student_name,
                               'phone_number'     => $lead->phone_number,
                               'branch_or_level'  => $lead->branch_or_level,
                               'lead_score'       => $lead->lead_score,
                               'level'            => $lead->level,
                               'filiere'          => $lead->filiere,
                               'subject'          => $lead->subject,
                               'ai_reply'         => $lead->ai_reply,
                               'source'           => $lead->source,
                               'created_at'       => $lead->created_at,
                           ];
                       });

        return response()->json([
            'success' => true,
            'count'   => $leads->count(),
            'data'    => $leads,
        ]);
    }

    /**
     * GET /api/leads/{id}
     *
     * Get a single lead by ID.
     */
    public function show(string $lead_id): JsonResponse
    {
        $lead = Lead::find($lead_id);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'error'   => 'Lead not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'               => $lead->id,
                'student_name'     => $lead->student_name,
                'phone_number'     => $lead->phone_number,
                'branch_or_level'  => $lead->branch_or_level,
                'lead_score'       => $lead->lead_score,
                'level'            => $lead->level,
                'filiere'          => $lead->filiere,
                'subject'          => $lead->subject,
                'ai_reply'         => $lead->ai_reply,
                'raw_message'      => $lead->raw_message,
                'source'           => $lead->source,
                'conversation_id'  => $lead->conversation_id,
                'created_at'       => $lead->created_at,
            ],
        ]);
    }
}



