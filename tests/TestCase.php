<?php

namespace Tests;

use App\Support\Pwa\PwaIdentity;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        if ($this->app) {
            PwaIdentity::quitarPrefijoUrls();
        }
        parent::tearDown();
    }
}
