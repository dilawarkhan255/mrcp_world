<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/**
 * The AI tutor, powered by Claude through the official Anthropic PHP SDK.
 *
 * - Study plan advice: written from the student's own numbers (subjects and
 *   accuracy). No name, email or other personal data is sent.
 * - Question explanations: written from the question, its options, the
 *   answer and the editor's explanation, after the answer is revealed.
 *
 * Every answer is cached, so the same request is paid for once. Each student
 * has a daily limit. Without an API key the feature is off and the pages fall
 * back to the rule-based study plan.
 */
class AiTutor
{
    private const SYSTEM = <<<'TXT'
        You are a tutor for doctors preparing for the MRCP (UK) written exams, writing for the MRCPWorld question bank.
        Write in clear, plain English for a busy doctor. Use short paragraphs and bullet points in Markdown. Do not use HTML or tables.
        Be accurate. Never invent a guideline title, year, number or URL. Only name a NICE or WHO guideline if it appears in the material you are given; otherwise say "check the current NICE guidance" without naming one.
        If you are not sure about a fact, say so plainly. Do not give advice about a real patient.
        TXT;

    public function __construct(private ?Client $client = null) {}

    public function enabled(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * Advice on a 7-day study plan, from the student's numbers only.
     *
     * @param  array{weak: list<array<string, mixed>>, unpractised: list<array<string, mixed>>, days: list<array<string, mixed>>, has_data: bool}  $plan
     */
    public function studyPlanAdvice(User $user, string $examName, array $plan): string
    {
        $weak = array_map(fn (array $subject): string => '- '.$subject['name'].': '.$subject['reason'], $plan['weak']);
        $new = array_map(fn (array $subject): string => '- '.$subject['name'].' ('.$subject['available'].' questions)', array_slice($plan['unpractised'], 0, 6));
        $days = array_map(fn (array $day): string => 'Day '.$day['day'].': '.implode(', ', array_map(fn (array $item): string => $item['name'].' ('.$item['count'].')', $day['items'])), $plan['days']);
        $prompt = "Exam: {$examName}\n\nWeakest subjects:\n".($weak ? implode("\n", $weak) : '- none yet (fewer than 10 answers per subject)')
            ."\n\nSubjects not practised yet:\n".($new ? implode("\n", $new) : '- none')
            ."\n\nThe 7-day plan already chosen:\n".implode("\n", $days)
            ."\n\nWrite a short, encouraging study plan (at most 180 words): what to focus on first and why, how to use each day, and one exam technique tip. Refer to the subjects by name. Do not change the plan's numbers.";

        return $this->ask($user, 'plan:'.$user->id.':'.now()->toDateString().':'.md5($prompt), $prompt);
    }

    /**
     * The student's own question about an MCQ. Before the answer is revealed,
     * Claude sees only the stem and the options (never the answer key), so it
     * reasons the answer out itself; afterwards it also sees the answer and
     * the editor's explanation.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function askAboutQuestion(User $user, int $questionId, array $snapshot, bool $revealed, ?string $selected, string $question): string
    {
        $options = [];
        foreach ((array) ($snapshot['options'] ?? []) as $key => $text) {
            $options[] = $key.'. '.$text;
        }
        $prompt = 'MCQ:
'.$snapshot['body'].'

Options:
'.implode('
', $options);
        if ($revealed) {
            $prompt .= '

Correct answer: '.$snapshot['correct_option']
                .($selected !== null ? "
The student chose: {$selected}" : '')
                ."

Editor's explanation:
".($snapshot['explanation'] ?: '(none)');
        } else {
            $prompt .= '

The student has not answered yet. You are not given the answer key: reason it out from the stem and options. If you give an answer, say it is your reasoning and that they can check it by submitting.';
        }
        // The same question about the same MCQ, whatever its spacing or capitals, is answered once and shared.
        $normalised = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $question)));
        $cacheKey = 'ask:'.$questionId.':'.($revealed ? 'revealed:'.($selected ?? '-') : 'open').':'.md5($prompt.'|'.$normalised);
        $prompt .= '

The student asks: '.$normalised.'

Answer in at most 200 words.';

        return $this->ask($user, $cacheKey, $prompt, now()->addDays(30));
    }

    /** Requests left today for the student. */
    public function remaining(User $user): int
    {
        return RateLimiter::remaining($this->limiterKey($user), $this->dailyLimit());
    }

    private function ask(User $user, string $cacheKey, string $prompt, ?\DateTimeInterface $ttl = null): string
    {
        if (! $this->enabled()) {
            throw new RuntimeException('The AI tutor is not switched on.');
        }
        $key = 'ai-tutor:'.$cacheKey;
        $cached = Cache::get($key);
        if (is_string($cached)) {
            return $cached;
        }
        $limiter = $this->limiterKey($user);
        if (RateLimiter::tooManyAttempts($limiter, $this->dailyLimit())) {
            throw new AiTutorException('You have used today\'s AI requests. They reset tomorrow.');
        }
        RateLimiter::hit($limiter, (int) now()->secondsUntilEndOfDay() + 1);

        $text = $this->send($prompt);
        Cache::put($key, $text, $ttl ?? now()->endOfDay());

        return $text;
    }

    /** One request to Claude. Overridden in tests. */
    protected function send(string $prompt): string
    {
        try {
            $message = $this->client()->beta->messages->create(
                model: (string) config('services.anthropic.model'),
                maxTokens: 4000,
                system: self::SYSTEM,
                messages: [['role' => 'user', 'content' => $prompt]],
                // Short, focused answers: low effort keeps them quick and inexpensive.
                outputConfig: ['effort' => 'low'],
                // If Claude declines for a safety reason, the API retries on its recommended fallback model.
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (RateLimitException $exception) {
            throw new AiTutorException('The AI tutor is busy. Please try again in a minute.', previous: $exception);
        } catch (AuthenticationException|BadRequestException $exception) {
            // A configuration problem (key, model or request): the admin needs to look, the student only sees a short note.
            Log::error('AI tutor request rejected', ['type' => $exception->type?->value, 'message' => $exception->getMessage()]);
            throw new AiTutorException('The AI tutor is not available right now.', previous: $exception);
        } catch (APIStatusException|APIConnectionException $exception) {
            Log::warning('AI tutor request failed', ['message' => $exception->getMessage()]);
            throw new AiTutorException('The AI tutor could not be reached. Please try again.', previous: $exception);
        }
        if ($message->stopReason === 'refusal') {
            throw new AiTutorException('The AI tutor could not answer this one. Please use the explanation above.');
        }
        $text = '';
        foreach ($message->content as $block) {
            if (($block->type ?? null) === 'text') {
                $text .= $block->text;
            }
        }
        if (trim($text) === '') {
            throw new AiTutorException('The AI tutor returned no answer. Please try again.');
        }

        return trim($text);
    }

    private function client(): Client
    {
        return $this->client ??= new Client(
            apiKey: (string) config('services.anthropic.key'),
            requestOptions: ['timeout' => 60.0, 'maxRetries' => 2],
        );
    }

    private function limiterKey(User $user): string
    {
        return 'ai-tutor-daily:'.$user->id;
    }

    private function dailyLimit(): int
    {
        return max(1, (int) config('services.anthropic.daily_limit', 20));
    }
}
