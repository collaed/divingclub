<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/** The month grid marks today's cell with .cal-today — see resources/scss/partials/_calendar.scss. */
class CalendarTodayHighlightTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_todays_cell_is_marked_in_the_month_view(): void
    {
        $response = $this->actingAs($this->createMemberUser())->get(route('events.index', ['view' => 'month']));

        $response->assertOk();
        $response->assertSee('cal-today', false);
    }

    public function test_a_day_outside_todays_month_is_never_marked(): void
    {
        $otherMonth = now()->addMonths(2)->format('Y-m-d');

        $response = $this->actingAs($this->createMemberUser())->get(route('events.index', ['view' => 'month', 'date' => $otherMonth]));

        $response->assertOk();
        $response->assertDontSee('cal-today', false);
    }
}
