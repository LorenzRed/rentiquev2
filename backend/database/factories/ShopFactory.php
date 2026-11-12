<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'shop_name' => fake()->company(),
            'description' => fake()->sentence(),
            'contact_number' => '09170000000',
            'email' => fake()->safeEmail(),
            'address' => fake()->streetAddress(),
            'formatted_address' => fake()->address(),
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Sample Barangay',
            'latitude' => 14.6507,
            'longitude' => 121.0494,
            'location_updated_at' => now(),
            'approval_status' => 'pending',
            'is_active' => true,
        ];
    }
}
