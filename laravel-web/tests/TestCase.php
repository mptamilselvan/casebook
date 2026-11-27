<?php

namespace Tests;

use App\Support\RoleAccess;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RoleAccess::flush();
        $this->withSession(['case_mode' => 'personal']);
    }
}
