<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UpdateUserTimeOfHours extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:update-time-off-hours';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add 1 day leave for staff similar add 8 hours into time_off_hours';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::whereIn('status_working', [STATUS_PROBATION, STATUS_OFFICIAL])
            ->where('status', STATUS_IS_ACTIVE)
            ->orderBy('idkey', 'asc')
            ->get();
        $userListText = '';
        $index = 1;
        foreach ($users as $user) {
            $beforeHour = $user->last_year_time_off + $user->time_off_hours;
            $afterHour = 0;
            $user->time_off_hours += 8;
            $user->save();
            $afterHour = $user->last_year_time_off + $user->time_off_hours;

            $memberId = getSlackMemberId($user->email);
            $memberName = $user->fullname;
            $memberCode = $user->idkey;

            switch ($user->status_working) {
                case 1:
                    $statusWorking = 'Thực tập';
                    break;
                case 2:
                    $statusWorking = 'Thử việc';
                    break;
                case 3:
                    $statusWorking = 'Chính thức';
                    break;
                default:
                    $statusWorking = '';
                    break;
            }

            $userListText .= "{$index}. <@$memberId> $memberName ($memberCode) $statusWorking $beforeHour" . "h → " . $afterHour . "h\n";
            $index++;
        }
        $monthYear = now()->format('m/Y');
        $payload = [
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Đã thực hiện cộng giờ phép tháng {$monthYear}:*"
                    ]
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => $userListText
                    ]
                ]
            ]
        ];

        sendSlackNotification($payload);
    }
}
