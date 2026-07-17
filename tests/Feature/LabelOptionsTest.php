<?php

use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;

it('declares the extra label options with defaults', function () {
    $order = makeOrder(); // helper: minimale Order met countryIsoCode 'NL', site_id
    $fields = MyParcel::extraLabelOptions($order);
    $keys = array_column($fields, 'name');
    expect($keys)->toContain('signature', 'insurance', 'age_check', 'only_recipient', 'return', 'same_day', 'large_format');
    $signature = collect($fields)->firstWhere('name', 'signature');
    expect($signature['type'])->toBe('boolean')->and($signature['group'])->toBe('extra')->and($signature['default'])->toBe(false);
    $insurance = collect($fields)->firstWhere('name', 'insurance');
    expect($insurance['type'])->toBe('amount');
});

it('maps stored options to a readable list, dropping false/empty', function () {
    $readable = MyParcel::readOptionsForDisplay(['signature' => true, 'insurance' => 5000, 'age_check' => false]);
    $byKey = collect($readable)->keyBy('key');
    expect($byKey->has('age_check'))->toBeFalse()
        ->and($byKey['signature']['value'])->toBe('Ja')
        ->and($byKey['signature']['label'])->toBe('Handtekening')
        ->and($byKey['insurance']['value'])->toBe('€ 50,00');
});
