<?php

namespace App\Utils;

use Carbon\Carbon;

class Util
{
    const MESSAGE_VALIDATOR = [
        'required'    => 'Yêu cầu :attribute là bắt buộc.',
        'integer'     => 'Yêu cầu :attribute là số nguyên',
        'string'      => 'Yêu cầu :attribute là một chuỗi',
        'date'        => 'Trường :attribute không xác định ngày.',
        'after'       => 'Ngày nghỉ phải là ngày sau ngày hôm nay.',
        'exists'      => 'Trường :attribute không tồn tại',
        'array'       => 'Trường :attribute phải là một mảng',
        'date_format' => 'Trường :attribute phải có dạng Y-m-d.'
    ];

}
