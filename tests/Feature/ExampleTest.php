<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_root_redirects_to_the_single_login_gate(): void
    {
        $this->get('/')->assertRedirect('/masuk');
    }
}
