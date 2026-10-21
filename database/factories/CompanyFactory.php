<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'legal_name' => fake()->company(),
            'trade_name' => fake()->optional()->company(),
            'tin' => fake()->numerify('###-###-###-###'),
            'branch_code' => fake()->optional()->numerify('####'),
            'registered_address' => fake()->address(),
            'status' => 'active',
        ];
    }
}
