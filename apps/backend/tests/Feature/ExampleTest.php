<?php

use Tests\TestCase;

uses(TestCase::class);

test('root endpoint / returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
