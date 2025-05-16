<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueArray implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if(!is_array($value)) {
            $fail('Trường :attribute phải là một mảng.');
        }

        // Kiểm tra tính duy nhất của các phần tử trong mảng
        if (count($value) !== count(array_unique($value))) {
            $fail('Trường :attribute phải chứa những giá trị không trùng lặp nhau.');
        }
    }
}
