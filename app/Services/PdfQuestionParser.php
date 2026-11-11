<?php

namespace App\Services;

use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Reads MCQs out of a text PDF without AI (smalot/pdfparser). The PDF must
 * follow a simple format (see the upload form):
 *
 *   12. A 30-year-old woman has tremor and weight loss… What is the diagnosis?
 *   A. Toxic multinodular goitre
 *   B. Graves disease
 *   C. Hashimoto thyroiditis
 *   Answer: B
 *   Explanation: Graves disease is…        (optional, may run over lines)
 *   Subject: Endocrinology                  (optional)
 *
 * Questions may be numbered "12.", "12)", "Q12." or "Question 12". Options
 * may be written "A.", "A)" or "(A)". Answers may also come in an answer key
 * at the end ("Answers" then "1. B", "2-C"…). Scanned PDFs (pictures of
 * pages) have no text and cannot be read.
 */
class PdfQuestionParser
{
    private const QUESTION = '/^\s*(?:Q(?:uestion)?\s*)?(\d{1,4})\s*[.):]\s*(.*)$/i';

    private const OPTION = '/^\s*\(?([A-Ea-e])\s*[.)]\s+(.+)$/';

    private const ANSWER = '/^\s*(?:correct\s+)?(?:answer|ans)\s*[:.\-]?\s*\(?([A-E])\)?\b/i';

    private const EXPLANATION = '/^\s*(?:explanation|rationale)\s*[:.\-]\s*(.*)$/i';

    private const SUBJECT = '/^\s*(?:subject|topic|category)\s*[:.\-]\s*(.+)$/i';

    private const KEY_HEADING = '/^\s*(?:answer\s*key|answers)\s*:?\s*$/i';

    private const FOOTER = '/·\s*page\s+\d+\s+of\s+\d+\s*$/iu';

    /** The long layout: "Question 12 of 213 Hypertrophic cardiomyopathy". */
    private const LONG_QUESTION = '/^\s*Question\s+(\d{1,4})\s+of\s+\d{1,4}\b\s*(.*)$/i';

    /**
     * Section headings of the long layout and where their text goes. Sections
     * mapped to null (the app's own textbook, atlas, video and image panels)
     * are left out.
     *
     * @var array<string, string|null>
     */
    private const SECTIONS = [
        'key point' => 'key_points',
        'key points' => 'key_points',
        'high-yield points' => 'key_points',
        'high yield points' => 'key_points',
        'mnemonic' => 'mnemonics',
        'mnemonics' => 'mnemonics',
        'why the other options are wrong' => 'explanation',
        'references and guidelines' => 'explanation',
        'references' => 'explanation',
        'guidelines' => 'explanation',
        'extended textbook' => null,
        'image atlas' => null,
        'youtube videos' => null,
        'youtube' => null,
        'image links' => null,
    ];

    /** The text of a PDF file. */
    public function text(string $pdfBinary): string
    {
        try {
            return (new Parser)->parseContent($pdfBinary)->getText();
        } catch (Throwable $exception) {
            throw new PdfImportException('This PDF could not be read (it may be damaged or password-protected).', previous: $exception);
        }
    }

