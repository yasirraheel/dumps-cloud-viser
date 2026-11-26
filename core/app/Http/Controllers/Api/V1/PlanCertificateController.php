<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\AttendExam;
use App\Models\Coupon;
use App\Models\GetCertificateUser;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanCertificateController extends Controller
{
    use ApiV1Trait;

    public function plans()
    {
        $plans = Plan::active()->orderBy('id')->get();
        $maxId = $plans->max('id');

        $data = $plans->map(fn ($plan) => [
            'id' => (int) $plan->id,
            'name' => (string) $plan->name,
            'short_description' => (string) ($plan->short_description ?? ''),
            'monthly_price' => (float) ($plan->monthly_price ?? 0),
            'yearly_price' => (float) ($plan->yearly_price ?? 0),
            'monthly_exams' => (int) ($plan->monthly_exam ?? 0),
            'yearly_exams' => (int) ($plan->yearly_exam ?? 0),
            'is_popular' => (int) $plan->id === (int) $maxId,
        ])->values()->all();

        return $this->ok($data);
    }

    public function validateCoupon(Request $request)
    {
        $code = trim((string) $request->input('code'));
        if ($code === '') {
            return $this->fail('Please enter a coupon code.', 422);
        }

        $today = now()->format('Y-m-d');
        $coupon = Coupon::where('code', $code)
            ->where('status', Status::ENABLE)
            ->where(function ($q) use ($today) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
            })
            ->first();

        if (!$coupon) {
            return $this->fail('Invalid or expired coupon code.', 422);
        }

        $basePrice = 0.0;
        if ($request->filled('plan_id')) {
            $plan = Plan::active()->find((int) $request->input('plan_id'));
            if ($plan) {
                $cycle = $request->input('billing_cycle', 'monthly') === 'yearly' ? 'yearly' : 'monthly';
                $basePrice = (float) ($cycle === 'yearly' ? $plan->yearly_price : $plan->monthly_price);
            }
        }

        $isFixed = (int) $coupon->discount_type === Status::YES;
        $discountAmount = $isFixed
            ? (float) $coupon->amount
            : round($basePrice * ((float) $coupon->amount / 100), 2);
        $finalAmount = max(0.0, round($basePrice - $discountAmount, 2));
        $discountPercent = $isFixed
            ? ($basePrice > 0 ? (int) round(($discountAmount / $basePrice) * 100) : 0)
            : (int) round((float) $coupon->amount);

        return $this->ok([
            'code' => (string) $coupon->code,
            'discount_percent' => $discountPercent,
            'final_amount' => $finalAmount,
            'message' => 'Coupon applied successfully.',
        ]);
    }

    public function subscribe(Request $request, $planId)
    {
        $plan = Plan::active()->find($planId);
        if (!$plan) {
            return $this->fail('Subscription plan not found.', 404);
        }

        $cycle = $request->input('billing_cycle', 'monthly') === 'yearly' ? 'yearly' : 'monthly';
        $price = (float) ($cycle === 'yearly' ? $plan->yearly_price : $plan->monthly_price);

        if ($price > 0) {
            return $this->fail('Paid plan checkout must be completed via the web payment gateway at https://dumps.cloud/pricing.', 422);
        }

        $user = $request->user();
        $user->plan_id = $plan->id;
        $user->exam_limit = (int) ($cycle === 'yearly' ? $plan->yearly_exam : $plan->monthly_exam);
        $user->expired_date = ($cycle === 'yearly' ? now()->addYear() : now()->addMonth())->format('Y-m-d');
        $user->save();

        return $this->ok($this->formatUserProfile($user), 'Subscription activated.');
    }

    public function certificateForAttempt(Request $request, $attemptId)
    {
        $attempt = AttendExam::where('user_id', $request->user()->id)
            ->where('is_submit', Status::YES)
            ->find($attemptId);

        if (!$attempt) {
            return $this->fail('Submitted exam attempt not found.', 404);
        }

        $cert = GetCertificateUser::where('attend_exam_id', $attempt->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$cert) {
            return $this->fail('Certificate is not available for this attempt.', 404);
        }

        return $this->ok($this->formatCertificate($cert));
    }

    public function verifyCertificate(Request $request)
    {
        $secret = trim((string) $request->query('secret', ''));
        if ($secret === '') {
            return $this->fail('Certificate verification code is required.', 422);
        }

        $cert = GetCertificateUser::where('secret', $secret)->first();
        if (!$cert) {
            return $this->ok(null, 'No certificate found for this verification code.');
        }

        return $this->ok($this->formatCertificate($cert), 'Certificate verified.');
    }
}
