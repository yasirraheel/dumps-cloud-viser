<?php

namespace App\Http\Controllers\User;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\DeviceToken;
use App\Models\Plan;
use App\Models\ReferralSetting;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller {
    public function home() {
        return to_route('home');
    }

    public function depositHistory(Request $request) {
        $pageTitle = 'Payment Log';
        $deposits  = auth()->user()->deposits()->searchable(['trx'])->with(['gateway'])->orderBy('id', 'desc')->paginate(getPaginate());
        return view('Template::user.deposit_history', compact('pageTitle', 'deposits'));
    }

    public function userData() {
        $user = auth()->user();

        if ($user->profile_complete == Status::YES) {
            return to_route('user.home');
        }

        $pageTitle  = 'User Data';
        $info       = json_decode(json_encode(getIpInfo()), true);
        $mobileCode = isset($info['code']) ? implode(',', $info['code']) : '';
        $countries  = json_decode(file_get_contents(resource_path('views/partials/country.json')));

        return view('Template::user.user_data', compact('pageTitle', 'user', 'countries', 'mobileCode'));
    }

    public function userDataSubmit(Request $request) {

        $user = auth()->user();

        if ($user->profile_complete == Status::YES) {
            return to_route('user.home');
        }

        $countryData  = (array) json_decode(file_get_contents(resource_path('views/partials/country.json')));
        $countryCodes = implode(',', array_keys($countryData));
        $mobileCodes  = implode(',', array_column($countryData, 'dial_code'));
        $countries    = implode(',', array_column($countryData, 'country'));

        $request->validate([
            'country_code' => 'required|in:' . $countryCodes,
            'country'      => 'required|in:' . $countries,
            'mobile_code'  => 'required|in:' . $mobileCodes,
            'username'     => 'required|unique:users|min:6',
            'mobile'       => ['required', 'regex:/^([0-9]*)$/', Rule::unique('users')->where('dial_code', $request->mobile_code)],
        ]);

        if (preg_match("/[^a-z0-9_]/", trim($request->username))) {
            $notify[] = ['info', 'Username can contain only small letters, numbers and underscore.'];
            $notify[] = ['error', 'No special character, space or capital letters in username.'];
            return back()->withNotify($notify)->withInput($request->all());
        }

        $user->country_code = $request->country_code;
        $user->mobile       = $request->mobile;
        $user->username     = $request->username;

        $user->address      = $request->address;
        $user->city         = $request->city;
        $user->state        = $request->state;
        $user->zip          = $request->zip;
        $user->country_name = isset($request->country) ? $request->country : '';
        $user->dial_code    = $request->mobile_code;

        $user->profile_complete = Status::YES;
        $user->save();

        return to_route('user.home');
    }

    public function addDeviceToken(Request $request) {

        $validator = Validator::make($request->all(), [
            'token' => 'required',
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'errors' => $validator->errors()->all()];
        }

        $deviceToken = DeviceToken::where('token', $request->token)->first();

        if ($deviceToken) {
            return ['success' => true, 'message' => 'Already exists'];
        }

        $deviceToken          = new DeviceToken();
        $deviceToken->user_id = auth()->user()->id;
        $deviceToken->token   = $request->token;
        $deviceToken->is_app  = Status::NO;
        $deviceToken->save();

        return ['success' => true, 'message' => 'Token saved successfully'];
    }

    public function downloadAttachment($fileHash) {
        $filePath  = decrypt($fileHash);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $title     = slug(gs('site_name')) . '- attachments.' . $extension;
        try {
            $mimetype = mime_content_type($filePath);
        } catch (\Exception $e) {
            $notify[] = ['error', 'File does not exists'];
            return back()->withNotify($notify);
        }
        header('Content-Disposition: attachment; filename="' . $title);
        header("Content-Type: " . $mimetype);
        return readfile($filePath);
    }

    public function subscribePlan($id, $interval = 0) {
        $plan = Plan::active()->findOrFail($id);

        if ($plan->is_free || ((float) $plan->monthly_price <= 0 && (float) $plan->yearly_price <= 0)) {
            $user = auth()->user();
            $user->plan_id = $plan->id;
            $user->exam_limit = (int) ($interval ? $plan->yearly_exam : $plan->monthly_exam);
            $user->expired_date = now()->addYears(10)->format('Y-m-d');
            $user->save();

            return back()->withNotify([['success', 'Free plan activated successfully']]);
        }

        $user           = auth()->user();
        $pendingPayment = $user->deposits()->where('status', Status::PAYMENT_PENDING)->count();
        if ($pendingPayment > 0) {
            $notify[] = ['error', 'Already one payment in pending. Please Wait'];
            return back()->withNotify($notify);
        }

        $days      = $interval ? 365 : 30;
        $price     = $interval ? $plan->yearly_price : $plan->monthly_price;
        $examLimit = $interval ? $plan->yearly_exam : $plan->monthly_exam;
        $type      = $interval ? Status::YEARLY_PLAN : Status::MONTHLY_PLAN;

        $subscription               = new Subscription();
        $subscription->user_id      = auth()->id();
        $subscription->plan_id      = $plan->id;
        $subscription->price        = $price;
        $subscription->expired_date = now()->addDays($days);
        $subscription->exam_limit   = $examLimit;
        $subscription->plan_type    = $type;
        $subscription->status       = Status::PAYMENT_INITIATE;
        $subscription->save();

        session(['subscription_id' => $subscription->id]);
        return redirect()->route('user.deposit.index');
    }





    public function referrals() {
        $pageTitle = 'Referrals';
        $user      = User::where('id', auth()->id())->with('allReferrals')->firstOrFail();
        $maxLevel  = ReferralSetting::max('level');
        return view('Template::user.referrals', compact('pageTitle', 'user', 'maxLevel'));
    }

    public function transactions() {
        $pageTitle    = 'Transactions';
        $remarks      = Transaction::distinct('remark')->orderBy('remark')->get('remark');
        $transactions = Transaction::where('user_id', auth()->id())->searchable(['trx'])->filter(['trx_type', 'remark'])->orderBy('id', 'desc')->paginate(getPaginate());
        return view('Template::user.transactions', compact('pageTitle', 'transactions', 'remarks'));
    }

    public function applyCoupon(Request $request) {
        $rules = [
            'coupon_code'     => 'required|string|max:40',
            'subscription_id' => 'required|integer',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->all(),
            ]);
        }

        $couponCode = $request->coupon_code;

        $coupon = Coupon::where('code', $couponCode)->where('status', Status::YES)->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => trans('Coupon not found yet!'),
            ]);
        }

        if (!($coupon->start_date <= now() && $coupon->end_date >= now())) {
            return response()->json([
                'success' => false,
                'message' => trans('This coupon has been expired'),
            ]);
        }

        $subscription = Subscription::where('user_id', auth()->id())->where('id', $request->subscription_id)->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => trans('Subscription not found yet!'),
            ]);
        }

        if ($subscription->price < $coupon->minimum_spend) {
            return response()->json([
                'success' => false,
                'message' => trans('Coupon cannot be applied to this amount'),
            ]);
        }

        $totalUses = Subscription::where('coupon_id', $coupon->id)->count();
        if (!is_null($coupon->usage_limit_per_coupon) && $totalUses >= $coupon->usage_limit_per_coupon) {
            return response()->json([
                'success' => false,
                'message' => trans('This coupon has reached its usage limit'),
            ]);
        }

        $userUses = Subscription::where('coupon_id', $coupon->id)->where('user_id', auth()->id())->count();
        if (!is_null($coupon->usage_limit_per_user) && $userUses >= $coupon->usage_limit_per_user) {
            return response()->json([
                'success' => false,
                'message' => trans('This coupon has reached its user usage limit'),
            ]);
        }

        if ($coupon->discount_type == Status::DISCOUNT_PERCENT) {
            $discount = $subscription->price * $coupon->amount / 100;
        } else {
            $discount = $coupon->amount;
        }

        session()->put('coupon_id', $coupon->id);
        session()->put('coupon', $coupon->code);

        return response()->json([
            'success'  => true,
            'discount' => getAmount($discount),
            'message'  => trans('Coupon applied successfully.'),
        ]);
    }

    public function removeCoupon(Request $request) {
        $rules = [
            'subscription_id' => 'required|integer',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->all(),
            ]);
        }

        $subscription = Subscription::where('user_id', auth()->id())->where('id', $request->subscription_id)->first();
        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => trans('Subscription not found yet!'),
            ]);
        }

        if (!session('coupon_id')) {
            return response()->json([
                'success' => false,
                'message' => trans('Coupon not applied in this subscription'),
            ]);
        }

        session()->forget('coupon_id');
        session()->forget('coupon');

        return response()->json([
            'success' => true,
            'amount'  => $subscription->price,
            'message' => trans('Coupon removed successfully.'),
        ]);
    }

    public function subscriptionLog(Request $request) {
        $pageTitle     = 'Subscription Log';
        $subscriptions = Subscription::where('user_id', auth()->id())->searchable(['plan:name'])->orderBy('id', 'desc')->with(['plan'])->paginate(getPaginate());
        return view('Template::user.subscriptions', compact('pageTitle', 'subscriptions', ));
    }

}
