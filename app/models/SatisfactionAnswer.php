<?php
declare(strict_types=1);

/** Jawaban formulir kepuasan: skala (score 1–5) atau teks bebas. */
final class SatisfactionAnswer extends Model
{
    protected static string $table = 'satisfaction_answers';

    public static function forResponse(int $responseId): array
    {
        return Database::all(
            'SELECT a.*, q.question, q.type
               FROM satisfaction_answers a
               JOIN satisfaction_questions q ON q.id = a.question_id
              WHERE a.response_id = ?
              ORDER BY q.sort_order, q.id',
            [$responseId]
        );
    }
}
