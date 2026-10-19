<?php

declare(strict_types=1);

use Sunrice\Admin\Support\Csv;

it('writes cells spreadsheets won\'t run as formulas', function () {
    expect(Csv::cell('=1+1'))->toBe("'=1+1")
        ->and(Csv::cell('-5'))->toBe("'-5")
        ->and(Csv::cell('@SUM(A1)'))->toBe("'@SUM(A1)")
        ->and(Csv::cell('plain'))->toBe('plain')
        ->and(Csv::cell(null))->toBe('')
        ->and(Csv::cell(true))->toBe('Yes')
        ->and(Csv::cell(3))->toBe('3');

    $stream = fopen('php://memory', 'w+');
    Csv::write($stream, ['a "quote"', '+cmd', null]);
    rewind($stream);

    expect(stream_get_contents($stream))->toBe("\"a \"\"quote\"\"\",'+cmd,\n");
});
