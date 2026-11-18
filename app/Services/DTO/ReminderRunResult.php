<?php

declare(strict_types=1);

namespace App\Services\DTO;

class ReminderRunResult
{
    /**
     * @param  list<string>  $errors
     * @param  list<array{document_id: int, document_number: ?string, recipient_email: string, interval: string, days_remaining: int, status: string}>  $details
     */
    public function __construct(
        public int $scannedCount = 0,
        public int $sentCount = 0,
        public int $skippedCount = 0,
        public int $failedCount = 0,
        public int $expiredMarkedCount = 0,
        public array $errors = [],
        public array $details = [],
        public int $expiredNotifiedCount = 0
    ) {}
}
