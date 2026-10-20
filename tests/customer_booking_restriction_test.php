<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Helpers\DatabaseManager;
use App\Repositories\CustomerBookingRestrictionRepository;

$db = DatabaseManager::getConnection('booking_db');

$restrictionRepository = new CustomerBookingRestrictionRepository();

$passed = 0;
$failed = 0;

$testCustomerId = 999999;
$restrictionId = null;

$cancellationDate = '2026-10-12';
$restrictionEndDate = '2026-10-15';
$allowedBookingDate = '2026-10-16';

$testReason = 'TEST_ONLY_customer_booking_restriction_test';

/**
 * Execute a test and record its result.
 */
function checkRestrictionTest(
    string $name,
    callable $test,
    int &$passed,
    int &$failed
): void {
    try {
        $test();

        echo "[PASS] {$name}\n";
        $passed++;
    } catch (Throwable $e) {
        echo "[FAIL] {$name}: {$e->getMessage()}\n";
        $failed++;
    }
}

try {
    /*
     * Create a test restriction directly in the database.
     *
     * A unique reason allows cleanup to identify the exact
     * test-created record without touching legitimate records.
     */
    $stmt = $db->prepare("
        INSERT INTO customer_booking_restrictions (
            customer_id,
            restriction_start_date,
            restriction_end_date,
            reason
        ) VALUES (
            :customer_id,
            :start_date,
            :end_date,
            :reason
        )
    ");

    $stmt->execute([
        'customer_id' => $testCustomerId,
        'start_date' => $cancellationDate,
        'end_date' => $restrictionEndDate,
        'reason' => $testReason,
    ]);

    $restrictionId = (int) $db->lastInsertId();

    if ($restrictionId <= 0) {
        throw new RuntimeException(
            'Could not identify the test-created restriction.'
        );
    }

    /*
     * Test 1:
     * The cancellation date is restricted.
     */
    checkRestrictionTest(
        'Cancellation date is restricted',
        function () use (
            $restrictionRepository,
            $testCustomerId,
            $restrictionId
        ): void {
            $restriction = $restrictionRepository
                ->findActiveRestriction(
                    $testCustomerId,
                    '2026-10-12'
                );

            if (
                $restriction === null
                || (int) $restriction['id'] !== $restrictionId
            ) {
                throw new RuntimeException(
                    'The test restriction was not returned '
                    . 'for the cancellation date.'
                );
            }
        },
        $passed,
        $failed
    );

    /*
     * Test 2:
     * The fourth calendar day is still restricted.
     */
    checkRestrictionTest(
        'Fourth restricted calendar day is blocked',
        function () use (
            $restrictionRepository,
            $testCustomerId,
            $restrictionId
        ): void {
            $restriction = $restrictionRepository
                ->findActiveRestriction(
                    $testCustomerId,
                    '2026-10-15'
                );

            if (
                $restriction === null
                || (int) $restriction['id'] !== $restrictionId
            ) {
                throw new RuntimeException(
                    'The test restriction was not returned '
                    . 'for the final restricted day.'
                );
            }
        },
        $passed,
        $failed
    );

    /*
  * Test 3:
  * The test restriction must no longer be active
  * on the fifth calendar day.
  */
    checkRestrictionTest(
        'Test restriction expires on the fifth calendar day',
        function () use (
            $db,
            $restrictionId,
            $allowedBookingDate
        ): void {
            $stmt = $db->prepare("
            SELECT id
            FROM customer_booking_restrictions
            WHERE id = :id
              AND restriction_start_date <= :test_date_start
              AND restriction_end_date >= :test_date_end
        ");

            $stmt->execute([
                'id' => $restrictionId,
                'test_date_start' => $allowedBookingDate,
                'test_date_end' => $allowedBookingDate,
            ]);

            if ($stmt->fetchColumn() !== false) {
                throw new RuntimeException(
                    'The test restriction remained active after its expiry date.'
                );
            }
        },
        $passed,
        $failed
    );

    /*
     * Test 4:
     * Verify the stored restriction boundaries.
     */
    checkRestrictionTest(
        'Restriction stores the correct start and end dates',
        function () use (
            $db,
            $restrictionId,
            $cancellationDate,
            $restrictionEndDate,
            $testReason,
            $testCustomerId
        ): void {
            $stmt = $db->prepare("
                SELECT
                    customer_id,
                    restriction_start_date,
                    restriction_end_date,
                    reason
                FROM customer_booking_restrictions
                WHERE id = :id
            ");

            $stmt->execute([
                'id' => $restrictionId,
            ]);

            $restriction = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$restriction) {
                throw new RuntimeException(
                    'The test restriction could not be found.'
                );
            }

            if (
                (int) $restriction['customer_id'] !== $testCustomerId
                || $restriction['restriction_start_date']
                !== $cancellationDate
                || $restriction['restriction_end_date']
                !== $restrictionEndDate
                || $restriction['reason'] !== $testReason
            ) {
                throw new RuntimeException(
                    'The stored restriction details are incorrect.'
                );
            }
        },
        $passed,
        $failed
    );

} catch (Throwable $e) {
    echo "[ERROR] Test setup failed: {$e->getMessage()}\n";
    $failed++;

} finally {
    /*
     * Delete only the exact record created by this test.
     *
     * Existing appointments and legitimate restrictions
     * are never deleted by this cleanup.
     */
    if ($restrictionId !== null && $restrictionId > 0) {
        try {
            $cleanup = $db->prepare("
                DELETE FROM customer_booking_restrictions
                WHERE id = :id
                  AND customer_id = :customer_id
                  AND reason = :reason
            ");

            $cleanup->execute([
                'id' => $restrictionId,
                'customer_id' => $testCustomerId,
                'reason' => $testReason,
            ]);

            if ($cleanup->rowCount() !== 1) {
                echo "[WARNING] Expected to remove one test restriction, "
                    . "but removed {$cleanup->rowCount()}.\n";

                $failed++;
            } else {
                echo "[CLEANUP] Removed test restriction "
                    . "{$restrictionId}\n";
            }

        } catch (Throwable $e) {
            echo "[WARNING] Restriction cleanup failed: "
                . $e->getMessage()
                . "\n";

            $failed++;
        }
    }
}

echo "\nResults: {$passed} passed, {$failed} failed.\n";

exit($failed > 0 ? 1 : 0);