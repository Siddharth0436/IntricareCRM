<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\ContactPhone;
use Faker\Factory as Faker;

class ContactSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        for ($i = 1; $i <= 10; $i++) {
            // Create contact
            $contact = Contact::create([
                'name' => $faker->firstName,
                'email' => $faker->email,
                'gender' => $faker->randomElement(['male', 'female', 'other']),
                'is_active' => true,
                'merged_to' => null,
            ]);

            // Attach 1–2 emails
            for ($e = 1; $e <= rand(1, 2); $e++) {
                ContactEmail::create([
                    'contact_id' => $contact->id,
                    'email' => $faker->unique()->safeEmail,
                    // 'is_primary' => $e === 1,
                ]);
            }

            // Attach 1–2 phone numbers
            for ($p = 1; $p <= rand(1, 2); $p++) {
                ContactPhone::create([
                    'contact_id' => $contact->id,
                    'phone' => $faker->phoneNumber,
                    // 'type' => $faker->randomElement(['work', 'mobile', 'home']),
                    'is_primary' => $p === 1,
                ]);
            }
        }
    }
}
