<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $routes = [
            [
                'state' => 'Lagos',
                'city_town' => 'LTV / Ikorodu / Berger / Iyana Oworo',
                'bus_location' => 'LTV / Ikorodu / Berger / Iyana Oworo',
                'contacts' => [
                    ['name' => 'Barr Olumide', 'phone' => '08051192255'],
                    ['name' => 'Bro Tobi', 'phone' => '09024009331'],
                ],
            ],
            [
                'state' => 'Ogun',
                'city_town' => 'Abeokuta',
                'bus_location' => 'Abeokuta',
                'contacts' => [
                    ['name' => 'Sis Doyin', 'phone' => '08027066367'],
                    ['name' => 'Adeniji Titilayo', 'phone' => '07033613734'],
                ],
            ],
            [
                'state' => 'Ogun',
                'city_town' => 'Sagamu',
                'bus_location' => 'Sagamu',
                'contacts' => [
                    ['name' => 'Bro Oyekunle', 'phone' => '08169282055'],
                ],
            ],
            [
                'state' => 'Ogun',
                'city_town' => 'Ijebu Ode',
                'bus_location' => 'Ijebu Ode',
                'contacts' => [
                    ['name' => 'Sis Bolutife', 'phone' => '08118147554'],
                ],
            ],
            [
                'state' => 'Ogun',
                'city_town' => 'Mowe/Ibafo',
                'bus_location' => 'Mowe/Ibafo',
                'contacts' => [
                    ['name' => 'Abimbola Talabi', 'phone' => '08080824466'],
                ],
            ],
            [
                'state' => 'Ogun',
                'city_town' => 'Sango-Ota',
                'bus_location' => 'Sango-Ota',
                'contacts' => [
                    ['name' => 'Christiana', 'phone' => '08067407109'],
                    ['name' => 'Bro Ope', 'phone' => '07062174369'],
                ],
            ],
            [
                'state' => 'Ondo',
                'city_town' => 'Akure',
                'bus_location' => 'Akure',
                'contacts' => [
                    ['name' => 'Bro Emmanuel Owoleye', 'phone' => '09029656110'],
                ],
            ],
            [
                'state' => 'Ondo',
                'city_town' => 'Ondo',
                'bus_location' => 'Ondo',
                'contacts' => [
                    ['name' => 'Adesola', 'phone' => '08060691855'],
                ],
            ],
            [
                'state' => 'Osun',
                'city_town' => 'Osogbo/Ilesa/Ife',
                'bus_location' => 'Osogbo/Ilesa/Ife',
                'contacts' => [
                    ['name' => 'Pastor Jide', 'phone' => '07039625188'],
                ],
            ],
            [
                'state' => 'Ekiti',
                'city_town' => 'Ado Ekiti',
                'bus_location' => 'Ado Ekiti',
                'contacts' => [
                    ['name' => 'Bro Ope', 'phone' => '08030638354'],
                ],
            ],
            [
                'state' => 'Ogbomosho',
                'city_town' => 'Ogbomoso/Ilorin',
                'bus_location' => 'Ogbomoso/Ilorin',
                'contacts' => [
                    ['name' => 'Pastor Egbeleye', 'phone' => '08088739817'],
                ],
            ],
            [
                'state' => 'Oyo',
                'city_town' => 'Ladigbolu Grammar School, Oyo',
                'bus_location' => 'Ladigbolu Grammar Schl. Oyo & Ishola Motors, opposite Ace Supermarket.',
                'contacts' => [
                    ['name' => 'Adebayo', 'phone' => '08063002910'],
                    ['name' => 'Seun', 'phone' => '07066353785'],
                ],
            ],
            [
                'state' => 'Ibadan',
                'city_town' => 'Challenge/Ojoo/Two Road/Oyo Motors',
                'bus_location' => 'Challenge/Ojoo/Two Road/Oyo Motors',
                'contacts' => [
                    ['name' => 'Pastor Daniel', 'phone' => '09122628411'],
                ],
            ],
        ];

        foreach ($routes as $index => $route) {
            $firstContact = $route['contacts'][0] ?? ['name' => null, 'phone' => null];

            DB::table('transportation_arrangements')->updateOrInsert(
                [
                    'program_name' => '72Hours',
                    'city_town' => $route['city_town'],
                    'state' => $route['state'],
                ],
                [
                    'event_title' => '72Hours June Edition Transportation',
                    'bus_location' => $route['bus_location'],
                    'bus_type' => null,
                    'passenger_capacity' => null,
                    'buses_available' => 1,
                    'driver_name' => null,
                    'driver_phone' => null,
                    'contact_person_name' => $firstContact['name'],
                    'contact_person_phone' => $firstContact['phone'],
                    'contacts' => json_encode($route['contacts']),
                    'is_active' => true,
                    'sort_order' => ($index + 1) * 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('transportation_arrangements')
            ->where('program_name', '72Hours')
            ->where('event_title', '72Hours June Edition Transportation')
            ->delete();
    }
};
