<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Response;

class CalendarFeedController extends Controller
{
    public function ical(): Response
    {
        $events = Event::where('status', '!=', 'cancelled')
            ->where('event_date', '>=', now()->subMonths(3))
            ->withCount('confirmedRegistrations')
            ->orderBy('event_date')
            ->get();

        $clubName = config('app.name', 'DivingClub');
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//DivingClub-Manager//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$clubName,
        ];

        foreach ($events as $event) {
            $dtStart = $this->formatDt($event->event_date, $event->event_time);
            $dtEnd = $this->formatDt(
                $event->end_date ?? $event->event_date,
                $event->end_time ?? $event->event_time
            );

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:event-'.$event->id.'@'.parse_url(config('app.url'), PHP_URL_HOST);
            $lines[] = 'DTSTART:'.$dtStart;
            $lines[] = 'DTEND:'.$dtEnd;
            $lines[] = 'SUMMARY:'.$this->escape($event->title);
            if ($event->location) {
                $lines[] = 'LOCATION:'.$this->escape($event->location);
            }
            $desc = $event->description ? strip_tags($event->description) : '';
            $attendance = $event->confirmed_registrations_count.($event->max_participants ? '/'.$event->max_participants : '').' registered';
            $desc = $attendance.($desc ? '\n'.$desc : '');
            $lines[] = 'DESCRIPTION:'.$this->escape($desc);
            $lines[] = 'URL:'.route('events.show', $event);
            $lines[] = 'DTSTAMP:'.$event->updated_at->format('Ymd\THis\Z');
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return response(implode("\r\n", $lines), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="calendar.ics"',
        ]);
    }

    private function formatDt($date, ?string $time): string
    {
        $dateStr = $date instanceof Carbon ? $date->format('Y-m-d') : (string) $date;
        $dt = $time ? "{$dateStr} {$time}" : $dateStr;

        return Carbon::parse($dt)->format('Ymd\THis');
    }

    private function escape(string $text): string
    {
        return str_replace(["\n", ',', ';'], ['\\n', '\\,', '\\;'], $text);
    }
}
