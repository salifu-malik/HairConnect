<?php

declare(strict_types=1);

use App\Helpers\DatabaseManager;

require_once __DIR__ . '/../bootstrap.php';

date_default_timezone_set('Africa/Accra');

$db = DatabaseManager::getConnection('messaging_db');

$processed = 0;
$errors = 0;

try {
    /*
     * Find expired business conversations.
     *
     * Complaint conversations are deliberately excluded.
     * Each candidate is processed in its own transaction.
     */
    $stmt = $db->query("
        SELECT id
        FROM conversations
        WHERE conversation_type <> 'COMPLAINT'
          AND deleted_at IS NULL
          AND expires_at IS NOT NULL
          AND expires_at <= UTC_TIMESTAMP()
        ORDER BY expires_at ASC, id ASC
        LIMIT 100
    ");

    $conversationIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($conversationIds as $candidateId) {
        $conversationId = (int) $candidateId;

        try {
            $db->beginTransaction();

            // Lock and recheck the conversation before deleting it.
            $lock = $db->prepare("
                SELECT id, conversation_type, expires_at
                FROM conversations
                WHERE id = :id
                  AND deleted_at IS NULL
                LIMIT 1
                FOR UPDATE
            ");

            $lock->execute(['id' => $conversationId]);
            $conversation = $lock->fetch(PDO::FETCH_ASSOC);

            if (
                !$conversation
                || $conversation['conversation_type'] === 'COMPLAINT'
                || empty($conversation['expires_at'])
            ) {
                $db->commit();
                continue;
            }

            $expiryCheck = $db->prepare("
                SELECT :expires_at <= UTC_TIMESTAMP()
            ");

            $expiryCheck->execute([
                'expires_at' => $conversation['expires_at'],
            ]);

            if ((int) $expiryCheck->fetchColumn() !== 1) {
                $db->commit();
                continue;
            }



            $audit = $db->prepare("
                INSERT INTO conversation_deletion_audit (
                    original_conversation_id,
                    deleted_by,
                    deletion_source
                ) VALUES (
                    :conversation_id,
                    :deleted_by,
                    'AUTOMATIC_EXPIRY'
                )
            ");

            $audit->execute([
                'conversation_id' => $conversationId,
                'deleted_by' => null,
            ]);

            // Remove messages before the conversation.
            $deleteMessages = $db->prepare("
                DELETE FROM messages
                WHERE conversation_id = :conversation_id
            ");

            $deleteMessages->execute([
                'conversation_id' => $conversationId,
            ]);

            $deleteConversation = $db->prepare("
                DELETE FROM conversations
                WHERE id = :conversation_id
                  AND conversation_type <> 'COMPLAINT'
                  AND expires_at <= UTC_TIMESTAMP()
            ");

            $deleteConversation->execute([
                'conversation_id' => $conversationId,
            ]);

            if ($deleteConversation->rowCount() !== 1) {
                throw new RuntimeException(
                    "Conversation {$conversationId} was not deleted."
                );
            }

            $db->commit();
            $processed++;

        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $errors++;

            error_log(sprintf(
                '[CHAT_CLEANUP] Failed for conversation %d: %s',
                $conversationId,
                $error->getMessage()
            ));
        }
    }

    echo sprintf(
        "[%s] Chat cleanup complete. Candidates: %d, deleted: %d, errors: %d\n",
        gmdate('Y-m-d H:i:s'),
        count($conversationIds),
        $processed,
        $errors
    );

} catch (Throwable $error) {
    fwrite(
        STDERR,
        sprintf(
            "[%s] Chat cleanup failed: %s\n",
            gmdate('Y-m-d H:i:s'),
            $error->getMessage()
        )
    );

    exit(1);
}

if ($errors > 0) {
    exit(1);
}