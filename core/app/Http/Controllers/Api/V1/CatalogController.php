<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Exam;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    use ApiV1Trait;

    public function catalog(Request $request)
    {
        $categories = Category::active()
            ->orderBy('id')
            ->get()
            ->map(fn ($cat) => [
                'id' => (int) $cat->id,
                'name' => (string) $cat->name,
                'slug' => (string) ($cat->slug ?? ''),
                'image_url' => $cat->image ? url(getFilePath('category') . '/' . $cat->image) : null,
            ])
            ->values()
            ->all();

        $query = Exam::active()->running()->with('category');
        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->input('category_id'));
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('category', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        $exams = $query->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function ($exam) use ($request) {
                $data = $this->formatExam($exam, true);
                $plan = $request->user()?->plan;
                $data['plan_access'] = (bool) ($plan?->allowsExam($exam));
                $data['simulation_available'] = (bool) ($plan?->allowsSimulation());
                return $data;
            })
            ->values()
            ->all();

        return $this->ok([
            'categories' => $categories,
            'exams' => $exams,
        ]);
    }

    public function showExam($examId)
    {
        $exam = Exam::active()->running()->with('category')->find($examId);
        if (!$exam) {
            return $this->fail('Exam not found.', 404);
        }
        $data = $this->formatExam($exam, true);
        $plan = request()->user()?->plan;
        $data['plan_access'] = (bool) ($plan?->allowsExam($exam));
        $data['simulation_available'] = (bool) ($plan?->allowsSimulation());
        return $this->ok($data);
    }

    public function bookExam(Request $request, $examId)
    {
        $exam = Exam::active()->running()->find($examId);
        if (!$exam) {
            return $this->fail('Exam not found.', 404);
        }

        $user = $request->user();
        if (!$user->plan_id || !$user->expired_date || $user->expired_date < now()->format('Y-m-d')) {
            return $this->fail('An active subscription plan is required to access exams.', 422);
        }
        if (!$user->plan?->allowsExam($exam)) {
            return $this->fail('This exam bank is not included in your current plan.', 422);
        }
        if ((int) $user->exam_limit <= 0) {
            return $this->fail('You do not have enough exam credits remaining.', 422);
        }

        return $this->ok([
            'exam_id' => (int) $exam->id,
            'is_booked' => true,
            'booking_status' => 'booked',
            'exam_credits_remaining' => (int) $user->exam_limit,
            'message' => 'Exam is ready to start.',
        ]);
    }
}
