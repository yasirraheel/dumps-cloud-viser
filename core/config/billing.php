<?php

return [
    // Annual plans use the equivalent of two free months by default.
    'annual_discount_percent' => (float) env('ANNUAL_PLAN_DISCOUNT_PERCENT', 20),
];
