<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReminderInterval;
use App\Models\Document;
use App\Models\ReminderLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReminderLog>
 */
class ReminderLogFactory extends Factory
{
    protected $model = ReminderLog::class;

    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'recipient_user_id' => User::factory(),
            'recipient_email' => fake()->safeEmail(),
            'interval_type' => ReminderInterval::H30,
            'sent_date' => now()->toDateString(),
            'expired_date_snapshot' => now()->addDays(30)->toDateString(),
            'sent_at' => now(),
            'is_success' => true,
            'error_message' => null,
        ];
    }
}
