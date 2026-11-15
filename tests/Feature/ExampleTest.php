<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_sends_visitors_to_the_events(): void
    {
        $this->get(route('home'))->assertRedirect('/en');
        $this->withUnencryptedCookie('locale', 'ar')->get(route('home'))->assertRedirect('/ar');
    }
}
