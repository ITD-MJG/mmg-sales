<?php

namespace Database\Seeders;

use App\Models\Principal;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Initial Marketing principal responsibilities, agreed 2026-11-25.
 *
 * Matched by name so it can run on any database. A missing user or principal
 * is skipped, never created. Idempotent: re-running never duplicates a row.
 *
 * `Moh. Reyyan Puja Laksana` has no agreed mapping yet; assign that user
 * through the user form.
 */
class MarketingPrincipalAssignmentSeeder extends Seeder
{
    private const ASSIGNMENTS = [
        'Ahmad Soni' => ['Sysmex', 'Genolution', 'Genesystem', 'Mandelab', 'Tecan'],
        'Reza Muhammad Rifki' => ['AFI', 'Pekybio', 'Infitek'],
    ];

    public function run(): void
    {
        foreach (self::ASSIGNMENTS as $userName => $principalNames) {
            $user = User::query()->where('name', $userName)->first();

            if (! $user) {
                continue;
            }

            $ids = Principal::query()->whereIn('name', $principalNames)->pluck('id')->all();

            if ($ids !== []) {
                $user->principals()->syncWithoutDetaching($ids);
            }
        }
    }
}
