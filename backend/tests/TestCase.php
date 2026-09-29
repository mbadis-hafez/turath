<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Queue;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // QUEUE_CONNECTION=sync in phpunit.xml means a dispatched job normally
        // runs inline during the request that queued it. Faking by default
        // keeps a test that merely triggers a side-effect job (e.g. uploading
        // a file that queues OCR processing) from also depending on that job's
        // real external dependencies; a test of the job itself calls it
        // directly instead of going through the queue.
        Queue::fake();
    }
}
