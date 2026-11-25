<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Exam;
use App\Models\Option;
use App\Models\Question;
use App\Rules\FileTypeValidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ManageExamController extends Controller {
    public function add($id = 0) {

        $pageTitle = "Add Exam";
        $exam      = null;
        if ($id) {
            $pageTitle = "Update Exam";
            $exam      = Exam::findOrFail($id);
        }
        $nextSortOrder = ((int) Exam::active()->max('sort_order')) + 1;
        $categories = Category::active()->get();
        return view('admin.exams.add', compact('pageTitle', 'exam', 'categories', 'nextSortOrder'));
    }

    public function store(Request $request, $id = 0) {
        $imgValidation = $id ? 'nullable' : 'required';
        $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
            'title'       => 'required|string|max:255',
            'sort_order'  => 'required|integer|min:1',
            'pass_percentage' => 'required|integer|between:0,100',
            'image'       => [$imgValidation, 'image', new FileTypeValidate(['jpeg', 'jpg', 'png'])],
        ]);

        if ($id) {
            $exam    = Exam::findOrFail($id);
            $message = 'Exam updated successfully';
        } else {
            $exam    = new Exam();
            $message = 'Exam added successfully';
        }

        if ($request->hasFile('image')) {
            try {
                $old         = $exam->image;
                $exam->image = fileUploader($request->image, getFilePath('exam'), getFileSize('exam'), $old);
            } catch (\Exception $exp) {
                $notify[] = ['error', 'Couldn\'t upload your image'];
                return back()->withNotify($notify);
            }
        }

        $categoryId = (int) $request->category_id;
        $title      = $request->title;
        $passMark   = (int) $request->pass_percentage;
        $sortOrder  = (int) $request->sort_order;

        DB::transaction(function () use ($exam, $id, $categoryId, $title, $passMark, $sortOrder) {
            if ($id) {
                $currentOrder = (int) $exam->sort_order;

                if ($sortOrder < $currentOrder) {
                    DB::table('exams')->where('id', '!=', $exam->id)
                        ->where('sort_order', '>=', $sortOrder)
                        ->where('sort_order', '<', $currentOrder)
                        ->increment('sort_order');
                } elseif ($sortOrder > $currentOrder) {
                    DB::table('exams')->where('id', '!=', $exam->id)
                        ->where('sort_order', '>', $currentOrder)
                        ->where('sort_order', '<=', $sortOrder)
                        ->decrement('sort_order');
                }
            } else {
                DB::table('exams')->where('sort_order', '>=', $sortOrder)->increment('sort_order');
            }

            $exam->category_id     = $categoryId;
            $exam->title           = $title;
            $exam->slug            = slug($title);
            $exam->pass_percentage = $passMark;
            $exam->sort_order      = $sortOrder;

            if (!$id) {
                $category = Category::findOrFail($categoryId);

                // Keep operational settings behind the simplified exam form.
                $exam->description         = null;
                $exam->instructions        = null;
                $exam->exam_type           = 'practice';
                $exam->randomize_questions = false;
                $exam->randomize_options   = false;
                $exam->show_answers        = true;
                $exam->duration            = 30;
                $exam->question_quantity   = 0;
                $exam->subjects            = [$category->name];
                $exam->start_at            = now();
                $exam->result_published_at = now()->addYears(10);
                $exam->status              = Status::ENABLE;
                $exam->result_published    = Status::NO;
            }

            $exam->save();
        });

        $notify[] = ['success', $message];
        return to_route('admin.exams.questions', $exam->id)->withNotify($notify);

    }

    public function index() {
        $pageTitle = "All Exams";
        $exams     = Exam::withCount(['questions as active_questions_count' => fn ($query) => $query->active()])
            ->searchable('title')->orderBy('sort_order')->orderBy('id')->paginate(getPaginate());
        return view('admin.exams.index', compact('pageTitle', 'exams'));
    }
    public function result() {
        $pageTitle = "Declare Results";
        $exams     = Exam::withCount(['questions as active_questions_count' => fn ($query) => $query->active()])
            ->where('result_published_at', '<', now())->where('result_published', Status::NO)
            ->searchable('title')->orderBy('sort_order')->orderBy('id')->paginate(getPaginate());
        return view('admin.exams.index', compact('pageTitle', 'exams'));
    }

    public function status($id) {
        return Exam::changeStatus($id);
    }

    public function delete($id) {
        $exam = Exam::findOrFail($id);
        $exam->questions->each(function ($question) {
            $question->options()->delete();
            $question->delete();
        });
        $exam->delete();
        $notify[] = ['success', 'Exam deleted successfully'];
        return back()->withNotify($notify);
    }

    public function questions($id) {
        $exam      = Exam::findOrFail($id);
        $pageTitle = "All Questions";
        $questions = Question::where('exam_id', $exam->id)->searchable('title')->with(['options', 'result'])->orderBy('sort_order')->orderBy('id')->paginate(getPaginate());
        return view('admin.exams.questions', compact('exam', 'questions', 'pageTitle'));
    }

    public function questionCreate($examId) {
        $exam = Exam::findOrFail($examId);
        $question = null;
        $options = [];
        $correctIndices = [];
        $pageTitle = 'Add New Question';

        return view('admin.exams.question_form', compact('exam', 'question', 'options', 'correctIndices', 'pageTitle'));
    }

    public function questionEdit($examId, $id) {
        $exam = Exam::findOrFail($examId);
        $question = Question::where('exam_id', $exam->id)->with('options')->findOrFail($id);
        $options = $question->options->pluck('title')->all();
        $correctIndices = $question->answer_data['correct_indices'] ?? [];
        $pageTitle = 'Edit Question';

        return view('admin.exams.question_form', compact('exam', 'question', 'options', 'correctIndices', 'pageTitle'));
    }

    public function questionStore(Request $request, $examId, $id = 0) {
        $questionType = $request->input('question_type');
        $rules = [
            'question_no'    => 'nullable|string|max:30',
            'title'          => 'required|string|max:10000',
            'question_type'  => 'required|in:single_choice,multiple_selection,drag_drop',
            'explanation'    => 'nullable|string|max:50000',
            'exhibit_image_upload' => ['nullable', 'image', 'max:20480', new FileTypeValidate(['jpg', 'jpeg', 'png', 'webp'])],
            'original_source_image_upload' => ['nullable', 'image', 'max:20480', new FileTypeValidate(['jpg', 'jpeg', 'png', 'webp'])],
            'cli_snippet'    => 'nullable|string|max:50000',
            'sort_order'     => 'nullable|integer|min:0|max:100000',
        ];

        if (in_array($questionType, ['single_choice', 'multiple_selection'], true)) {
            $rules['options'] = 'required|array|min:2';
            $rules['options.*'] = 'required|string|max:10000';

            if ($questionType === 'single_choice') {
                $rules['correct_option'] = 'required|integer|min:0';
            } else {
                $rules['correct_options'] = 'required|array|min:1';
                $rules['correct_options.*'] = 'required|integer|min:0|distinct';
            }
        } elseif ($questionType === 'drag_drop') {
            $rules['drag_drop_json'] = 'required|json';
        }

        $validator = Validator::make($request->all(), $rules);
        $validator->after(function ($validator) use ($request, $questionType) {
            if (in_array($questionType, ['single_choice', 'multiple_selection'], true)) {
                $options = $request->input('options', []);
                $optionCount = is_array($options) ? count($options) : 0;

                foreach ((array) $options as $index => $option) {
                    if (blank(trim((string) $option))) {
                        $validator->errors()->add("options.$index", 'Each answer option must contain text.');
                    }
                }

                $correctIndices = $questionType === 'single_choice'
                    ? [$request->input('correct_option')]
                    : $request->input('correct_options', []);

                foreach ((array) $correctIndices as $index) {
                    if (!is_numeric($index) || (int) $index < 0 || (int) $index >= $optionCount) {
                        $validator->errors()->add('correct_option', 'Select a correct answer from the available options.');
                        break;
                    }
                }
            }

            if ($questionType === 'drag_drop' && $request->filled('drag_drop_json')) {
                $data = json_decode($request->input('drag_drop_json'), true);
                $items = $data['items'] ?? null;
                $targets = $data['targets'] ?? null;
                $matches = $data['correctMatches'] ?? null;

                if (!is_array($items) || count($items) < 1 || !is_array($targets) || count($targets) < 1 || !is_array($matches)) {
                    $validator->errors()->add('drag_drop_json', 'Drag & drop data needs items, targets, and correctMatches.');
                    return;
                }

                foreach ($items as $item) {
                    if (!is_string($item) || blank(trim($item))) {
                        $validator->errors()->add('drag_drop_json', 'Every draggable item must contain text.');
                        break;
                    }
                }

                foreach ($targets as $target) {
                    if (!is_string($target) || blank(trim($target)) || !isset($matches[$target]) || !in_array($matches[$target], $items, true)) {
                        $validator->errors()->add('drag_drop_json', 'Every target must have a correct match selected from the items.');
                        break;
                    }
                }
            }
        });
        $validator->validate();

        $exam = Exam::findOrFail($examId);
        $question = $id
            ? Question::where('exam_id', $exam->id)->findOrFail($id)
            : new Question();

        $exhibitImage = $question->exhibit_image;
        $originalSourceImage = $question->original_source_image;
        try {
            if ($request->hasFile('exhibit_image_upload')) {
                $exhibitImage = $this->storeQuestionImage($request->file('exhibit_image_upload'));
            }
            if ($request->hasFile('original_source_image_upload')) {
                $originalSourceImage = $this->storeQuestionImage($request->file('original_source_image_upload'));
            }
        } catch (\Throwable $exception) {
            Log::error('Question image upload failed', ['exception' => $exception]);
            $notify[] = ['error', 'Could not upload the selected question image. Please try again.'];
            return back()->withInput()->withNotify($notify);
        }

        $isNewQuestion = !$id;
        DB::transaction(function () use ($request, $exam, $question, $isNewQuestion, $exhibitImage, $originalSourceImage) {
            $question->exam_id = $exam->id;
            $question->question_no = $request->filled('question_no') ? trim($request->input('question_no')) : null;
            $question->title = $request->title;
            $question->question_type = $request->question_type;
            $question->explanation = $request->input('explanation');
            $question->exhibit_image = $exhibitImage;
            $question->original_source_image = $originalSourceImage;
            $question->cli_snippet = $request->input('cli_snippet');
            $question->sort_order = $request->integer('sort_order', 0);
            $question->result_option_id = null;
            $question->answer_data = null;
            $question->save();

            if ($isNewQuestion && blank($question->question_no)) {
                $question->question_no = 'Question #' . $question->id;
                $question->save();
            }

            // Options are managed together with the question so an admin can
            // create or edit a complete question in one save operation.
            $question->options()->delete();
            $options = collect($request->input('options', []))
                ->map(fn ($title) => trim((string) $title))
                ->filter()
                ->values();
            $savedOptions = $options->map(fn ($title) => $question->options()->create(['title' => $title]));

            if ($question->question_type === 'single_choice') {
                $correctIndex = $request->input('correct_option');
                $correct = is_numeric($correctIndex) ? $savedOptions->get((int) $correctIndex) : null;
                $question->result_option_id = $correct?->id;
                $question->answer_data = ['correct_indices' => $correct ? [(int) $correctIndex] : []];
            } elseif ($question->question_type === 'multiple_selection') {
                $indices = collect($request->input('correct_options', []))->map(fn ($i) => (int) $i)->values();
                $correctIds = $indices->map(fn ($index) => $savedOptions->get($index)?->id)->filter()->values()->all();
                $question->result_option_id = $correctIds[0] ?? null;
                $question->answer_data = [
                    'correct_indices' => $indices->all(),
                    'correct_option_ids' => $correctIds,
                ];
            } else {
                $question->answer_data = json_decode($request->input('drag_drop_json', '{}'), true) ?: [];
            }

            $question->save();
        });

        $message = $id ? 'Question updated successfully' : 'Question added successfully';
        $this->syncQuestionQuantity($exam);

        $notify[] = ['success', $message];
        return to_route('admin.exams.questions', $exam->id)->withNotify($notify);
    }

    private function syncQuestionQuantity(Exam $exam): void {
        $exam->question_quantity = $exam->questions()->active()->count();
        $exam->save();
    }

    private function storeQuestionImage($file): string {
        $directory = trim(getFilePath('question'), '/');
        // The local CodeCanyon router serves static files from the website root,
        // which is the parent of the Laravel core directory.
        $filename = fileUploader($file, base_path('../' . $directory));

        return $directory . '/' . $filename;
    }

    public function questionStatus($id) {
        $question = Question::findOrFail($id);
        $response = Question::changeStatus($id);
        $this->syncQuestionQuantity(Exam::findOrFail($question->exam_id));
        return $response;
    }

    public function questionDelete($id) {
        $question = Question::findOrFail($id);
        $exam = Exam::findOrFail($question->exam_id);
        $question->options()->delete();
        $question->delete();
        $this->syncQuestionQuantity($exam);
        $notify[] = ['success', 'Question deleted successfully'];
        return back()->withNotify($notify);
    }

    public function optionStore(Request $request, $id = 0) {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'question_id' => 'required|integer|exists:questions,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->all()]);
        }

        if ($id) {
            $option = Option::where('id', $id)->first();
            if (!$option) {
                return response()->json(['error' => 'Option not found']);
            }
            $notification = 'Option updated successfully';
        } else {
            $option              = new Option();
            $option->question_id = $request->question_id;
            $notification        = 'Option added successfully';
        }

        $option->title = $request->title;
        $option->save();

        $question = $option->question;
        $options  = $question->options()->get();
        return response()->json(['success' => $notification, 'options' => $options]);
    }

    public function optionStatus($id) {
        $option = Option::where('id', $id)->with('question')->first();
        if (!$option) {
            return response()->json(['success' => 'Option not found']);
        }
        if ($option->status == Status::ENABLE) {
            $option->status = Status::DISABLE;
        } else {
            $option->status = Status::ENABLE;
        }
        $option->save();
        $question = $option->question;
        $options  = $question->options()->get();
        return response()->json(['success' => 'Status changed successfully', 'options' => $options]);
    }

    public function questionGenerate(Request $request, $id) {

        $validator = Validator::make($request->all(), [
            'prompt' => 'required|string',
        ]);

        if ($validator->fails()) {
            return responseError('error', $validator->errors()->all());
        }

        $exam = Exam::where('id', $id)->first();
        if (!$exam) {
            return responseError('error', 'Exam not found');
        }

        if (gs('default_engine') == Status::GEMINI_MODEL) {
            $apiToken = gs('gemini_api_key');
            if (!$apiToken) {
                return responseError('missing_api_token', 'API key is missing for the selected engine.');
            }

            $response = $this->geminiApi($request, $exam);

        } else {
            $apiToken = gs('openai_api_key');
            if (!$apiToken) {
                return responseError('missing_api_token', 'API key is missing for the selected engine.');
            }

            $response = $this->openaiApi($request, $exam);
        }

        if (isset($response['status']) && $response['status'] == 'error') {
            return responseError('api_error', $response['message']);
        }

        $questionData = $response['data'];

        if (!$questionData) {
            return responseError('error', 'No question generated');
        }

        if (!$questionData['questions']) {
            return responseError('error', 'No question generated');
        }

        foreach ($questionData['questions'] as $singleQuestion) {
            $question          = new Question();
            $question->exam_id = $exam->id;
            $question->title   = $singleQuestion['question'];
            $question->save();

            foreach ($singleQuestion['options'] as $key => $singleOption) {
                $option              = new Option();
                $option->question_id = $question->id;
                $option->title       = $singleOption;
                $option->save();
                if ($key === $singleQuestion['answer']) {
                    $question->result_option_id = $option->id;
                    $question->save();
                }
            }
        }

        $this->syncQuestionQuantity($exam);

        return responseSuccess('success', 'Question generated successfully');

    }

    private function openaiApi($request, $exam) {
        try {
            $apiKey = gs('openai_api_key');
            $model  = gs('openai_api_model') ?? 'gpt-4o-mini';

            $subjects = implode(',', (array) $exam->subjects);
            $generationQuantity = max((int) $exam->question_quantity, 10);

            $instruction = <<<PROMPT
You are an expert exam question generator AI.

The admin will provide a fully custom instruction text, which must be followed strictly.

Admin Prompt:
{$request->prompt}
-----------------------------------------

Exam Settings:
Total Questions: {$generationQuantity}
Subjects: {$subjects}
Difficulty: hard

-----------------------------------------
Your Task:
1. Read the Admin Prompt carefully.
2. Combine the Admin Prompt with the Subject Instructions to generate all questions.
3. Distribute questions equally across subjects.
4. For each question, provide:
   - subject
   - question
   - options (A, B, C, D)
   - answer (A/B/C/D)

-----------------------------------------
Output Format (STRICT JSON ONLY):

{
  "meta": {
    "total_questions": number,
    "difficulty": "string",
    "subjects": ["string"],
    "distribution": {
      "Subject1": number
    },
    "admin_prompt_used": "string"
  },
  "questions": [
    {
      "subject": "string",
      "question": "string",
      "options": {
        "A": "string",
        "B": "string",
        "C": "string",
        "D": "string"
      },
      "answer": "A"
    }
  ]
}

Rules:
- STRICTLY follow the Admin Prompt.
- Use only the subjects and instructions provided by the admin.
- Questions must be original and exam-ready.
- Output must be PURE JSON — no markdown, no explanation.
- Do not include text outside the JSON.
PROMPT;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])->timeout(300)->post('https://api.openai.com/v1/chat/completions', [
                'model'       => $model,
                'messages'    => [
                    [
                        'role'    => 'user',
                        'content' => $instruction,
                    ],
                ],
                'max_tokens'  => 6000,
                'temperature' => 0.7,
            ]);

            if ($response->failed()) {

                $data = json_decode($response->body(), true);
                return [
                    'status'  => 'error',
                    'message' => 'Error from OpenAI: ' . $data['error']['message'],
                ];
            }

            $content = $response->json();

            $rawText = $content['choices'][0]['message']['content'] ?? '';

            if (!$rawText) {
                return [
                    'status'  => 'error',
                    'message' => 'Empty response from OpenAI.',
                ];
            }

            // Clean accidental code fences
            $cleanText = preg_replace('/^```json|```$/m', '', trim($rawText));
            $cleanText = preg_replace('/^```|```$/m', '', $cleanText);

            $questionData = json_decode($cleanText, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON from OpenAI', ['response' => $rawText]);

                return [
                    'status'  => 'error',
                    'message' => 'Invalid JSON response from OpenAI.',
                ];
            }

            if (!isset($questionData['meta'], $questionData['questions'])) {
                return [
                    'status'  => 'error',
                    'message' => 'Invalid OpenAI response structure.',
                ];
            }

            return [
                'status' => 'success',
                'data'   => $questionData,
            ];

        } catch (\Throwable $th) {
            Log::error('OpenAI API Error', ['error' => $th->getMessage()]);
            return [
                'status'  => 'error',
                'message' => $th->getMessage(),
            ];
        }
    }

    private function geminiApi($request, $exam) {
        try {

            $apiKey   = gs('gemini_api_key');
            $subjects = implode(',', (array) $exam->subjects);
            $generationQuantity = max((int) $exam->question_quantity, 10);

            $instruction = <<<PROMPT
You are an expert exam question generator AI.

The admin will provide a fully custom instruction text, which must be followed strictly.

Admin Prompt:
$request->prompt
-----------------------------------------

Exam Settings:
Total Questions: $generationQuantity
Subjects:  $subjects // ["Bangla", "Math", "General Knowledge", ...]
Difficulty: hard

-----------------------------------------
Your Task:
1. Read the **Admin Prompt** carefully.
2. Combine the Admin Prompt with the Subject Instructions to generate all questions.
3. Distribute the total questions automatically across all subjects:
   - Divide equally.
   - If not divisible, add remaining questions to the last subject.
4. For each question, provide:
   - "subject"
   - "question"
   - "options": { "A": "...", "B": "...", "C": "...", "D": "..." }
   - "answer": one of "A", "B", "C", or "D"

-----------------------------------------
Output Format (STRICT):
Return ONLY valid JSON in the format:

{
  "meta": {
    "total_questions": number,
    "difficulty": "string",
    "subjects": ["string"],
    "distribution": {
      "Subject1": number,
      "Subject2": number
    },
    "admin_prompt_used": "string"
  },
  "questions": [
    {
      "subject": "string",
      "question": "string",
      "options": {
        "A": "string",
        "B": "string",
        "C": "string",
        "D": "string"
      },
      "answer": "A"
    }
  ]
}

-----------------------------------------
Rules:
- STRICTLY follow the Admin Prompt.
- Use only the subjects and instructions provided by the admin.
- Questions must be original and exam-ready.
- Output must be PURE JSON — no markdown, no explanation.
- Do not include text outside the JSON.

PROMPT;

            $geminiResponse = Http::withHeaders([
                'Content-Type'   => 'application/json',
                'x-goog-api-key' => $apiKey,
            ])->timeout(300)->post(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-3-pro-preview:generateContent',
                [
                    'contents'         => [
                        ['parts' => [['text' => $instruction]]],
                    ],
                    'generationConfig' => [
                        'temperature'     => 0.7,
                        'maxOutputTokens' => 80000,
                    ],
                ]
            );

            $content = $geminiResponse->json();

            $rawText = $content['candidates'][0]['content']['parts'][0]['text'] ?? '{}';

            $cleanText = preg_replace('/^```json|```$/m', '', trim($rawText));
            $cleanText = preg_replace('/^```|```$/m', '', $cleanText);

            $questionData = json_decode($cleanText, true);

            Log::info($questionData);

            if (!is_array($questionData)) {
                return [
                    'status'  => 'error',
                    'message' => 'Invalid JSON response from Gemini.',
                ];
            }

            if (!isset($questionData['meta']) || !isset($questionData['questions'])) {
                return [
                    'status'  => 'error',
                    'message' => 'Invalid response from Gemini. Required keys missing: meta, questions',
                ];
            }

            return [
                'status' => 'success',
                'data'   => $questionData,
            ];

        } catch (\Throwable $th) {
            return ['status' => 'error', 'message' => $th->getMessage()];
        }
    }

    public function questionResult(Request $request, $id) {
        $request->validate([
            'option_id' => 'required|integer|exists:options,id',
        ]);

        $question = Question::findOrFail($id);
        $option   = Option::where('question_id', $question->id)->where('id', $request->option_id)->first();

        if (!$option) {
            $notify[] = ['error', 'Question not found yet!'];
            return back()->withNotify($notify);
        }

        $question->result_option_id = $option->id;
        $question->save();

        $notify[] = ['success', 'Answer submitted successfully'];
        return back()->withNotify($notify);
    }

    public function publishResult($id) {
        $exam = Exam::where('result_published', Status::NO)->where('result_published_at', '<=', now())->withWhereHas('attendExam', function ($query) {
            $query->withWhereHas('examAnswer', function ($q) {
                $q->with('question:id,result_option_id,question_type,answer_data');
            });
        })->with('questions:id,exam_id,question_type,result_option_id,answer_data')->where('id', $id)->first();

        if (!$exam) {
            $notify[] = ['error', 'Result already published!'];
            return back()->withNotify($notify);
        }

        foreach ($exam->attendExam as $attendExam) {
            $correctCount = 0;
            foreach ($attendExam->examAnswer->groupBy('question_id') as $answers) {
                $question = $answers->first()->question;
                $isCorrect = $this->answerIsCorrect($question, $answers);
                foreach ($answers as $examAnswer) {
                    $examAnswer->is_correct = $isCorrect ? Status::YES : Status::NO;
                    $examAnswer->save();
                }
                if ($isCorrect) {
                    $correctCount++;
                }
            }
            $questionCount = max($exam->questions->count(), 1);
            $attendExam->correct_answer  = $correctCount;
            $attendExam->pass_percentage = round(($correctCount / $questionCount) * 100, 2);
            $attendExam->status          = Status::EXAM_COMPLETED;
            $attendExam->save();
        }

        $exam->result_published = Status::YES;
        $exam->save();

        $notify[] = ['success', 'Result published successfully'];
        return back()->withNotify($notify);
    }

    private function answerIsCorrect($question, $answers): bool
    {
        $answers = collect($answers);
        $type = $question->question_type ?? 'single_choice';

        if ($type === 'drag_drop') {
            $submitted = $answers->first()?->answer_data ?? [];
            $correct = $question->answer_data['correctMatches'] ?? [];
            if (!is_array($submitted) || !is_array($correct) || count($submitted) !== count($correct)) {
                return false;
            }
            foreach ($correct as $target => $item) {
                if (($submitted[$target] ?? null) !== $item) {
                    return false;
                }
            }
            return true;
        }

        $submittedIds = $answers->pluck('option_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        if ($type === 'multiple_selection') {
            $correctIds = array_map('intval', $question->answer_data['correct_option_ids'] ?? []);
            sort($correctIds);
            return $submittedIds === $correctIds;
        }

        return count($submittedIds) === 1 && $submittedIds[0] === (int) $question->result_option_id;
    }
}
