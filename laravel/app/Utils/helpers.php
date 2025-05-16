<?php

use App\Models\DayOff;
use Carbon\Carbon;

if (!function_exists('errorResponse')) {
    /**
     * Hàm trả về response lỗi dưới dạng JSON.
     *
     * @param mixed $value
     * @return \Illuminate\Http\JsonResponse
     */
    function errorResponse($value)
    {
        return response()->json([
            'code'    => ERROR,
            'message' => '',
            'data'    => [
                $value => [
                    $value
                ]
            ]
        ], CLIENT_ERROR);
    }
}

if (!function_exists('calculateTimeSource')) {
    /**
     * Hàm tính giờ phép dùng là của năm nay hay năm ngoái
     *
     * @param mixed $leaveTime, $user, $leave
     * @return \Illuminate\Http\JsonResponse
     */
    function calculateTimeSource($user, $leaveTime)
    {
        if ($user->last_year_time_off == 0) {
            $timeSourceLeave = CURRENT_YEAR_TIME_OFF;
        } elseif ($user->last_year_time_off >= $leaveTime) {
            $timeSourceLeave = LAST_YEAR_TIME_OFF;
        } else {
            $timeSourceLeave = BOTH_TIME_OFF;
        }
        return $timeSourceLeave;
    }
}

if (!function_exists('calculateTimeOff')) {
    /**
     * Hàm tính giờ phép
     *
     * @param mixed $leaveTime, $user
     * @return \Illuminate\Http\JsonResponse
     */
    function calculateTimeOff($leaveTime, $user)
    {
        if ($user->last_year_time_off == 0) {
            $user->time_off_hours -= $leaveTime;
        } elseif ($user->last_year_time_off >= $leaveTime) {
            $user->last_year_time_off -= $leaveTime;
        } else {
            $remainingLeaveTime = $leaveTime - $user->last_year_time_off;
            $user->last_year_time_off = 0;
            $user->time_off_hours -= $remainingLeaveTime;
        }
        $user->time_off_hours = max($user->time_off_hours, 0);
        $user->save();
    }
}

if (!function_exists('checkDayOffExist')) {
    /**
     * Hàm kiểm tra đơn có tạo vào ngày nghỉ ko
     *
     * @param mixed $dayOff, $dayLeave
     * @return \Illuminate\Http\JsonResponse
     */
    function checkDayOffExist($dayOff, $dayLeave)
    {
        $dayOffExist = DayOff::select('day_off', 'status')
            ->where('is_delete', DELETED_N)
            ->where('day_off', $dayOff)
            ->first();
        if ($dayOffExist) {
            if ($dayOffExist->status == STATUS_DAY_OFF_DAY) {
                return errorResponse(NO_CREATE_LEAVE_ON_DAY_OFF);
            }
        } else {
            $dayLeaveCarbon = Carbon::createFromFormat('d/m/Y', $dayLeave);
            if ($dayLeaveCarbon->isSaturday() || $dayLeaveCarbon->isSunday()) {
                return errorResponse(DAY_IS_WEEKEND);
            }
        }
    }
}

if (!function_exists('getSlackMemberId')) {
    function getSlackMemberId($email)
    {
        try {
            $slackBotToken = config('services.slack.bot_token');
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $slackBotToken,
            ])->get('https://slack.com/api/users.lookupByEmail', [
                'email' => $email,
            ]);

            $data = $response->json();

            if ($response->successful() && $data['ok']) {
                return $data['user']['id'];
            } else {
                return null;
            }
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('sendSlackNotification')) {
    function sendSlackNotification($payload)
    {
        $slackWebhookUrl = config('services.slack.webhook_url');
        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->post($slackWebhookUrl, $payload);
    }
}

if (!function_exists('truncateTextByLines')) {
    function truncateTextByLines($text, $maxLines = 2, $maxChars = 102) {
        if (strpos($text, "\n") !== false) {
            $lines = explode("\n", trim($text));
            if (count($lines) >= $maxLines) {

                if (mb_strlen($lines[0], 'UTF-8') > $maxChars) {
                    return mb_substr($text, 0, $maxChars, 'UTF-8') . '...';
                }

                if (mb_strlen($lines[0], 'UTF-8') < $maxChars && mb_strlen($lines[0], 'UTF-8') > 61) {
                    return $lines[0] . "\n" . "...";
                }

                if (mb_strlen($lines[1], 'UTF-8') < 33) {
                    if (count($lines) == $maxLines) {
                        return $text;
                    }

                    if (count($lines) == $maxLines + 1) {
                        if (mb_strlen($lines[0], 'UTF-8') < 22 && mb_strlen($lines[2], 'UTF-8') < 33) {
                            return $text;
                        }

                        if (mb_strlen($lines[0], 'UTF-8') > 22) {
                            return $lines[0] . "\n" . $lines[1] . "\n" . "...";
                        }

                        if (mb_strlen($lines[2], 'UTF-8') > 33) {
                            return $lines[0] . "\n" . $lines[1] . "\n" . mb_substr($lines[2], 0, 40, 'UTF-8') . "\n" . "...";
                        }
                    }

                    if (count($lines) >= $maxLines + 1) {
                        return implode("\n", array_slice($lines, 0, $maxLines + 1)) . "\n...";
                    }
                }
                else
                {
                    return $lines[0] . "\n" . mb_substr($lines[1], 0, 40, 'UTF-8') . "\n" . "...";
                }
            }

            return $text;
        } else {
            if (mb_strlen($text, 'UTF-8') > $maxChars) {
                return mb_substr($text, 0, $maxChars, 'UTF-8') . '...';
            }
            return $text;
        }
    }
}
