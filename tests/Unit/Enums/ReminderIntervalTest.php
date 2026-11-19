<?php

namespace Tests\Unit\Enums;

use App\Enums\ApprovalStatus;
use App\Enums\DegreeLevel;
use App\Enums\DocumentStatus;
use App\Enums\PostStatus;
use App\Enums\ReminderInterval;
use App\Enums\StampingPosition;
use App\Enums\StorageDisk;
use App\Enums\UnitType;
use App\Enums\VisibilityLevel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReminderIntervalTest extends TestCase
{
    /**
     * @return array<string, array{0: ReminderInterval, 1: int, 2: string, 3: string}>
     */
    public static function reminderIntervalCases(): array
    {
        return [
            'h60' => [ReminderInterval::H60, 60, 'H-60 Hari Sebelum Kedaluwarsa', 'info'],
            'h30' => [ReminderInterval::H30, 30, 'H-30 Hari Sebelum Kedaluwarsa', 'warning'],
            'h7' => [ReminderInterval::H7, 7, 'H-7 Hari (Kritis) Sebelum Kedaluwarsa', 'danger'],
        ];
    }

    #[DataProvider('reminderIntervalCases')]
    public function test_returns_expected_days_label_and_color_for_interval(
        ReminderInterval $interval,
        int $expectedDays,
        string $expectedLabel,
        string $expectedColor,
    ): void {
        $this->assertSame($expectedDays, $interval->daysBeforeExpiry());
        $this->assertSame($expectedLabel, $interval->getLabel());
        $this->assertSame($expectedColor, $interval->getColor());
    }

    /**
     * @return array<string, array{0: HasLabel}>
     */
    public static function allApplicationEnumCases(): array
    {
        $enumClasses = [
            ApprovalStatus::class,
            DegreeLevel::class,
            DocumentStatus::class,
            PostStatus::class,
            ReminderInterval::class,
            StampingPosition::class,
            StorageDisk::class,
            UnitType::class,
            VisibilityLevel::class,
        ];

        $cases = [];

        foreach ($enumClasses as $enumClass) {
            foreach ($enumClass::cases() as $case) {
                $key = class_basename($enumClass).'::'.$case->name;
                $cases[$key] = [$case];
            }
        }

        return $cases;
    }

    #[DataProvider('allApplicationEnumCases')]
    public function test_returns_non_empty_filament_contract_metadata_for_every_enum_case(HasLabel $enumCase): void
    {
        $this->assertNotEmpty($enumCase->getLabel());

        if ($enumCase instanceof HasColor) {
            $this->assertNotEmpty($enumCase->getColor());
        }

        if ($enumCase instanceof HasIcon) {
            $this->assertNotEmpty($enumCase->getIcon());
        }
    }
}
