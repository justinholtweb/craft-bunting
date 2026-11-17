<?php

declare(strict_types=1);

use JustinHoltWeb\Bunting\Settings;

it('has sensible defaults', function () {
    $settings = new Settings;

    expect($settings->defaultScope)->toBe('user');
});
