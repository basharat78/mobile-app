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
        // 0. Create Master Admin
        User::updateOrCreate(
            ['email' => 'admin@truckzap.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // 1. Create 3 Dispatchers
        $dispatchers = [];
        for ($i = 1; $i <= 3; $i++) {
            $dispatchers[] = User::create([
                'name' => "Dispatcher Team " . $i,
                'email' => "dispatcher{$i}@truckzap.com",
                'phone' => "555-010" . $i,
                'password' => Hash::make('password'),
                'role' => 'dispatcher',
                'email_verified_at' => now(),
            ]);
        }

        // 2. Configuration for Diversity
        $equipmentTypes = ['Dry Van', 'Reefer', 'Flatbed', 'Step Deck', 'Power Only'];
        $cities = ['Houston, TX', 'Chicago, IL', 'Atlanta, GA', 'Phoenix, AZ', 'Los Angeles, CA', 'Miami, FL', 'Dallas, TX', 'Seattle, WA', 'Denver, CO', 'New York, NY'];

        // 3. Create 15 Carriers with different states
        for ($i = 1; $i <= 15; $i++) {
            $role = 'carrier';
            $user = User::create([
                'name' => "Carrier Contact " . $i,
                'company_name' => "Trucking Logistics " . $i,
                'email' => "carrier{$i}@truckzap.com",
                'phone' => "555-020" . $i,
                'password' => Hash::make('password'),
                'role' => $role,
                'email_verified_at' => now(),
            ]);

            // Determine Status and Assignment
            $status = 'approved';
            $dispatcherId = null;

            if ($i <= 6) {
                // 1-6: Assigned & Approved
                $status = 'approved';
                $dispatcherId = $dispatchers[($i - 1) % 3]->id;
            } elseif ($i <= 10) {
                // 7-10: Unassigned & Approved
                $status = 'approved';
                $dispatcherId = null;
            } else {
                // 11-15: Unassigned & Pending
                $status = 'pending';
                $dispatcherId = null;
            }

            // Create Carrier Profile
            $carrier = Carrier::create([
                'user_id' => $user->id,
                'status' => $status,
                'preferred_origin' => $cities[array_rand($cities)],
                'preferred_destination' => $cities[array_rand($cities)],
                'preferred_equipment' => $equipmentTypes[array_rand($equipmentTypes)],
                'min_rate' => rand(2, 5) * 100,
                'dispatcher_id' => $dispatcherId,
            ]);

            // Create Documents
            $docTypes = ['mc_authority', 'insurance', 'w9'];
            foreach ($docTypes as $type) {
                CarrierDocument::create([
                    'carrier_id' => $carrier->id,
                    'type' => $type,
                    'file_path' => "seeders/documents/{$type}.pdf",
                    'status' => ($status === 'approved' ? 'approved' : 'pending'),
                ]);
            }
        }
    }
}