    /**
     * The MCQs in the text, in order.
     *
     * @return list<array{number: int, stem: string, options: array<string, string>, correct_option: string|null, explanation: string, subject: string, title: string, key_points: string, mnemonics: string}>
     */
    public function parse(string $text): array
    {
        // Tabs are kept: in the long layout they separate the table columns.
        $lines = preg_split('/\R/u', $text) ?: [];
        // Page footers ("… · page 3 of 617") and lone page numbers are not content.
        $lines = array_values(array_filter($lines, fn (string $line): bool => ! preg_match(self::FOOTER, $line) && ! preg_match('/^\s*\d{1,4}\s*$/', $line)));
        if (preg_grep(self::LONG_QUESTION, $lines)) {
            return $this->parseLongForm($lines);
        }
        [$lines, $key] = $this->answerKey($lines);

        $questions = [];
        $current = null;
        $field = null; // Where a continuation line belongs: stem, an option letter, or explanation.
        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line) ?? '');
            if ($line === '') {
                continue;
            }
            // A new question starts with a number, unless we are still in the stem before any option.
            if (preg_match(self::QUESTION, $line, $match) && ($current === null || $current['options'] !== [] || $field === 'explanation')) {
                if ($current !== null) {
                    $questions[] = $current;
                }
                $current = ['number' => (int) $match[1], 'stem' => trim($match[2]), 'options' => [], 'correct_option' => null, 'explanation' => '', 'subject' => '', 'title' => '', 'key_points' => '', 'mnemonics' => ''];
                $field = 'stem';

                continue;
            }
            if ($current === null) {
                continue; // Title or instructions before the first question.
            }
            if (preg_match(self::ANSWER, $line, $match)) {
                $current['correct_option'] = strtoupper($match[1]);
                $field = null;
            } elseif (preg_match(self::EXPLANATION, $line, $match)) {
                $current['explanation'] = trim($match[1]);
                $field = 'explanation';
            } elseif (preg_match(self::SUBJECT, $line, $match)) {
                $current['subject'] = trim($match[1]);
                $field = null;
            } elseif ($field !== 'explanation' && preg_match(self::OPTION, $line, $match)) {
                // Several options may share one line: "A. One   B. Two".
                foreach ($this->splitOptions($line) as $letter => $option) {
                    $current['options'][$letter] = $option;
                    $field = $letter;
                }
            } elseif ($field === 'stem') {
                $current['stem'] .= ' '.$line;
            } elseif ($field === 'explanation') {
                $current['explanation'] .= "\n".$line;
            } elseif (is_string($field) && isset($current['options'][$field])) {
                $current['options'][$field] .= ' '.$line;
            }
        }
        if ($current !== null) {
            $questions[] = $current;
        }

        foreach ($questions as &$question) {
            $question['correct_option'] ??= $key[$question['number']] ?? null;
            $question['stem'] = trim($question['stem']);
            $question['explanation'] = trim($question['explanation']);
            ksort($question['options']);
        }

        return $questions;
    }

    /**
     * The long layout: "Question N of M <topic>", then Clinical scenario,
     * Investigations (a table), Question (the prompt), options written as
     * "AVerapamil" or "A. Verapamil", "Answer: D. …", and sections such as
     * Key point, Explanation, Mnemonic and "Why the other options are wrong".
     *
     * @param  list<string>  $lines
     * @return list<array{number: int, stem: string, options: array<string, string>, correct_option: string|null, explanation: string, subject: string, title: string, key_points: string, mnemonics: string}>
     */
    private function parseLongForm(array $lines): array
    {
        $questions = [];
        $q = null;
        $part = 'scenario'; // scenario, table, prompt, options, after (the answer has been given)
        $target = 'explanation'; // Where text after the answer goes; null = left out.
        $lastOption = null;
        $table = []; // Lines of the Investigations table (tabs kept: they separate the columns).
        // Per field after the answer: the last line added and the last step of a numbered list.
        $previous = ['explanation' => '', 'key_points' => '', 'mnemonics' => ''];
        $steps = ['explanation' => 0, 'key_points' => 0, 'mnemonics' => 0];
        foreach ($lines as $raw) {
            $line = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
            if ($line === '') {
                continue;
            }
            if (preg_match(self::LONG_QUESTION, $line, $match)) {
                if ($q !== null) {
                    if ($table !== []) {
                        $q['stem'] .= $this->markdownTable($table)."\n";
                        $table = [];
                    }
                    $questions[] = $q;
                }
                /** @var array{number: int, stem: string, options: array<string, string>, correct_option: string|null, explanation: string, subject: string, title: string, key_points: string, mnemonics: string} $q */
                $q = ['number' => (int) $match[1], 'stem' => '', 'options' => [], 'correct_option' => null, 'explanation' => '', 'subject' => '', 'title' => trim($match[2]), 'key_points' => '', 'mnemonics' => ''];
                $part = 'scenario';
                $target = 'explanation';
                $lastOption = null;
                $previous = ['explanation' => '', 'key_points' => '', 'mnemonics' => ''];
                $steps = ['explanation' => 0, 'key_points' => 0, 'mnemonics' => 0];

                continue;
            }
            if ($q === null) {
                continue; // The title page.
            }
            // The Investigations table runs until the "Question" (or another) heading.
            $heading = strtolower(rtrim($line, ':'));
            if ($part === 'table' && ! in_array($heading, ['question', 'clinical scenario'], true) && ! preg_match(self::ANSWER, $line)) {
                $table[] = trim(preg_replace(['/[ \x{00A0}]+/u', '/ *\t[ \t]*/u'], [' ', "\t"], $raw) ?? '');

                continue;
            }
            if ($table !== []) {
                $q['stem'] .= $this->markdownTable($table)."\n";
                $table = [];
            }
            if (preg_match('/^[—–-]+$/u', $line)) {
                continue; // An empty table cell ("—").
            }
            $lower = strtolower(rtrim($line, ':'));
            // The topic may be printed on the line after "Question 12 of 213".
            if ($q['title'] === '' && $q['stem'] === '' && $part === 'scenario' && $lower !== 'clinical scenario' && $lower !== 'investigations' && $lower !== 'question') {
                $q['title'] = $line;

                continue;
            }
            // A section ("Key point", "Explanation…") printed before the answer line ends the options.
            if ($part === 'options' && (array_key_exists($lower, self::SECTIONS) || preg_match('/^explanation\b/i', $line))) {
                $part = 'after';
            }
            // The answer line printed after such a section.
            if ($part === 'after' && $q['correct_option'] === null && preg_match(self::ANSWER, $line, $match)) {
                $q['correct_option'] = strtoupper($match[1]);

                continue;
            }

            if ($part !== 'after') {
                if (preg_match(self::ANSWER, $line, $match)) {
                    $q['correct_option'] = strtoupper($match[1]);
                    $part = 'after';
                    $target = 'explanation';

                    continue;
                }
                if ($lower === 'clinical scenario') {
                    $part = 'scenario';
                } elseif ($lower === 'investigations') {
                    $part = 'table';
                    $q['stem'] .= "\n\n**Investigations**\n\n";
                } elseif ($lower === 'question') {
                    $part = 'prompt';
                    $q['stem'] .= "\n\n";
                } elseif (preg_match(self::SUBJECT, $line, $match)) {
                    $q['subject'] = trim($match[1]);
                } elseif (($part === 'options' || ($part === 'prompt' && ! str_ends_with($q['stem'], '
'))) && ($option = $this->longOption($line, count($q['options']))) !== null) {
                    // Options start only after the prompt line itself.
                    $q['options'][$option[0]] = $option[1];
                    $lastOption = $option[0];
                    $part = 'options';
                } elseif ($part === 'options' && $lastOption !== null) {
                    $q['options'][$lastOption] .= ' '.$line; // A long option over two lines.
                } else {
                    $q['stem'] .= ($q['stem'] === '' || str_ends_with($q['stem'], "\n") ? '' : ' ').$line;
                }

                continue;
            }

            // After the answer: sections go to the explanation, key points or mnemonics.
            if (array_key_exists($lower, self::SECTIONS)) {
                $target = self::SECTIONS[$lower];
                if ($target === 'explanation') {
                    $q['explanation'] = rtrim($q['explanation'])."\n\n**".$line."**\n";
                } elseif ($target !== null && $q[$target] !== '') {
                    $q[$target] = rtrim($q[$target])."\n";
                }
                if ($target !== null) {
                    [$previous[$target], $steps[$target]] = ['', 0];
                }

                continue;
            }
            if (preg_match('/^explanation\b/i', $line)) {
                $target = 'explanation';
                $q['explanation'] = rtrim($q['explanation'])."\n\n**".$line."**\n";
                [$previous['explanation'], $steps['explanation']] = ['', 0];

                continue;
            }
            // "Why the other options are wrong": each option starts its own bullet.
            if ($target === 'explanation' && str_contains($q['explanation'], '**Why the other options are wrong**')
                && preg_match('/^\(?([A-E])[.)]?\s?(\S.*)$/u', $line, $match) && isset($q['options'][$match[1]])
                && str_starts_with(mb_strtolower($match[2]), mb_strtolower(mb_substr($q['options'][$match[1]], 0, 12)))) {
                $q['explanation'] = rtrim($q['explanation'])."\n- **".$match[1].'.** '.$match[2];
                [$previous['explanation'], $steps['explanation']] = [$line, 0];

                continue;
            }
            if ($target !== null) {
                // Tabs separate the columns of a table row; a tab inside a word ("first-degr⇥ee") is a PDF quirk.
                $cells = trim(preg_replace(['/[ \x{00A0}]+/u', '/ *\t[ \t]*/u', '/(\p{Ll})\t(\p{Ll})/u', '/(\p{L})\t(-)/u'], [' ', "\t", '$1$2', '$1$2'], $raw) ?? '');
                [$q[$target], $previous[$target], $steps[$target]] = $this->appendLine($q[$target], $cells, $previous[$target], $steps[$target]);
            }
        }
        if ($q !== null) {
            if ($table !== []) {
                $q['stem'] .= $this->markdownTable($table)."\n";
            }
            $questions[] = $q;
        }

        foreach ($questions as &$question) {
            foreach (['stem', 'explanation', 'key_points', 'mnemonics'] as $field) {
                $question[$field] = trim(preg_replace("/\n{3,}/", "\n\n", $question[$field]) ?? '');
            }
            // Several key points (Key point + High-yield points): one bullet each.
            $points = array_values(array_filter(array_map('trim', preg_split('/ {2}\n|\n/', $question['key_points']) ?: [])));
            if (count($points) > 1) {
                $question['key_points'] = implode("\n", array_map(fn (string $point): string => preg_match('/^(?:- |\d+\. )/', $point) ? $point : '- '.$point, $points));
            }
            $question['options'] = array_map('trim', $question['options']);
            ksort($question['options']);
        }

        return $questions;
    }

    /**
     * Adds one line of an explanation (or key points, mnemonic) as the PDF
     * printed it. A line that continues a wrapped paragraph is joined to it
     * (the previous line ran the full width, ends mid-phrase, or this line
     * starts in lower case); otherwise it starts a new line, which keeps the
     * rows of a table and short headings apart. "Label⇥Value" rows become
     * "Label — Value", and steps printed as "Confirm HCM…1", "Calculate…2"
     * become a numbered list.
     *
     * @return array{0: string, 1: string, 2: int} the text, the line just added, the last step number
     */
    private function appendLine(string $text, string $line, string $previous, int $step): array
    {
        $plain = str_replace("\t", ' ', $line);
        $formatted = str_contains($line, "\t")
            ? (count($parts = array_values(array_filter(explode("\t", $line), fn (string $cell): bool => trim($cell) !== ''))) === 2 ? $parts[0].' — '.$parts[1] : implode(' · ', $parts))
            : $line;

        // A step of a numbered flowchart: the number is glued to the end of its line.
        if (mb_strlen($plain) <= 160 && preg_match('/^(.*[^\s\d])(\d{1,2})$/u', $formatted, $match) && (int) $match[2] === $step + 1) {
            $item = $match[2].'. '.trim($match[1]);

            return [rtrim($text).($step === 0 ? "\n\n" : "\n").$item, $plain, $step + 1];
        }

        $joins = $previous !== '' && (
            // A wrapped paragraph line runs (nearly) the full width; a table row (with a tab) never continues the row above.
            (mb_strlen($previous) >= 78 && ! str_contains($line, "\t"))
            || preg_match('/^[\p{Ll}(]/u', $plain)
            || substr_count($previous, '(') > substr_count($previous, ')')
            || preg_match('/[\p{L}\d]-$/u', $previous)
            || preg_match('/[,;–\/]$/u', $previous)
            || preg_match('/^[↓↑]\S{0,2}$/u', $plain)
        );
        if ($text === '' || str_ends_with($text, "\n")) {
            $separator = '';
        } elseif ($joins) {
            $separator = preg_match('/[\p{L}\d]-$/u', $text) ? '' : ' ';
        } else {
            // A new line (a Markdown line break); after a numbered or bullet list, a new paragraph so the line is not inside the list.
            $lastLine = (string) strrchr("\n".$text, "\n");
            $separator = ($step > 0 || preg_match('/^\n- /', $lastLine)) ? "\n\n" : "  \n";
            $step = 0;
        }

        return [rtrim($text, ' ').$separator.$formatted, $plain, $step];
    }

    /**
     * The Investigations table as a Markdown table. In the PDF text a tab
     * separates the columns (sometimes only Result from Reference), a long
     * cell runs onto the next line ("135–145" / "mmol/L", "a 6-" / "beat
     * run"), a test name can take two lines ("Renal" / "ultrasound") and
     * "—" is an empty reference.
     *
     * @param  list<string>  $lines
     */
    private function markdownTable(array $lines): string
    {
        $lines = array_values(array_filter($lines, fn (string $line): bool => $line !== ''));
        if ($lines === []) {
            return '';
        }
        $first = strtolower(str_replace("\t", ' ', $lines[0]));
        $hasHeader = str_contains($first, 'investigation') && str_contains($first, 'result');
        if ($hasHeader) {
            array_shift($lines);
        }
        $columns = ($hasHeader && ! str_contains($first, 'reference')) ? 2 : 3;

        // Join the lines into rows. A row is complete once it has its reference
        // (a range, "<14 ng/L", "—"…); until then the next lines belong to it.
        $isReference = fn (string $text): bool => (bool) preg_match('/^(?:—|[<>≤≥]|\d[\d.,]*\s?[–-]\s?\d|negative|positive|normal|absent|present)/iu', trim($text));
        $complete = function (string $row) use ($columns, $isReference): bool {
            if (str_ends_with($row, '—')) {
                return true;
            }
            $parts = explode("\t", $row);

            return count($parts) >= $columns || ($columns === 3 && count($parts) === 2 && $isReference((string) end($parts)));
        };
        $join = fn (string $row, string $line): string => $row.(preg_match('/[\p{L}\d]-$/u', $row) ? '' : ' ').$line; // "a 6-" + "beat" = "a 6-beat"
        $rows = [];
        foreach ($lines as $line) {
            $last = count($rows) - 1;
            if (preg_match('/^[—–-]+$/u', $line)) {
                if ($last >= 0) {
                    $rows[$last] .= "\t—";
                }

                continue;
            }
            if ($last < 0) {
                $rows[] = $line;

                continue;
            }
            $row = $rows[$last];
            if ($complete($row)) {
                // Only a unit that wrapped ("mmol/L") still belongs to a complete row.
                if (preg_match('/^[\p{Ll}µ%×][^\s]{0,14}$/u', $line)) {
                    $rows[$last] = $row.' '.$line;
                } else {
                    $rows[] = $line;
                }
            } elseif (str_contains($row, "\t")) {
                // The result runs on, unless this line is a new row of its own.
                if (str_contains($line, "\t")) {
                    $rows[] = $line;
                } else {
                    $rows[$last] = $join($row, $line);
                }
            } elseif (! str_contains($line, "\t") && (preg_match('/^[\p{Ll}(]/u', $line) || count(preg_split('/\s+/u', $line) ?: []) <= 2)) {
                $rows[$last] = $join($row, $line); // The test name runs on ("High-sensitivity" / "troponin T").
            } else {
                $rows[$last] = $row."\t".$line; // The row was only a test name: this line is its result.
            }
        }

        $cell = fn (string $text): string => str_replace('|', '\|', trim($text));
        $out = $columns === 3
            ? ['| Investigation | Result | Reference |', '| --- | --- | --- |']
            : ['| Investigation | Result |', '| --- | --- |'];
        foreach ($rows as $row) {
            $parts = array_map('trim', explode("\t", $row));
            if (count($parts) >= 3) {
                [$name, $result, $reference] = [$parts[0], $parts[1], implode(' ', array_slice($parts, 2))];
            } elseif (count($parts) === 2) {
                [$name, $result] = $this->splitTest($parts[0]);
                $reference = $parts[1];
                if ($result === '') {
                    // "LDL cholesterol<TAB>4.0 mmol/L <1.8 mmol/L target": the reference is inside the result.
                    [$name, $result, $reference] = [$parts[0], $parts[1], ''];
                    if (preg_match('/^(.*?\d.*?\S)\s+((?:[<>≤≥]\s?\d|\d[\d.,]*\s?[–-]\s?\d).*)$/u', $result, $match)) {
                        [$result, $reference] = [$match[1], $match[2]];
                    }
                }
            } else {
                $reference = '';
                if ($columns === 3 && preg_match('/^(.*\S)\s+((?:[<>≤≥]\s?)?\d[\d.,]*\s?(?:[–-]\s?\d[\d.,]*)?(?:\s?[^\s\d][^\s]*)?)$/u', $parts[0], $match)
                    && $this->splitTest($match[1])[1] !== '') {
                    [$parts[0], $reference] = [$match[1], $match[2]];
                }
                [$name, $result] = $this->splitTest($parts[0]);
            }
            if ($columns === 2) {
                $result = trim($result.' '.$reference);
            }
            $out[] = '| '.$cell($name).' | '.$cell($result).($columns === 3 ? ' | '.$cell($reference) : '').' |';
        }

        return implode("\n", $out)."\n";
    }

    /**
     * "Haemoglobin 138 g/L" → ["Haemoglobin", "138 g/L"]; "24-h Holter
     * Frequent ectopics" → ["24-h Holter", "Frequent ectopics"]. The result
     * starts at the first word (after the first) that begins with a capital,
     * a digit or a sign and is followed by a lower-case word (or nothing).
     *
     * @return array{0: string, 1: string}
     */
    private function splitTest(string $text): array
    {
        $text = trim($text);
        // Tests whose result often starts in lower case, or is glued to the name in the PDF ("culturesStreptococcus").
        foreach (['blood cultures', 'blood culture', 'blood film', 'chest x-ray', 'chest radiograph', 'urine dipstick', 'urinalysis', 'echocardiogram'] as $known) {
            if (mb_strlen($text) > mb_strlen($known) + 1 && mb_strtolower(mb_substr($text, 0, mb_strlen($known))) === $known) {
                return [mb_substr($text, 0, mb_strlen($known)), trim(mb_substr($text, mb_strlen($known)))];
            }
        }
        $words = preg_split('/\s+/u', $text) ?: [];
        $count = count($words);
        for ($i = 1; $i < $count; $i++) {
            $starts = preg_match('/^[\p{Lu}\d<>≤≥↑↓±+]/u', $words[$i]);
            $next = $words[$i + 1] ?? null;
            // The value is followed by a lower-case word or a unit ("g/L", "IU/L", "×10⁹/L", "mmHg").
            if ($starts && ($next === null || preg_match('/^[\p{Ll}µ%×+\-(]/u', $next) || str_contains($next, '/') || preg_match('/^(?:IU|U|kPa|L|mL)\b/', $next))) {
                return [implode(' ', array_slice($words, 0, $i)), implode(' ', array_slice($words, $i))];
            }
        }

        return [trim($text), ''];
    }

    /**
     * The next option of the long layout: "AVerapamil", "A Verapamil" or
     * "A. Verapamil", only for the letter that comes next (A, then B…).
     *
     * @return array{0: string, 1: string}|null
     */
    private function longOption(string $line, int $count): ?array
    {
        if ($count >= 5) {
            return null;
        }
        $letter = chr(ord('A') + $count);
        if (preg_match('/^\(?'.$letter.'(?:[.)]\s*|\s+|(?=\S))(\S.*)$/u', $line, $match)) {
            return [$letter, trim($match[1])];
        }

        return null;
    }

    /**
     * Options written on one line ("A. One B. Two") or one per line.
     *
     * @return array<string, string>
     */
    private function splitOptions(string $line): array
    {
        $parts = preg_split('/(?:^|\s{1,})\(?([A-E])[.)]\s+/', ' '.$line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $options = [];
        for ($i = 0; $i + 1 < count($parts); $i += 2) {
            if (preg_match('/^[A-E]$/', $parts[$i])) {
                $options[$parts[$i]] = trim($parts[$i + 1]);
            }
        }

        return $options;
    }

    /**
     * An answer key at the end ("Answers" then "1. B", "2-C", "3) D"…), and
     * the lines before it.
     *
     * @param  list<string>  $lines
     * @return array{0: list<string>, 1: array<int, string>}
     */
    private function answerKey(array $lines): array
    {
        foreach ($lines as $index => $line) {
            if (preg_match(self::KEY_HEADING, $line)) {
                $key = [];
                preg_match_all('/\b(\d{1,4})\s*[.)\-:]?\s*([A-E])\b/', implode("\n", array_slice($lines, $index + 1)), $pairs, PREG_SET_ORDER);
                foreach ($pairs as $pair) {
                    $key[(int) $pair[1]] = $pair[2];
                }

                return [array_slice($lines, 0, $index), $key];
            }
        }

        return [$lines, []];
    }
}
