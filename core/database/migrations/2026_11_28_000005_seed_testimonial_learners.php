<?php

use App\Constants\Status;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $freePlan = DB::table('plans')
            ->where('is_free', Status::YES)
            ->where('status', Status::ENABLE)
            ->orderBy('id')
            ->first(['id', 'monthly_exam']);

        DB::table('frontends')
            ->where('data_keys', 'testimonial.element')
            ->orderBy('id')
            ->get(['data_values'])
            ->each(function ($row, $index) use ($now, $freePlan): void {
                $data = json_decode($row->data_values, true);
                $name = trim((string) ($data['name'] ?? ''));
                if ($name === '') {
                    return;
                }

                $slug = Str::slug($name, '_');
                if ($slug === '') {
                    return;
                }

                $username = 'learner_' . $slug;
                $email = 'learner.' . $slug . '@dumps.cloud';
                if (DB::table('users')->where('username', $username)->orWhere('email', $email)->exists()) {
                    return;
                }

                $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [$name];
                $firstname = array_shift($parts) ?: $name;
                $lastname = implode(' ', $parts);
                $mobile = '300' . str_pad((string) ($index + 1), 7, '0', STR_PAD_LEFT);
                while (DB::table('users')->where('mobile', $mobile)->where('dial_code', '+92')->exists()) {
                    $mobile = '300' . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT);
                }

                DB::table('users')->insert([
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'plan_id' => $freePlan?->id ?? 0,
                    'exam_limit' => (int) ($freePlan?->monthly_exam ?? 0),
                    'expired_date' => now()->addYears(10)->format('Y-m-d'),
                    'username' => $username,
                    'email' => $email,
                    'dial_code' => '+92',
                    'mobile' => $mobile,
                    'ref_by' => 0,
                    'balance' => 0,
                    'image' => null,
                    'password' => Hash::make(Str::random(64)),
                    'country_name' => 'Pakistan',
                    'country_code' => 'PK',
                    'city' => 'Bahawalpur',
                    'state' => 'Punjab',
                    'zip' => null,
                    'address' => null,
                    'status' => Status::USER_ACTIVE,
                    'kyc_data' => null,
                    'kyc_rejection_reason' => null,
                    'kv' => Status::VERIFIED,
                    'ev' => Status::VERIFIED,
                    'sv' => Status::VERIFIED,
                    'profile_complete' => Status::YES,
                    'ver_code' => null,
                    'ver_code_send_at' => null,
                    'ts' => Status::DISABLE,
                    'tv' => Status::ENABLE,
                    'tsc' => null,
                    'ban_reason' => null,
                    'remember_token' => null,
                    'provider' => 'testimonial_seed',
                    'provider_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'exam_preferences' => null,
                ]);
            });
    }

    public function down(): void
    {
        // Seeded learner accounts are retained so public learner statistics remain stable.
    }
};
