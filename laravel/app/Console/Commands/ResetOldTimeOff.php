<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetOldTimeOff extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:reset-old-time-off';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete the old time off in June';


    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::orderBy('idkey', 'asc')->get();
        // Delete the old time off in June
        $userListText = '';
        $index = 1;
        foreach ($users as $user) {
            $beforeHour = $user->last_year_time_off + $user->time_off_hours;
            $afterHour = 0;
            if ($user->last_year_time_off > 0) {
                $user->last_year_time_off = 0;
                $user->save();
                $afterHour = $user->time_off_hours;
            }
            if ($user->status == STATUS_IS_ACTIVE) {
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
        }

        $payload = [
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Đã thực hiện xóa giờ phép năm cũ:*"
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
