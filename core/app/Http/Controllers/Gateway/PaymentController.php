<?php

namespace App\Http\Controllers\Gateway;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Lib\FormProcessor;
use App\Lib\Referral;
use App\Models\AdminNotification;
use App\Models\Coupon;
use App\Models\Deposit;
use App\Models\GatewayCurrency;
use App\Models\ReferralSetting;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller {
    public function deposit() {
        $gatewayCurrency = GatewayCurrency::whereHas('method', function ($gate) {
            $gate->where('status', Status::ENABLE);
        })->with('method')->orderby('name')->get();
        $subscriptionId = session('subscription_id');
        if (!$subscriptionId) {
            $notify[] = ['error', 'Oops! Session invalid'];
            return redirect()->route('user.home')->withNotify($notify);
        }

        $subscription = Subscription::where('status', Status::NO)->where('id', $subscriptionId)->first();
        if (!$subscription) {
            $notify[] = ['error', 'Oops! Subscription not found'];
            return redirect()->route('user.home')->withNotify($notify);
        }
        $amount = $subscription->price;

        $isCoupon = Coupon::where('status', Status::YES)->where('start_date', '<=', now()->format('Y-m-d'))->where('end_date', '>=', now()->format('Y-m-d'))->exists();

        $discount      = 0;
        $afterDiscount = $amount;
        if (session('coupon_id') && gs('show_coupon') && $isCoupon) {
            $coupon = Coupon::where('id', session('coupon_id'))->where('start_date', '<=', now()->format('Y-m-d'))->where('end_date', '>=', now()->format('Y-m-d'))->where('status', Status::YES)->first();
            if ($coupon) {
                if ($coupon->discount_type == Status::DISCOUNT_PERCENT) {
                    $discount = $subscription->price * $coupon->amount / 100;
                } else {
                    $discount = $coupon->amount;
                }

                $afterDiscount = $amount - $discount;
            }
        }

        $pageTitle = 'Payment Methods';
        return view('Template::user.payment.deposit', compact('gatewayCurrency', 'pageTitle', 'amount', 'isCoupon', 'subscription', 'discount', 'afterDiscount'));
    }

    public function depositInsert(Request $request) {
        $request->validate([
            'amount'   => 'required|numeric|gt:0',
            'gateway'  => 'required',
            'currency' => 'required',
        ]);

        $user = auth()->user();

        $subscriptionId = session()->get('subscription_id');
        if (!$subscriptionId) {
            $notify[] = ['error', 'Oops! Session invalid'];
            return back()->withNotify($notify);
        }

        $subscription = Subscription::inactive()->where('id', $subscriptionId)->first();
        if (!$subscription) {
            $notify[] = ['error', 'Oops! Subscription not found'];
            return back()->withNotify($notify);
        }

        $amount = $subscription->price;

        if (session('coupon_id') && gs('show_coupon')) {
            $coupon = Coupon::where('id', session('coupon_id'))->where('start_date', '<=', now()->format('Y-m-d'))->where('end_date', '>=', now()->format('Y-m-d'))->where('status', Status::YES)->first();
            if ($coupon) {
                if ($coupon->discount_type == Status::DISCOUNT_PERCENT) {
                    $discount = $subscription->price * $coupon->amount / 100;
                } else {
                    $discount = $coupon->amount;
                }
                $amount                        = $amount - $discount;
                $subscription->discount_amount = $discount;
                $subscription->save();
            }

        }

        if ($request->gateway == 'wallet') {
            if ($amount > $user->balance) {
                $notify[] = ['error', 'Insufficient balance from your wallet'];
                return back()->withNotify($notify);
            }

            $subscription->status    = Status::PAYMENT_SUCCESS;
            $subscription->coupon_id = session('coupon_id') ?? 0;
            $subscription->save();

            $user->balance -= $amount;
            $user->plan_id      = $subscription->plan_id;
            $user->credit       = $subscription->credit;
            $user->expired_date = $subscription->expired_date;
            $user->save();

            $plan = $subscription->plan;

            $transaction               = new Transaction();
            $transaction->user_id      = $user->id;
            $transaction->amount       = $amount;
            $transaction->post_balance = $user->balance;
            $transaction->trx_type     = '-';
            $transaction->details      = $plan->name . 'plan subscription successfully';
            $transaction->remark       = 'subscription_plan';
            $transaction->trx          = getTrx();
            $transaction->save();

            $plan = $subscription->plan;

            notify($user, 'SUBSCRIBE_PLAN', [
                'plan'     => $plan->name,
                'price'    => showAmount($amount),
                'duration' => $subscription->expired_date,
            ]);

            session()->forget('coupon_id');
            session()->forget('coupon');

            $notify[] = ['success', 'Plan subscribed successfully'];
            return back()->withNotify($notify);

        }

        $gate = GatewayCurrency::whereHas('method', function ($gate) {
            $gate->where('status', Status::ENABLE);
        })->where('method_code', $request->gateway)->where('currency', $request->currency)->first();
        if (!$gate) {
            $notify[] = ['error', 'Invalid gateway'];
            return back()->withNotify($notify);
        }

        if ($gate->min_amount > $amount || $gate->max_amount < $amount) {
            $notify[] = ['error', 'Please follow deposit limit'];
            return back()->withNotify($notify);
        }

        $charge      = $gate->fixed_charge + ($amount * $gate->percent_charge / 100);
        $payable     = $amount + $charge;
        $finalAmount = $payable * $gate->rate;

        $data                  = new Deposit();
        $data->user_id         = $user->id;
        $data->subscription_id = $subscription->id;
        $data->method_code     = $gate->method_code;
        $data->method_currency = strtoupper($gate->currency);
        $data->amount          = $amount;
        $data->charge          = $charge;
        $data->rate            = $gate->rate;
        $data->final_amount    = $finalAmount;
        $data->btc_amount      = 0;
        $data->btc_wallet      = "";
        $data->trx             = getTrx();
        $data->success_url     = route('user.deposit.history');
        $data->failed_url      = route('user.deposit.history');
        $data->save();
        session()->put('Track', $data->trx);
        return to_route('user.deposit.confirm');
    }

    public function depositConfirm() {
        $track   = session()->get('Track');
        $deposit = Deposit::where('trx', $track)->where('status', Status::PAYMENT_INITIATE)->orderBy('id', 'DESC')->with('gateway')->firstOrFail();

        if ($deposit->method_code >= 1000) {
            return to_route('user.deposit.manual.confirm');
        }

        $dirName = $deposit->gateway->alias;
        $new     = __NAMESPACE__ . '\\' . $dirName . '\\ProcessController';

        $data = $new::process($deposit);
        $data = json_decode($data);

        if (isset($data->error)) {
            $notify[] = ['error', $data->message];
            return back()->withNotify($notify);
        }
        if (isset($data->redirect)) {
            return redirect($data->redirect_url);
        }

        // for Stripe V3
        if (isset($data->session)) {
            $deposit->btc_wallet = $data->session->id;
            $deposit->save();
        }

        $pageTitle = 'Payment Confirm';
        return view("Template::$data->view", compact('data', 'pageTitle', 'deposit'));
    }

    public static function userDataUpdate($deposit, $isManual = null) {
        if ($deposit->status == Status::PAYMENT_INITIATE || $deposit->status == Status::PAYMENT_PENDING) {
            $deposit->status = Status::PAYMENT_SUCCESS;
            $deposit->save();

            $existsSubscription = Subscription::where('user_id', $deposit->user_id)->where('status', Status::ENABLE)->first();
            if ($existsSubscription) {
                $existsSubscription->status = Status::DISABLE;
                $existsSubscription->save();
            }

            $subscription         = Subscription::inactive()->where('id', $deposit->subscription_id)->first();
            $subscription->status = Status::PAYMENT_SUCCESS;
            $subscription->save();

            $user = User::find($deposit->user_id);
            $plan = $subscription->plan;

            if ($plan) {
                $user->plan_id      = $subscription->plan_id;
                $user->exam_limit   = $subscription->exam_limit;
                $user->expired_date = $subscription->expired_date;
                $user->save();

                notify($user, 'SUBSCRIBE_PLAN', [
                    'plan'     => $plan->name,
                    'price'    => showAmount($subscription->price),
                    'duration' => $subscription->expired_date,
                ]);
            }

            $methodName = $deposit->methodName();
            if (!$isManual) {
                $adminNotification            = new AdminNotification();
                $adminNotification->user_id   = $user->id;
                $adminNotification->title     = 'Payment successful via ' . $methodName;
                $adminNotification->click_url = urlPath('admin.deposit.successful');
                $adminNotification->save();
            }

            if (gs()->deposit_commission) {
                Referral::levelCommission($user, $deposit->amount, $deposit->trx, 'deposit_commission');
            }

            session()->forget('coupon_id');
            session()->forget('coupon');

            notify($user, $isManual ? 'DEPOSIT_APPROVE' : 'DEPOSIT_COMPLETE', [
                'method_name'     => $methodName,
                'method_currency' => $deposit->method_currency,
                'method_amount'   => showAmount($deposit->final_amount, currencyFormat: false),
                'amount'          => showAmount($deposit->amount, currencyFormat: false),
                'charge'          => showAmount($deposit->charge, currencyFormat: false),
                'rate'            => showAmount($deposit->rate, currencyFormat: false),
                'trx'             => $deposit->trx,
                'post_balance'    => showAmount($user->balance),
            ]);
        }
    }
    public function manualDepositConfirm() {
        $track = session()->get('Track');
        $data  = Deposit::with('gateway')->where('status', Status::PAYMENT_INITIATE)->where('trx', $track)->first();
        abort_if(!$data, 404);
        if ($data->method_code > 999) {
            $pageTitle = 'Confirm Deposit';
            $method    = $data->gatewayCurrency();
            $gateway   = $method->method;
            return view('Template::user.payment.manual', compact('data', 'pageTitle', 'method', 'gateway'));
        }
        abort(404);
    }

    public function manualDepositUpdate(Request $request) {
        $track = session()->get('Track');
        $data  = Deposit::with('gateway')->where('status', Status::PAYMENT_INITIATE)->where('trx', $track)->first();
        abort_if(!$data, 404);
        $gatewayCurrency = $data->gatewayCurrency();
        $gateway         = $gatewayCurrency->method;
        $formData        = $gateway->form->form_data;

        $formProcessor  = new FormProcessor();
        $validationRule = $formProcessor->valueValidation($formData);
        $request->validate($validationRule);
        $userData = $formProcessor->processFormData($request, $formData);

        $data->detail = $userData;
        $data->status = Status::PAYMENT_PENDING;
        $data->save();

        $subscription = Subscription::inactive()->where('id', $data->subscription_id)->first();
        if ($subscription) {
            $subscription->coupon_id = session('coupon_id') ?? 0;
            $subscription->save();

            session()->forget('coupon_id');
            session()->forget('coupon');
        }

        $adminNotification            = new AdminNotification();
        $adminNotification->user_id   = $data->user->id;
        $adminNotification->title     = 'Payment request from ' . $data->user->username;
        $adminNotification->click_url = urlPath('admin.deposit.details', $data->id);
        $adminNotification->save();

        notify($data->user, 'DEPOSIT_REQUEST', [
            'method_name'     => $data->gatewayCurrency()->name,
            'method_currency' => $data->method_currency,
            'method_amount'   => showAmount($data->final_amount, currencyFormat: false),
            'amount'          => showAmount($data->amount, currencyFormat: false),
            'charge'          => showAmount($data->charge, currencyFormat: false),
            'rate'            => showAmount($data->rate, currencyFormat: false),
            'trx'             => $data->trx,
        ]);

        $notify[] = ['success', 'You have payment request has been taken'];
        return to_route('user.deposit.history')->withNotify($notify);
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
