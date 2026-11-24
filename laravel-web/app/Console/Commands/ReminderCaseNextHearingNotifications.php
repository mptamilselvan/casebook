<?php

namespace App\Console\Commands;

use App\Mail\NextHearingReminder;
use App\Models\CourtCase;
use App\Models\HearingReminder;
use App\Support\OneSignal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ReminderCaseNextHearingNotifications extends Command
{
    protected $signature = 'casebook:hearing-reminders';

    protected $description = 'Email and push the case owner 1 week, 3 days, 2 days, and 1 day before the next hearing';

    /**
     * @var array<int, string>
     */
    private array $offsets = [
        7 => '1 week',
        3 => '3 days',
        2 => '2 days',
        1 => '1 day',
    ];

    public function handle(OneSignal $oneSignal): int
    {
        $dates = [];
        foreach (array_keys($this->offsets) as $days) {
            $dates[today()->addDays($days)->toDateString()] = $days;
        }

        $cases = CourtCase::withoutGlobalScope('owner')
            ->with('user')
            ->whereNotNull('user_id')
            ->whereNotNull('next_hearing')
            ->where(function ($query) use ($dates) {
                foreach (array_keys($dates) as $date) {
                    $query->orWhereDate('next_hearing', $date);
                }
            })
            ->get();

        $sent = 0;

        foreach ($cases as $case) {
            $user = $case->user;
            if ($user === null || blank($user->email)) {
                continue;
            }

            $hearingOn = $case->next_hearing->toDateString();
            $daysBefore = $dates[$hearingOn] ?? null;
            if ($daysBefore === null) {
                continue;
            }

            $alreadySent = HearingReminder::query()
                ->where('case_id', $case->id)
                ->whereDate('hearing_on', $hearingOn)
                ->where('days_before', $daysBefore)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $when = $this->offsets[$daysBefore];
            $title = 'Hearing in '.$when;
            $message = $case->title.' is listed on '.$hearingOn.' at '.$case->court.'.';

            try {
                Mail::to($user)->send(new NextHearingReminder($case, $when));
                $oneSignal->notifyUser($user->id, $title, $message, [
                    'case_id' => $case->id,
                    'hearing_on' => $hearingOn,
                    'days_before' => $daysBefore,
                ]);
            } catch (Throwable $exception) {
                report($exception);
                $this->warn('Reminder failed for case '.$case->id.': '.$exception->getMessage());

                continue;
            }

            HearingReminder::query()->create([
                'case_id' => $case->id,
                'hearing_on' => $hearingOn,
                'days_before' => $daysBefore,
                'sent_at' => now(),
            ]);
            $sent++;
        }

        $this->info('Sent '.$sent.' hearing reminder'.($sent === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
