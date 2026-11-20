<?php
declare(strict_types=1);

/** Pertanyaan formulir kepuasan — dapat dikelola Super Admin (skala 1–5 / teks). */
final class SatisfactionQuestion extends Model
{
    protected static string $table = 'satisfaction_questions';

    public static function forSurvey(int $surveyId): array
    {
        return Database::all(
            'SELECT q.*,
                    (SELECT COUNT(*) FROM satisfaction_answers a WHERE a.question_id = q.id) AS answer_count
               FROM satisfaction_questions q
              WHERE q.survey_id = ?
              ORDER BY q.sort_order, q.id',
            [$surveyId]
        );
    }

    public static function activeForSurvey(int $surveyId): array
    {
        return Database::all(
            'SELECT * FROM satisfaction_questions
              WHERE survey_id = ? AND is_active = 1
              ORDER BY sort_order, id',
            [$surveyId]
        );
    }

    /**
     * Item 11 — validasi input opsi pilihan: "Ya|Tidak" → ['Ya','Tidak'];
     * null bila kurang dari 2 atau lebih dari 10 opsi.
     */
    public static function parseOptions(string $raw): ?array
    {
        $parts = [];
        foreach (explode('|', $raw) as $piece) {
            $piece = mb_substr(trim($piece), 0, 100);
            if ($piece !== '' && !in_array($piece, $parts, true)) {
                $parts[] = $piece;
            }
        }
        if (count($parts) < 2 || count($parts) > 10) {
            return null;
        }
        return $parts;
    }

    /**
     * Item 11 — daftar opsi siap render untuk pertanyaan pilihan;
     * bila opsi kosong (baris lama) pakai bawaan "Ya|Tidak".
     */
    public static function optionsOf(array $q): array
    {
        $parts = [];
        foreach (explode('|', (string)($q['options'] ?? '')) as $piece) {
            $piece = trim($piece);
            if ($piece !== '') {
                $parts[] = $piece;
            }
        }
        return $parts ?: ['Ya', 'Tidak'];
    }
}
