<?php

namespace App;

class UserValidator
{
    private const OPTIONS = [
        'nameMinLength' => 3,
        'passwordMinLength' => 6,
        'passwordContainNumbers' => false,
    ];

    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = array_merge(self::OPTIONS, $options);
    }

    public function validate(array $user): array
    {
        $errors = [];
        if (mb_strlen($user['name']) < $this->options['nameMinLength']) {
            $errors['nameMinLength'] = 'to small';
        }
        if (mb_strlen($user['password']) < $this->options['passwordMinLength']) {
            $errors['passwordMinLength'] = 'to small';
        }
        if ($this->options['passwordContainNumbers'] && !$this->hasNumber($user['password'])) {
            $errors['passwordContainNumbers'] = 'should contain at least one number';
        }
        return $errors;
    }

    private function hasNumber(string $subject): bool
    {
        return strpbrk($subject, '1234567890') !== false;
    }
}
