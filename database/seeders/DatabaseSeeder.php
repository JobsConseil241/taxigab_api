<?php

namespace Database\Seeders;

use App\Enums\RideStatus;
use App\Models\Driver;
use App\Models\PricingFormula;
use App\Models\Ride;
use App\Models\RideStatusLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ------------------------------------------------------------ Admin
        $admin = User::updateOrCreate(
            ['email' => 'admin@taxigab.com'],
            [
                'name'      => 'Administrateur Taxi Gab',
                'phone'     => '+24166000001',
                'password'  => Hash::make('password'),
                'role'      => 'admin',
                'language'  => 'fr',
                'is_active' => true,
            ],
        );

        // ------------------------------------------------------------ Passagers
        $passenger1 = User::updateOrCreate(
            ['email' => 'user1@test.com'],
            [
                'name'      => 'Alice Mba',
                'phone'     => '+24166000010',
                'password'  => Hash::make('password'),
                'role'      => 'passenger',
                'language'  => 'fr',
                'is_active' => true,
            ],
        );

        $passenger2 = User::updateOrCreate(
            ['email' => 'user2@test.com'],
            [
                'name'      => 'Jean Obame',
                'phone'     => '+24166000011',
                'password'  => Hash::make('password'),
                'role'      => 'passenger',
                'language'  => 'fr',
                'is_active' => true,
            ],
        );

        // ------------------------------------------------------------ Chauffeurs (approuvés)
        // Coordonnées clés de Moanda
        $driversData = [
            [
                'email'         => 'driver1@taxigab.com',
                'name'          => 'Paul Ndong',
                'phone'         => '+24166000020',
                'license'       => 'LIC-MOA-0001',
                'lat'           => -1.5660,
                'lng'           => 13.2581,
                'vehicle' => [
                    'brand'        => 'Toyota',
                    'model'        => 'Yaris',
                    'year'         => 2019,
                    'color'        => 'Blanche',
                    'plate_number' => 'GA-MOA-001',
                    'category'     => 'standard',
                ],
            ],
            [
                'email'         => 'driver2@taxigab.com',
                'name'          => 'Grace Ondo',
                'phone'         => '+24166000021',
                'license'       => 'LIC-MOA-0002',
                'lat'           => -1.5700,
                'lng'           => 13.2620,
                'vehicle' => [
                    'brand'        => 'Hyundai',
                    'model'        => 'Accent',
                    'year'         => 2020,
                    'color'        => 'Grise',
                    'plate_number' => 'GA-MOA-002',
                    'category'     => 'standard',
                ],
            ],
            [
                'email'         => 'driver3@taxigab.com',
                'name'          => 'Yannick Mabiala',
                'phone'         => '+24166000022',
                'license'       => 'LIC-MOA-0003',
                'lat'           => -1.5630,
                'lng'           => 13.2550,
                'vehicle' => [
                    'brand'        => 'Nissan',
                    'model'        => 'Almera',
                    'year'         => 2018,
                    'color'        => 'Noire',
                    'plate_number' => 'GA-MOA-003',
                    'category'     => 'comfort',
                ],
            ],
        ];

        $drivers = [];
        foreach ($driversData as $d) {
            $user = User::updateOrCreate(
                ['email' => $d['email']],
                [
                    'name'      => $d['name'],
                    'phone'     => $d['phone'],
                    'password'  => Hash::make('password'),
                    'role'      => 'driver',
                    'language'  => 'fr',
                    'is_active' => true,
                ],
            );

            $driver = Driver::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'license_number'   => $d['license'],
                    'status'           => 'approved',
                    'is_online'        => true,
                    'current_lat'      => $d['lat'],
                    'current_lng'      => $d['lng'],
                    'current_heading'  => rand(0, 359),
                    'rating_avg'       => 4.5 + (random_int(0, 5) / 10),
                    'total_rides'      => random_int(20, 200),
                    'last_location_at' => now(),
                ],
            );

            Vehicle::updateOrCreate(
                ['plate_number' => $d['vehicle']['plate_number']],
                array_merge($d['vehicle'], [
                    'driver_id' => $driver->id,
                    'is_active' => true,
                ]),
            );

            $drivers[] = $driver;
        }

        // ------------------------------------------------------------ Formules tarifaires
        $formulaStandard = PricingFormula::updateOrCreate(
            ['slug' => 'standard'],
            [
                'name'            => 'Standard',
                'description'     => 'Course économique',
                'icon'            => 'car',
                'base_fare'       => 500,
                'per_km'          => 350,
                'per_minute_wait' => 50,
                'min_fare'        => 1000,
                'rounding_step'   => 100,
                'sort_order'      => 0,
                'is_active'       => true,
            ],
        );

        $formulaConfort = PricingFormula::updateOrCreate(
            ['slug' => 'confort'],
            [
                'name'            => 'Confort',
                'description'     => 'Véhicule récent et climatisé',
                'icon'            => 'car_luxury',
                'base_fare'       => 800,
                'per_km'          => 500,
                'per_minute_wait' => 75,
                'min_fare'        => 1500,
                'rounding_step'   => 100,
                'sort_order'      => 1,
                'is_active'       => true,
            ],
        );

        $formulaVip = PricingFormula::updateOrCreate(
            ['slug' => 'vip'],
            [
                'name'            => 'VIP',
                'description'     => 'Service premium, véhicule haut de gamme',
                'icon'            => 'star',
                'base_fare'       => 1200,
                'per_km'          => 700,
                'per_minute_wait' => 100,
                'min_fare'        => 2500,
                'rounding_step'   => 100,
                'sort_order'      => 2,
                'is_active'       => true,
            ],
        );

        $formulas = [$formulaStandard, $formulaConfort, $formulaVip];

        // ------------------------------------------------------------ Courses historiques
        $pickups = [
            ['lat' => -1.5660, 'lng' => 13.2581, 'label' => 'Centre-ville Moanda'],
            ['lat' => -1.5640, 'lng' => 13.2600, 'label' => 'Gare routière'],
            ['lat' => -1.5700, 'lng' => 13.2560, 'label' => 'Hôpital'],
        ];
        $dropoffs = [
            ['lat' => -1.5550, 'lng' => 13.2720, 'label' => 'Marché central'],
            ['lat' => -1.5800, 'lng' => 13.2480, 'label' => 'Quartier Mbaya'],
            ['lat' => -1.5720, 'lng' => 13.2450, 'label' => 'Collège Ste-Marie'],
        ];

        for ($i = 0; $i < 10; $i++) {
            $passenger = [$passenger1, $passenger2][$i % 2];
            $driver    = $drivers[$i % count($drivers)];
            $pickup    = $pickups[array_rand($pickups)];
            $dropoff   = $dropoffs[array_rand($dropoffs)];

            $distance = round(random_int(10, 60) / 10, 2); // 1.0 - 6.0 km
            $duration = (int) ceil($distance * 2);          // ~30 km/h

            $formula  = $formulas[$i % count($formulas)];
            $price    = max($formula->min_fare, ((int) ceil(($formula->base_fare + $distance * $formula->per_km) / 100)) * 100);

            // 8 completed / 2 cancelled
            $isCancelled = $i >= 8;
            $status      = $isCancelled ? RideStatus::CANCELLED : RideStatus::COMPLETED;

            // Mix payment : 7 cash + 3 mobile_money
            $paymentMethod = $i >= 7 ? 'mobile_money' : 'cash';

            $requestedAt = now()->subDays(random_int(1, 7))->subHours(random_int(0, 23));
            $acceptedAt  = (clone $requestedAt)->addMinutes(1);
            $completedAt = $isCancelled ? null : (clone $acceptedAt)->addMinutes($duration + 5);
            $cancelledAt = $isCancelled ? (clone $acceptedAt)->addMinutes(2) : null;

            $ride = Ride::create([
                'passenger_id'       => $passenger->id,
                'driver_id'          => $driver->id,
                'vehicle_id'         => $driver->activeVehicle?->id,
                'pricing_formula_id' => $formula->id,
                'formula_name'       => $formula->name,
                'status'             => $status,
                'pickup_lat'         => $pickup['lat'],
                'pickup_lng'         => $pickup['lng'],
                'pickup_address'     => $pickup['label'] . ', Moanda',
                'dropoff_lat'        => $dropoff['lat'],
                'dropoff_lng'        => $dropoff['lng'],
                'dropoff_address'    => $dropoff['label'] . ', Moanda',
                'distance_km'        => $distance,
                'duration_minutes'   => $duration,
                'price_estimated'    => $price,
                'price_final'        => $isCancelled ? null : $price,
                'currency'           => 'XAF',
                'payment_method'     => $paymentMethod,
                'payment_status'     => $isCancelled ? 'pending' : 'paid',
                'rating'             => $isCancelled ? null : random_int(4, 5),
                'requested_at'       => $requestedAt,
                'accepted_at'        => $acceptedAt,
                'started_at'         => $isCancelled ? null : (clone $acceptedAt)->addMinutes(4),
                'completed_at'       => $completedAt,
                'cancelled_at'       => $cancelledAt,
                'cancellation_reason' => $isCancelled ? 'Passager indisponible' : null,
            ]);

            RideStatusLog::create([
                'ride_id'    => $ride->id,
                'status'     => RideStatus::REQUESTED,
                'changed_by' => $passenger->id,
                'created_at' => $requestedAt,
            ]);

            RideStatusLog::create([
                'ride_id'    => $ride->id,
                'status'     => $status,
                'changed_by' => $isCancelled ? $passenger->id : $driver->user_id,
                'created_at' => $completedAt ?? $cancelledAt,
            ]);
        }

        $this->command?->info('Taxi Gab seed data created.');
        $this->command?->info('  Admin     : admin@taxigab.com / password');
        $this->command?->info('  Passagers : user1@test.com, user2@test.com / password');
        $this->command?->info('  Chauffeurs: driver1..3@taxigab.com / password');
    }
}
