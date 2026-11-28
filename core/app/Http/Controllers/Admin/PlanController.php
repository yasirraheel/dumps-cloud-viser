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
            ->with(['exams' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('id')])
            ->orderBy('name')
            ->get();

        return view('admin.plan.add', compact('pageTitle', 'plan', 'categories'));
    }

    public function store(Request $request, $id = 0) {
        $request->validate([
            'name'              => 'required|string|max:40|unique:plans,name,' . $id,
            'monthly_price'     => 'required|numeric|min:0',
            'yearly_price'      => 'required|numeric|min:0',
            'monthly_exam'      => 'required|integer|min:0',
            'yearly_exam'       => 'required|integer|min:0',
            'short_description' => 'required|string|max:255',
            'allowed_categories' => 'nullable|array',
            'allowed_categories.*' => 'integer|exists:categories,id',
            'allowed_exams' => 'nullable|array',
            'allowed_exams.*' => 'integer|exists:exams,id',
        ]);

        if ($id) {
            $plan         = Plan::findOrFail($id);
            $notification = 'Plan updated successfully';
        } else {
            $plan         = new Plan();
            $notification = 'Plan added successfully';
        }

        $allowedCategories = array_values(array_unique(array_map('intval', (array) $request->input('allowed_categories', []))));
        $allowedExams = Exam::query()
            ->whereIn('id', array_map('intval', (array) $request->input('allowed_exams', [])))
            ->whereIn('category_id', $allowedCategories)
            ->pluck('id')
            ->map(fn ($examId) => (int) $examId)
            ->values()
            ->all();
        $featureFlags = [
            'answer_explanations' => $request->boolean('feature_answer_explanations'),
            'progress_tracking' => $request->boolean('feature_progress_tracking'),
            'certificate' => $request->boolean('feature_certificate'),
        ];
        $isFree = $request->boolean('is_free');

        $plan->name              = $request->name;
        $plan->monthly_price     = $isFree ? 0 : $request->monthly_price;
        $plan->yearly_price      = $isFree ? 0 : $request->yearly_price;
        $plan->monthly_exam      = $request->monthly_exam;
        $plan->yearly_exam       = $request->yearly_exam;
        $plan->short_description = $request->short_description;
        $plan->allowed_categories = $allowedCategories;
        $plan->allowed_exams = $allowedExams;
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
}
