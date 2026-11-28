<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamSecurityController extends Controller
{
    public const VIOLATION_EVENTS = [
        'contextmenu',
        'copy',
        'cut',
        'paste',
        'print_attempt',
        'shortcut_attempt',
        'fullscreen_exit',
        'devtools_suspected',
    ];

    public function index(Request $request)
    {
        $pageTitle = 'Exam Security Monitor';
        $settings = gs();
        $events = DB::table('exam_security_events as security_events')
            ->leftJoin('users', 'users.id', '=', 'security_events.user_id')
            ->leftJoin('attend_exams', 'attend_exams.id', '=', 'security_events.attend_exam_id')
            ->leftJoin('exams', 'exams.id', '=', 'attend_exams.exam_id')
            ->select([
                'security_events.*',
                'users.username',
                'users.firstname',
                'users.lastname',
                'users.email',
                'users.status as user_status',
                'exams.title as exam_title',
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . trim($request->input('search')) . '%';
                $query->where(function ($nested) use ($search) {
                    $nested->where('users.username', 'like', $search)
                        ->orWhere('users.email', 'like', $search)
                        ->orWhere('users.firstname', 'like', $search)
                        ->orWhere('users.lastname', 'like', $search);
                });
            })
            ->when($request->filled('event') && in_array($request->input('event'), array_merge(self::VIOLATION_EVENTS, ['visibility_hidden', 'window_blur', 'dragstart']), true), function ($query) use ($request) {
                $query->where('security_events.event', $request->input('event'));
            })
            ->orderByDesc('security_events.id')
            ->paginate(getPaginate())
            ->withQueryString();

        $userIds = $events->getCollection()->pluck('user_id')->filter()->unique()->values();
        $violationCounts = $userIds->isEmpty()
            ? collect()
            : DB::table('exam_security_events')
                ->whereIn('user_id', $userIds)
                ->whereIn('event', self::VIOLATION_EVENTS)
                ->select('user_id', DB::raw('COUNT(*) as total'))
                ->groupBy('user_id')
                ->pluck('total', 'user_id');

        $events->getCollection()->transform(function ($event) use ($violationCounts) {
            $event->violation_count = (int) ($violationCounts[$event->user_id] ?? 0);
            return $event;
        });

        $totalViolations = DB::table('exam_security_events')->whereIn('event', self::VIOLATION_EVENTS)->count();
        $affectedUsers = DB::table('exam_security_events')
            ->whereIn('event', self::VIOLATION_EVENTS)
            ->distinct('user_id')
            ->count('user_id');
        $suspendedUsers = User::where('status', Status::USER_BAN)
            ->where('ban_reason', 'like', 'Automated exam security suspension%')
            ->count();

        $eventOptions = array_merge(self::VIOLATION_EVENTS, ['visibility_hidden', 'window_blur', 'dragstart']);

        return view('admin.exams.security', compact(
            'pageTitle',
            'settings',
            'events',
            'totalViolations',
            'affectedUsers',
            'suspendedUsers',
            'eventOptions'
        ));
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'exam_security_violation_limit' => 'required|integer|min:2|max:100',
        ]);

        $settings = gs();
        $settings->exam_security_enabled = $request->boolean('exam_security_enabled');
        $settings->exam_security_violation_limit = (int) $request->input('exam_security_violation_limit');
        $settings->save();

        $notify[] = ['success', 'Exam security settings updated successfully.'];
        return back()->withNotify($notify);
    }
}
