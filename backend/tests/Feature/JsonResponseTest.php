<?php

it('returns json 404 for missing api routes even with an html accept header', function () {
    $response = $this->get('/api/v1/does-not-exist', ['Accept' => 'text/html']);

    $response->assertNotFound()
        ->assertHeader('Content-Type', 'application/json');
});
