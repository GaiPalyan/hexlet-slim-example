<?php

namespace App;

use Faker\Factory;

class Generator2
{
    public static function generate($count)
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

    public function idGenerator($num1, $num2): int
    {
        return mt_rand($num1, $num2);
    }
}
