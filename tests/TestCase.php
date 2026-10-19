<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Docs\ResponseRecorder;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('DOCS_CAPTURE')) {
            ResponseRecorder::listen($this->app);
        }
    }
}
