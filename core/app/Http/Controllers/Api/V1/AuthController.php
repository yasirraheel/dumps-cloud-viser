<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    use ApiV1Trait;

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422, $validator->errors()->toArray());
        }

        $login = trim((string) $request->input('username'));
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user = User::where($field, $login)->first();

        if (!$user || !Hash::check((string) $request->input('password'), (string) $user->password)) {
            return $this->fail('Invalid username or password.', 401);
        }

        if ((int) $user->status === Status::USER_BAN) {
            return $this->fail('Your account has been suspended.', 403);
        }

        $token = $user->createToken($request->input('device_name', 'android'))->plainTextToken;

        return $this->ok([
            'token' => $token,
            'user' => $this->formatUserProfile($user),
        ], 'Signed in successfully.');
    }

    public function register(Request $request)
    {
        if (!gs('registration')) {
            return $this->fail('Registration is currently disabled.', 403);
        }

        $validator = Validator::make($request->all(), [
            'firstname' => 'required|string|max:40',
            'lastname' => 'required|string|max:40',
            'username' => 'required|string|alpha_num|min:4|max:40|unique:users,username',
            'email' => 'required|email|max:90|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'device_name' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422, $validator->errors()->toArray());
        }

        $user = new User();
        $user->firstname = trim((string) $request->input('firstname'));
        $user->lastname = trim((string) $request->input('lastname'));
        $user->username = strtolower(trim((string) $request->input('username')));
        $user->email = strtolower(trim((string) $request->input('email')));
        $user->password = Hash::make((string) $request->input('password'));
        $user->status = Status::USER_ACTIVE;
        $user->ev = gs('ev') ? Status::NO : Status::YES;
        $user->sv = gs('sv') ? Status::NO : Status::YES;
        $user->ts = Status::NO;
        $user->tv = Status::YES;
        $user->profile_complete = Status::YES;

        $freePlan = Plan::free();
        if ($freePlan) {
            $user->plan_id = $freePlan->id;
            $user->exam_limit = $freePlan->monthly_exam;
            $user->expired_date = now()->addYears(10)->format('Y-m-d');
        }

        $user->save();

        $token = $user->createToken($request->input('device_name', 'android'))->plainTextToken;

        return $this->ok([
            'token' => $token,
            'user' => $this->formatUserProfile($user),
        ], 'Account created successfully.', 201);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return $this->ok(null, 'Logged out successfully.');
    }

    public function profile(Request $request)
    {
        return $this->ok($this->formatUserProfile($request->user()));
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'firstname' => 'required|string|max:40',
            'lastname' => 'required|string|max:40',
            'email' => 'required|email|max:90|unique:users,email,' . $user->id,
            'mobile' => 'nullable|string|max:40',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422, $validator->errors()->toArray());
        }

        $user->firstname = trim((string) $request->input('firstname'));
        $user->lastname = trim((string) $request->input('lastname'));
        $user->email = strtolower(trim((string) $request->input('email')));
        if ($request->filled('mobile')) {
            $user->mobile = trim((string) $request->input('mobile'));
        }
        $user->save();

        return $this->ok($this->formatUserProfile($user), 'Profile updated successfully.');
    }

    public function saveExamPreferences(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mode' => 'required|in:study,simulation',
            'question_order' => 'required|in:original,random',
            'randomize_options' => 'nullable|boolean',
            'show_score_live' => 'nullable|boolean',
            'include_show_answer' => 'nullable|boolean',
            'inline_feedback' => 'nullable|boolean',
            'timer' => 'required|in:not_timed,ccna_120,90_mins,60_mins,30s_per_q,60s_per_q,90s_per_q',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422, $validator->errors()->toArray());
        }

        $user = $request->user();
        $user->exam_preferences = [
            'mode' => $request->input('mode'),
            'question_order' => $request->input('question_order'),
            'randomize_options' => $request->boolean('randomize_options'),
            'show_score_live' => $request->boolean('show_score_live'),
            'include_show_answer' => $request->boolean('include_show_answer'),
            'inline_feedback' => $request->boolean('inline_feedback'),
            'timer' => $request->input('timer'),
        ];
        $user->save();

        return $this->ok($this->formatUserProfile($user), 'Exam preferences saved.');
    }
}
