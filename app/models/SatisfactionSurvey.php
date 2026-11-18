<?php
declare(strict_types=1);

/** Formulir kepuasan (FR-07): siklus draft → open → closed. */
final class SatisfactionSurvey extends Model
{
    protected static string $table = 'satisfaction_surveys';

    public static function withStats(): array
    {
        return Database::all(
            'SELECT s.*,
                    (SELECT COUNT(*) FROM satisfaction_questions q
                      WHERE q.survey_id = s.id AND q.is_active = 1) AS question_count,
                    (SELECT COUNT(*) FROM satisfaction_responses r WHERE r.survey_id = s.id) AS response_count
               FROM satisfaction_surveys s
              ORDER BY s.start_date IS NULL, s.start_date DESC, s.id DESC'
        );
    }

    public static function nameExists(string $name, int $exceptId = 0): bool
    {
        $sql    = 'SELECT COUNT(*) AS n FROM satisfaction_surveys WHERE name = ?';
        $params = [$name];
        if ($exceptId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        $row = Database::fetch($sql, $params);
        return (int)($row['n'] ?? 0) > 0;
    }

    /**
     * Rekap otomatis (FR-08): rerata per pertanyaan (skala 1–5),
     * rerata tertimbang keseluruhan, dan kutipan jawaban teks.
     */
    public static function recap(int $surveyId): array
    {
        $survey = self::find($surveyId);
        if (!$survey) {
            return [];
        }

        $questions = Database::all(
            'SELECT q.id, q.question, q.type, q.weight, q.sort_order,
                    COUNT(a.id) AS answer_count,
                    ROUND(AVG(a.score), 2) AS avg_score
               FROM satisfaction_questions q
               LEFT JOIN satisfaction_answers a ON a.question_id = q.id
              WHERE q.survey_id = ? AND q.is_active = 1
              GROUP BY q.id
              ORDER BY q.sort_order, q.id',
            [$surveyId]
        );

        $responseCount = (int)Database::fetch(
            'SELECT COUNT(*) AS n FROM satisfaction_responses WHERE survey_id = ?',
            [$surveyId]
        )['n'];

        // Rerata tertimbang hanya atas pertanyaan skala yang terjawab.
        $sumW  = 0.0;
        $sumWx = 0.0;
        foreach ($questions as $q) {
            if ($q['type'] === 'skala' && $q['avg_score'] !== null && (float)$q['weight'] > 0) {
                $sumW  += (float)$q['weight'];
                $sumWx += (float)$q['avg_score'] * (float)$q['weight'];
            }
        }
        $overallScore = $sumW > 0 ? round($sumWx / $sumW, 2) : null;
        $overallPct   = $overallScore !== null ? round($overallScore / 5 * 100, 1) : null;

        $texts = Database::all(
            'SELECT q.question, a.answer_text, r.submitted_at, u.name AS respondent_name
               FROM satisfaction_answers a
               JOIN satisfaction_questions q ON q.id = a.question_id
               JOIN satisfaction_responses r ON r.id = a.response_id
               LEFT JOIN users u ON u.id = r.respondent_user_id
              WHERE q.survey_id = ? AND a.answer_text IS NOT NULL AND a.answer_text <> ""
              ORDER BY a.id DESC
              LIMIT 30',
            [$surveyId]
        );

        return [
            'survey'         => $survey,
            'questions'      => $questions,
            'response_count' => $responseCount,
            'overall_score'  => $overallScore,
            'overall_pct'    => $overallPct,
            'texts'          => $texts,
        ];
    }
}
