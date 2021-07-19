<?php

namespace App;

class CourseValidator
{
    private const OPTIONS = [
        'minLength' => 5,
    ];
    private $options;
    public function __construct(array $options = [])
    {
        $this->options = array_merge(self::OPTIONS, $options);
    }

    public function validate(array $course): array
    {
        $errors = [];
        if (mb_strlen($course['title']) < $this->options['minLength']) {
            $errors['title'] = 'too small course title';
        }
        if (empty($course['paid'])) {
            $errors['paid'] = "Can't be blank";
        }
        if (empty($course['title'])) {
            $errors['title'] = "Can't be blank";
        }
        return $errors;
    }
}
