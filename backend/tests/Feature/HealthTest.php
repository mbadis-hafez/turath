<?php

it('returns 200 with database ok', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('app', 'Bidayaat')
        ->assertJsonPath('database', 'ok')
        ->assertJsonStructure(['status', 'app', 'version', 'time', 'database']);
});
