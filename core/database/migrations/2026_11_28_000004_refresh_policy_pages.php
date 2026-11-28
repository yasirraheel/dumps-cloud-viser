<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $pages = [
            'privacy-policy' => [
                'title' => 'Privacy Policy',
                'details' => <<<'HTML'
<div class="mb-5">
    <h5 class="mb-3">What this policy covers</h5>
    <p>This policy explains how Dumps.Cloud handles information when you create an account, practise in an exam bank, subscribe to a plan, contact support, or use any other part of the website.</p>
</div>

<div class="mb-5">
    <h5 class="mb-3">Information connected to your account</h5>
    <p>Depending on how you use the website, we may store your name, email address, phone number, login and verification details, selected plan, subscription status, and support conversations.</p>
    <p>When you practise, we store session details such as the exam bank, allowed questions, answers, marked questions, score, result, and exam history so you can continue or review your progress.</p>
</div>

<div class="mb-5">
    <h5 class="mb-3">How we use this information</h5>
    <ul>
        <li>To create and secure your account.</li>
        <li>To give you the categories, exam banks, questions, and features included in your plan.</li>
        <li>To save attempts, results, answer review, certificates, and progress history.</li>
        <li>To process subscriptions and show the correct access status.</li>
        <li>To answer support requests, prevent misuse, and improve the practice experience.</li>
    </ul>
</div>

<div class="mb-5">
    <h5 class="mb-3">Payments and service providers</h5>
    <p>Payments may be handled by the payment provider selected at checkout. We use trusted providers for hosting, email, security, captcha, analytics, or other services only where needed to operate Dumps.Cloud. We do not sell your personal information.</p>
</div>

<div class="mb-5">
    <h5 class="mb-3">Your choices</h5>
    <p>You can update available account details from your profile, stop optional marketing messages, or contact us about access, corrections, or deletion requests. Some information may need to be retained for account security, payment records, dispute handling, or legal requirements.</p>
    <p>For help, visit our <a href="/contact">Contact page</a>.</p>
</div>

<div>
    <h5 class="mb-3">Updates to this policy</h5>
    <p>We may update this policy when the website, plans, payment methods, or data practices change. The latest version will be published on this page.</p>
</div>
HTML,
            ],
            'terms-of-service' => [
                'title' => 'Terms of Service',
                'details' => <<<'HTML'
<div class="mb-5">
    <h5 class="mb-3">Using Dumps.Cloud</h5>
    <p>Dumps.Cloud is a certification exam practice platform. It provides question banks, timed sessions, answer review, progress tracking, exam history, and certificates where those features are enabled for your account or plan.</p>
    <p>Dumps.Cloud is not the certification provider and does not guarantee that a user will pass an official certification exam.</p>
</div>

<div class="mb-5">
    <h5 class="mb-3">Account responsibilities</h5>
    <ul>
        <li>Provide accurate account information and keep your login details private.</li>
        <li>Use only your own account and do not share paid access with other people.</li>
        <li>Tell us promptly if you notice suspicious access or an account problem.</li>
        <li>Do not scrape, copy, resell, automate, attack, or interfere with the website or its question banks.</li>
    </ul>
</div>

<div class="mb-5">
    <h5 class="mb-3">Plans and access limits</h5>
    <p>Each plan controls the categories, exam banks, questions, simulation mode, explanations, and number of attempts available to the user. Locked content is not included until the user moves to a plan that allows it. We may update plan features, prices, or availability before a new subscription or renewal.</p>
    <p>See the current options on the <a href="/pricing">Pricing page</a> and available practice banks on the <a href="/exams">Exams page</a>.</p>
</div>

<div class="mb-5">
    <h5 class="mb-3">Sessions, results, and certificates</h5>
    <p>Scores are calculated from the questions allowed in that session. Refreshing a page, leaving a session incomplete, or reaching a plan limit may affect the session state. Results, history, and certificate availability are based on the records saved by the platform.</p>
</div>

<div class="mb-5">
    <h5 class="mb-3">Payments, cancellation, and misuse</h5>
    <p>Subscription charges and any cancellation or refund conditions are shown during checkout or in the applicable plan details. We may suspend or close access when an account is used for fraud, credential sharing, abusive behavior, payment misuse, or attempts to bypass plan restrictions.</p>
</div>

<div>
    <h5 class="mb-3">Changes and support</h5>
    <p>We may change the website, question banks, plans, or these terms as the service develops. Continued use after an update means the revised terms apply. If something is unclear, please use the <a href="/contact">Contact page</a>.</p>
</div>
HTML,
            ],
            'security-policy' => [
                'title' => 'Security Policy',
                'details' => <<<'HTML'
<div class="mb-5">
    <h5 class="mb-3">Our security approach</h5>
    <p>Dumps.Cloud protects the account, subscription, exam session, result, and support data needed to run the practice platform. We use application access controls, server protections, secure session handling, and monitoring appropriate to the service.</p>
</div>

<div class="mb-5">
    <h5 class="mb-3">Account and session protection</h5>
    <ul>
        <li>Use a strong, private password and never share it.</li>
        <li>Keep your email and phone verification details up to date.</li>
        <li>Exam sessions and saved answers are linked to the signed-in account.</li>
        <li>Plan checks are applied before restricted banks, questions, or features are opened.</li>
        <li>Support and security events may be logged to investigate abuse or account issues.</li>
    </ul>
</div>

<div class="mb-5">
    <h5 class="mb-3">Payments and third parties</h5>
    <p>Where a payment gateway is used, payment details are handled by the selected provider. We limit the information exchanged with service providers to what is needed for payments, email, captcha, hosting, analytics, or security operations.</p>
</div>

<div class="mb-5">
    <h5 class="mb-3">Reporting a security concern</h5>
    <p>If you find a suspected account, payment, content-access, or security issue, do not post details publicly. Contact us with the affected page, account email, time of the issue, and safe reproduction steps through the <a href="/contact">Contact page</a>.</p>
</div>

<div>
    <h5 class="mb-3">Availability and updates</h5>
    <p>No internet service can promise perfect security or uninterrupted availability. We will investigate reported issues and may update this policy when our controls or platform features change.</p>
</div>
HTML,
            ],
        ];

        foreach ($pages as $slug => $page) {
            DB::table('frontends')
                ->where('data_keys', 'policy_pages.element')
                ->where('slug', $slug)
                ->update([
                    'data_values' => json_encode($page, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
        }

        DB::table('frontends')
            ->where('data_keys', 'cookie.data')
            ->update([
                'data_values' => json_encode([
                    'short_desc' => 'Dumps.Cloud uses essential cookies for secure sessions, sign-in, preferences, and the basic operation of exam practice.',
                    'description' => <<<'HTML'
<div class="mb-5">
    <h5 class="mb-3">How Dumps.Cloud uses cookies</h5>
    <p>Cookies are small files stored by your browser. Dumps.Cloud uses them to keep you signed in, protect forms, remember preferences, and make pages work reliably during exam practice.</p>
</div>

<div class="mb-5">
    <h5 class="mb-3">Essential cookies</h5>
    <p>These cookies support login sessions, CSRF protection, language or display preferences, cookie consent, and other functions required for account and exam pages. The website may not work correctly if they are blocked.</p>
</div>

<div class="mb-5">
    <h5 class="mb-3">Optional measurement cookies</h5>
    <p>If optional analytics or performance tools are enabled, they may use cookies to show us which pages work well and where the experience can improve. They are not used to read your exam answers.</p>
</div>

<div class="mb-5">
    <h5 class="mb-3">Managing cookies</h5>
    <p>You can remove or block cookies from your browser settings. Blocking essential cookies may sign you out or prevent account, support, plan, and exam features from working as expected.</p>
</div>

<div>
    <h5 class="mb-3">Policy updates</h5>
    <p>We may update this page when we add or change website features that use cookies. The latest version will remain available here.</p>
</div>
HTML,
                    'status' => 1,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Policy copy is editable content and is intentionally not restored on rollback.
    }
};
