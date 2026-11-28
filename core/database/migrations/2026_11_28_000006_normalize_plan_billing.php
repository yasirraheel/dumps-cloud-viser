<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $discount = max(0, min(100, (float) config('billing.annual_discount_percent', 20)));
        $plans = DB::table('plans')->whereIn('name', ['Free', 'Starter', 'Professional', 'Complete Access'])->get()->keyBy('name');
        $anchorMonthly = (float) ($plans->get('Starter')?->monthly_price ?? 25);
        $anchorMonthly = $anchorMonthly > 0 ? $anchorMonthly : 25;

        $tiers = [
            'Free' => ['monthly_price' => 0, 'monthly_exam' => 10],
            'Starter' => ['monthly_price' => $anchorMonthly, 'monthly_exam' => 50],
            'Professional' => ['monthly_price' => $anchorMonthly * 2, 'monthly_exam' => 100],
            'Complete Access' => ['monthly_price' => $anchorMonthly * 3, 'monthly_exam' => 200],
        ];

        foreach ($tiers as $name => $tier) {
            if (!isset($plans[$name])) {
                continue;
            }

            $monthlyPrice = round((float) $tier['monthly_price'], 2);
            DB::table('plans')->where('id', $plans[$name]->id)->update([
                'monthly_price' => $monthlyPrice,
                'yearly_price' => round($monthlyPrice * 12 * (1 - ($discount / 100)), 2),
                'monthly_exam' => (int) $tier['monthly_exam'],
                'yearly_exam' => (int) $tier['monthly_exam'] * 12,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Pricing is managed data and is intentionally not reverted automatically.
    }
};
