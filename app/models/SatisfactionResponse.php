<?php
declare(strict_types=1);

/** Satu jawaban kepuasan per pengguna per survei (dicek di aplikasi). */
final class SatisfactionResponse extends Model
{
    protected static string $table = 'satisfaction_responses';

    public static function hasResponded(int $surveyId, int $userId): bool
    {
        $row = Database::fetch(
            'SELECT COUNT(*) AS n FROM satisfaction_responses WHERE survey_id = ? AND respondent_user_id = ?',
            [$surveyId, $userId]
        );
        return (int)($row['n'] ?? 0) > 0;
    }

    public static function forSurvey(int $surveyId): array
    {
        return Database::all(
            'SELECT r.*, u.name AS respondent_name, u.email AS respondent_email
               FROM satisfaction_responses r
               LEFT JOIN users u ON u.id = r.respondent_user_id
              WHERE r.survey_id = ?
              ORDER BY r.submitted_at DESC',
            [$surveyId]
        );
    }
}
