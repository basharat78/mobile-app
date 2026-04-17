<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Carrier;
use App\Models\CarrierDocument;
use Illuminate\Support\Facades\Hash;

class OperationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create 5 Dispatchers
        $dispatchers = [];
        for ($i = 1; $i <= 5; $i++) {
            $dispatchers[] = User::create([
                'name' => "Dispatcher " . $i,
                'email' => "dispatcher{$i}@truckzap.com",
                'phone' => "555-010" . $i,
                'password' => Hash::make('password'),
                'role' => 'dispatcher',
                'email_verified_at' => now(),
            ]);
        }

        // 2. Create 10 Carriers
        $equipmentTypes = ['Dry Van', 'Reefer', 'Flatbed', 'Step Deck', 'Power Only'];
        $cities = ['Houston, TX', 'Chicago, IL', 'Atlanta, GA', 'Phoenix, AZ', 'Los Angeles, CA', 'Miami, FL', 'Dallas, TX', 'Seattle, WA', 'Denver, CO', 'New York, NY'];

        for ($i = 1; $i <= 10; $i++) {
            $user = User::create([
                'name' => "Carrier Team " . $i,
                'company_name' => "Logistics Co " . $i,
                'email' => "carrier{$i}@truckzap.com",
                'phone' => "555-020" . $i,
                'password' => Hash::make('password'),
                'role' => 'carrier',
                'email_verified_at' => now(),
            ]);

            // Create Carrier Profile
            $carrier = Carrier::create([
                'user_id' => $user->id,
                'status' => 'approved',
                'preferred_origin' => $cities[array_rand($cities)],
                'preferred_destination' => $cities[array_rand($cities)],
                'preferred_equipment' => $equipmentTypes[array_rand($equipmentTypes)],
                'min_rate' => rand(2, 5) * 100,
                'dispatcher_id' => $dispatchers[array_rand($dispatchers)]->id, // Assign to random dispatcher
            ]);

            // Create 3 Documents per carrier
            $docTypes = ['mc_authority', 'insurance', 'w9'];
            foreach ($docTypes as $type) {
                CarrierDocument::create([
                    'carrier_id' => $carrier->id,
                    'type' => $type,
                    'file_path' => "seeders/documents/{$type}.pdf",
                    'status' => 'approved',
                ]);
            }
        }
    }
}
