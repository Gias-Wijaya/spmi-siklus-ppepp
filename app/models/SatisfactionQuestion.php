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
}
