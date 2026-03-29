<?php

test('guest is redirected to login from root', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
