<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LoadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dispatcher = \App\Models\User::where('role', 'dispatcher')->first() ?? \App\Models\User::factory()->create(['role' => 'dispatcher']);

        $loads = [
            [
                'dispatcher_id' => $dispatcher->id,
                'pickup_location' => 'Chicago, IL',
                'drop_location' => 'Dallas, TX',
                'miles' => 960,
                'rate' => 2400.00,
                'equipment_type' => 'Dry Van',
                'status' => 'available',
            ],
            [
                'dispatcher_id' => $dispatcher->id,
                'pickup_location' => 'Atlanta, GA',
                'drop_location' => 'Miami, FL',
                'miles' => 660,
                'rate' => 1850.00,
                'equipment_type' => 'Reefer',
                'status' => 'available',
            ],
            [
                'dispatcher_id' => $dispatcher->id,
                'pickup_location' => 'Los Angeles, CA',
                'drop_location' => 'Phoenix, AZ',
                'miles' => 370,
                'rate' => 1100.00,
                'equipment_type' => 'Flatbed',
                'status' => 'available',
            ],
            [
                'dispatcher_id' => $dispatcher->id,
                'pickup_location' => 'Seattle, WA',
                'drop_location' => 'Denver, CO',
                'miles' => 1300,
                'rate' => 3200.00,
                'equipment_type' => 'Dry Van',
                'status' => 'available',
            ],
        ];

        foreach ($loads as $load) {
            \App\Models\Load::create($load);
        }
    }
}
