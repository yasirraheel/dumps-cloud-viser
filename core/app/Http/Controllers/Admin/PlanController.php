<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Exam;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller {
    public function index() {
        $pageTitle = 'Pricing Plan';
        $plans     = Plan::searchable(['name'])->orderBy('id', 'desc')->paginate(getPaginate());
        return view('admin.plan.index', compact('pageTitle', 'plans'));
    }

    public function add($id = 0) {
        if ($id) {
            $pageTitle = 'Update Plan';
            $plan      = Plan::findOrFail($id);
        } else {
            $pageTitle = 'Add Plan';
            $plan      = null;
        }
        $categories = Category::active()
            ->with(['exams' => fn ($query) => $query->active()
                ->withCount(['questions as active_questions_count' => fn ($questionQuery) => $questionQuery->active()])
                ->orderBy('sort_order')->orderBy('id')])
            ->orderBy('name')
            ->get();

        $categories->each(function ($category) {
            $category->exams->each(function ($exam) {
                $exam->available_questions = $exam->activeQuestionCount();
            });
        });

        return view('admin.plan.add', compact('pageTitle', 'plan', 'categories'));
    }

    public function store(Request $request, $id = 0) {
        $request->validate([
            'name'              => 'required|string|max:40|unique:plans,name,' . $id,
            'monthly_price'     => 'required|numeric|min:0',
            'yearly_price'      => 'nullable|numeric|min:0',
            'monthly_exam'      => 'required|integer|min:0',
            'yearly_exam'       => 'nullable|integer|min:0',
            'short_description' => 'required|string|max:255',
            'allowed_categories' => 'nullable|array',
            'allowed_categories.*' => 'integer|exists:categories,id',
            'allowed_exams' => 'nullable|array',
            'allowed_exams.*' => 'integer|exists:exams,id',
            'exam_question_limits' => 'nullable|array',
            'exam_question_limits.*' => 'nullable|integer|min:1',
        ]);

        if ($id) {
            $plan         = Plan::findOrFail($id);
            $notification = 'Plan updated successfully';
        } else {
            $plan         = new Plan();
            $notification = 'Plan added successfully';
        }

        $allowedCategories = array_values(array_unique(array_map('intval', (array) $request->input('allowed_categories', []))));
        $allowedExamRows = Exam::query()
            ->active()
            ->whereIn('id', array_map('intval', (array) $request->input('allowed_exams', [])))
            ->whereIn('category_id', $allowedCategories)
            ->get();
        $allowedExams = [];
        $examQuestionLimits = [];
        $requestedLimits = (array) $request->input('exam_question_limits', []);
        foreach ($allowedExamRows as $exam) {
            $availableQuestions = $exam->activeQuestionCount();
            if ($availableQuestions < 1) {
                continue;
            }

            $requestedLimit = $requestedLimits[(string) $exam->id] ?? $availableQuestions;
            $allowedExams[] = (int) $exam->id;
            $examQuestionLimits[(string) $exam->id] = min($availableQuestions, max(1, (int) $requestedLimit));
        }
        $featureFlags = [
            'answer_explanations' => $request->boolean('feature_answer_explanations'),
            'progress_tracking' => $request->boolean('feature_progress_tracking'),
            'certificate' => $request->boolean('feature_certificate'),
        ];
        $isFree = $request->boolean('is_free');
        $monthlyPrice = (float) $request->input('monthly_price', 0);
        $monthlyExamLimit = (int) $request->input('monthly_exam', 0);

        $plan->name              = $request->name;
        $plan->monthly_price     = $isFree ? 0 : $monthlyPrice;
        $plan->yearly_price      = $isFree ? 0 : $this->annualPrice($monthlyPrice);
        $plan->monthly_exam      = $monthlyExamLimit;
        $plan->yearly_exam       = $monthlyExamLimit * 12;
        $plan->short_description = $request->short_description;
        $plan->allowed_categories = $allowedCategories;
        $plan->allowed_exams = $allowedExams;
        $plan->exam_question_limits = $examQuestionLimits;
        $plan->feature_flags = $featureFlags;
        $plan->simulation_mode = $request->boolean('simulation_mode');
        $plan->is_free = $isFree;
        $plan->save();

        if ($isFree) {
            Plan::where('id', '!=', $plan->id)->update(['is_free' => false]);
        }

        $notify[] = ['success', $notification];
        return back()->withNotify($notify);
    }
    public function status($id) {
        return Plan::changeStatus($id);
    }

    private function annualPrice(float $monthlyPrice): float
    {
        $discount = max(0, min(100, (float) config('billing.annual_discount_percent', 20)));
        return round($monthlyPrice * 12 * (1 - ($discount / 100)), 2);
    }
}
