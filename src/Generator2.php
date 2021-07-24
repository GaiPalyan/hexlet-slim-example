<?php

namespace App;

use Exception;
use Faker\Factory;

class Generator2
{
    public static function generate($count): array
    {
        $range = range(1, $count - 2);
        $numbers = collect($range)->shuffle(1)->toArray();

        $faker = \Faker\Factory::create();
        $faker->seed(1234);
        $users = [];
        for ($i = 0; $i < $count - 2; $i++) {
            $users[] = [
                'id' => $numbers[$i],
                'firstName' => $faker->firstName,
                'lastName' => $faker->lastName,
                'email' => $faker->email
            ];
        }

        $users[] = [
            'id' => 99,
            'firstName' => $faker->firstName,
            'lastName' => $faker->lastName,
            'email' => $faker->email
        ];

        $users[] = [
            'id' => 100,
            'firstName' => $faker->firstName,
            'lastName' => $faker->lastName,
            'email' => $faker->email
        ];

        return $users;
    }

    /**
     * @throws Exception
     */
    public function idGenerator($num1, $num2): int
    {
        return random_int($num1, $num2);
    }
}
