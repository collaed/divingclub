<?php

namespace Tests\Unit;

use App\Http\Controllers\CalendarFeedController;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CalendarFeedHelperTest extends TestCase
{
    private CalendarFeedController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new CalendarFeedController;
    }

    // --- escape ---

    public function test_escape_newlines(): void
    {
        $this->assertSame('line1\\nline2', $this->invokeEscape("line1\nline2"));
    }

    public function test_escape_commas(): void
    {
        $this->assertSame('hello\\, world', $this->invokeEscape('hello, world'));
    }

    public function test_escape_semicolons(): void
    {
        $this->assertSame('a\\;b', $this->invokeEscape('a;b'));
    }

    public function test_escape_combined(): void
    {
        $this->assertSame('a\\, b\\; c\\nd', $this->invokeEscape("a, b; c\nd"));
    }

    // --- formatDt ---

    public function test_format_dt_with_date_and_time(): void
    {
        $date = Carbon::parse('2026-06-15');
        $result = $this->invokeFormatDt($date, '14:30');

        $this->assertSame('20260615T143000', $result);
    }

    public function test_format_dt_with_date_only(): void
    {
        $date = Carbon::parse('2026-06-15');
        $result = $this->invokeFormatDt($date, null);

        $this->assertSame('20260615T000000', $result);
    }

    public function test_format_dt_with_string_date(): void
    {
        $result = $this->invokeFormatDt('2026-12-25', '09:00');

        $this->assertSame('20261225T090000', $result);
    }

    // --- Helpers ---

    private function invokeEscape(string $text): string
    {
        $method = new ReflectionMethod(CalendarFeedController::class, 'escape');

        return $method->invoke($this->controller, $text);
    }

    private function invokeFormatDt(mixed $date, ?string $time): string
    {
        $method = new ReflectionMethod(CalendarFeedController::class, 'formatDt');

        return $method->invoke($this->controller, $date, $time);
    }
}
