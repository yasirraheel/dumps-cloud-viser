<?php

namespace Database\Seeders;

use App\Constants\Status;
use App\Models\Category;
use App\Models\Exam;
use App\Models\Question;
use Illuminate\Database\Seeder;

class DumpCloudSampleExamSeeder extends Seeder
{
    public function run(): void
    {
        $ipsecExplanation = <<<'HTML'
<div class="explanation-rich-content">
  <div class="expl-card concept-card">
    <div class="section-title-concept">📌 Core Concept: IPsec Modes (RFC 4301)</div>
    <p>IPsec operates in two distinct operational modes: <strong>Tunnel mode</strong> and <strong>Transport mode</strong>. The selection determines what portion of the original IP packet is encrypted and whether a new IP header is appended.</p>
  </div>
  <div class="expl-card correct-card">
    <h4 class="section-title-correct">✓ Why the Correct Answer (Option B) is Correct:</h4>
    <ul><li><span class="opt-tag correct">Option B</span> <strong>tunnel</strong>: In Tunnel mode, the <strong>entire original IP packet</strong> (including original IP header and payload) is encrypted and encapsulated inside a brand new IP header. This mode is the standard for <strong>site-to-site VPNs</strong> because it securely carries private internal subnets (RFC 1918) across the public Internet.</li></ul>
  </div>
  <div class="expl-card incorrect-card">
    <h4 class="section-title-incorrect">✗ Why Other Options are Incorrect:</h4>
    <ul>
      <li><span class="opt-tag wrong">Option A</span> <strong>aggressive</strong>: Aggressive mode is an IKE Phase 1 negotiation mode (using 3 packets instead of Main mode's 6), not an IPsec packet encapsulation mode.</li>
      <li><span class="opt-tag wrong">Option C</span> <strong>transport</strong>: Transport mode encrypts only the Layer 4+ payload while leaving the original IP header unencrypted and visible. It is used for end-to-end host-to-host communications, not site-to-site gateway tunnels.</li>
      <li><span class="opt-tag wrong">Option D</span> <strong>main</strong>: Main mode is an IKE Phase 1 exchange mode designed to protect peer identities, not an IPsec data transfer encapsulation mode.</li>
    </ul>
  </div>
  <div class="expl-card tip-card"><h4 class="section-title-tip">💡 CCNA Exam Tip:</h4><p><strong>Tunnel Mode</strong> = Entire packet encrypted + New IP header added (Site-to-Site VPNs).<br/><strong>Transport Mode</strong> = Payload only encrypted + Original IP header preserved (Host-to-Host).</p></div>
</div>
HTML;

        $mfaExplanation = <<<'HTML'
<div class="explanation-rich-content">
  <div class="expl-card concept-card">
    <div class="section-title-concept">📌 Core Concept: Multi-Factor Authentication (MFA) Categories</div>
    <p>MFA requires authentication credentials from at least two distinct factor categories: Knowledge (something you know), Possession (something you have), and Inherence (something you are).</p>
  </div>
  <div class="expl-card correct-card">
    <h4 class="section-title-correct">✓ Why the Correct Answers (Options A &amp; E) are Correct:</h4>
    <ul>
      <li><span class="opt-tag correct">Option A</span> <strong>Unique user knowledge</strong>: Falls into the <em>"Something you know"</em> category (e.g., password, PIN, passphrase).</li>
      <li><span class="opt-tag correct">Option E</span> <strong>Soft tokens</strong>: Falls into the <em>"Something you have"</em> category (e.g., authenticator app generating time-based one-time passwords - TOTP). Combining A and E satisfies multi-factor authentication.</li>
    </ul>
  </div>
  <div class="expl-card incorrect-card">
    <h4 class="section-title-incorrect">✗ Why Other Options are Incorrect:</h4>
    <ul>
      <li><span class="opt-tag wrong">Option B</span> <strong>Passwords that expire</strong>: A password policy, not a separate authentication factor.</li>
      <li><span class="opt-tag wrong">Option C</span> <strong>Single sign-on</strong>: An access federation mechanism, not an MFA factor.</li>
      <li><span class="opt-tag wrong">Option D</span> <strong>Shared password repository</strong>: Manages stored passwords, remaining in the single knowledge category.</li>
    </ul>
  </div>
  <div class="expl-card tip-card"><h4 class="section-title-tip">💡 CCNA Exam Tip:</h4><p>MFA Factors: <strong>Something you know</strong> (password) + <strong>Something you have</strong> (smart card/phone) + <strong>Something you are</strong> (biometrics).</p></div>
</div>
HTML;

        $category = Category::updateOrCreate(
            ['slug' => 'cisco-ccna-200-301'],
            ['name' => 'Cisco CCNA 200-301', 'status' => Status::ENABLE]
        );

        $exam = Exam::updateOrCreate(
            ['slug' => 'ccna-question-types-sample'],
            [
                'category_id' => $category->id,
                'title' => 'CCNA Question Types — Sample',
                'description' => 'A small admin demo containing single-choice, multiple-selection, and drag-and-drop questions.',
                'instructions' => 'Review each question carefully. Multiple-selection questions may have more than one correct answer.',
                'exam_type' => 'practice',
                'duration' => 30,
                'question_quantity' => 3,
                'pass_percentage' => 70,
                'subjects' => ['Routing', 'Security', 'Switching'],
                'randomize_questions' => false,
                'randomize_options' => false,
                'show_answers' => true,
                'start_at' => now(),
                'result_published_at' => now()->addYears(10),
                'status' => Status::ENABLE,
            ]
        );

        $this->singleChoice($exam, [
            'title' => 'Which IPsec mode provides encapsulation and encryption of the entire original IP packet on a site-to-site VPN? (Choose one answer)',
            'options' => ['A. aggressive', 'B. tunnel', 'C. transport', 'D. main'],
            'correct' => [1],
            'explanation' => $ipsecExplanation,
            'original_source_image' => 'original_sources/1.webp',
        ], 1);

        $this->singleChoice($exam, [
            'title' => 'What are two examples of multifactor authentication? (Choose two answers)',
            'options' => ['A. unique user knowledge', 'B. passwords that expire', 'C. single sign-on', 'D. shared password repository', 'E. soft tokens'],
            'correct' => [0, 4],
            'explanation' => $mfaExplanation,
            'original_source_image' => 'original_sources/109.webp',
        ], 2, 'multiple_selection');

        $this->dragDrop($exam, [
            'title' => 'Drag and drop the attack mitigation techniques from the left onto the types of attack that they mitigate on the right.',
            'data' => [
                'items' => [
                    'configure the DHCP snooping feature and DAI',
                    'disable the Dynamic Trunking Protocol (DTP)',
                    'configure the native VLAN with a dedicated nondefault VLAN ID',
                ],
                'targets' => [
                    'Man-in-the-Middle / DHCP Spoofing Attack',
                    'Switch-Spoofing VLAN-Hopping Attack',
                    '802.1Q Double-Tagging VLAN-Hopping Attack',
                ],
                'correctMatches' => [
                    'Man-in-the-Middle / DHCP Spoofing Attack' => 'configure the DHCP snooping feature and DAI',
                    'Switch-Spoofing VLAN-Hopping Attack' => 'disable the Dynamic Trunking Protocol (DTP)',
                    '802.1Q Double-Tagging VLAN-Hopping Attack' => 'configure the native VLAN with a dedicated nondefault VLAN ID',
                ],
            ],
            'explanation' => '<p>DHCP snooping and DAI mitigate DHCP spoofing, disabling DTP prevents switch spoofing, and using a dedicated native VLAN prevents double-tagging VLAN hopping.</p>',
        ], 3);
    }

    private function singleChoice(Exam $exam, array $data, int $order, string $type = 'single_choice'): void
    {
        $question = Question::updateOrCreate(
            ['exam_id' => $exam->id, 'title' => $data['title']],
            [
                'question_type' => $type,
                'explanation' => $data['explanation'],
                'original_source_image' => $data['original_source_image'] ?? null,
                'answer_data' => ['correct_indices' => $data['correct']],
                'sort_order' => $order,
                'status' => Status::ENABLE,
            ]
        );

        $question->options()->delete();
        foreach ($data['options'] as $option) {
            $question->options()->create(['title' => $option, 'status' => Status::ENABLE]);
        }
        $optionIds = $question->options()->orderBy('id')->pluck('id')->values();
        $correctIds = collect($data['correct'])->map(fn ($index) => $optionIds->get($index))->filter()->values()->all();
        $question->answer_data = ['correct_indices' => $data['correct'], 'correct_option_ids' => $correctIds];
        $question->result_option_id = $correctIds[0] ?? null;
        $question->save();
    }

    private function dragDrop(Exam $exam, array $data, int $order): void
    {
        Question::updateOrCreate(
            ['exam_id' => $exam->id, 'title' => $data['title']],
            [
                'question_type' => 'drag_drop',
                'explanation' => $data['explanation'],
                'answer_data' => $data['data'],
                'sort_order' => $order,
                'status' => Status::ENABLE,
                'result_option_id' => null,
            ]
        );
    }
}
