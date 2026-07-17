<?php

use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;

/**
 * `createLabelForOrder` / `createConceptAndLabelForOrder` hit the live
 * MyParcel API (concept + label creation) and aren't mockable in this
 * package's test setup, so per the task brief we verify the two pure/mockable
 * building blocks instead:
 * - `sanitizeExtraOptions()` — the pure function that produces `$attrs['options']`.
 * - `applyOptionsToConsignment()` — verified against a spy object instead of
 *   a real SDK consignment.
 */
it('sanitizes overrides to only known extra options as json (insurance euro->centen)', function () {
    $sanitized = MyParcel::sanitizeExtraOptions(['signature' => true, 'insurance' => 50, 'bogus' => 'x']);

    expect($sanitized)->toBe(['signature' => true, 'insurance' => 5000]); // €50 -> 5000 centen; 'bogus' gefilterd
});

it('applies set options to the consignment via setters', function () {
    $spy = new class () {
        public array $calls = [];

        public function setSignature($v)
        {
            $this->calls['signature'] = $v;

            return $this;
        }

        public function setInsurance($v)
        {
            $this->calls['insurance'] = $v;

            return $this;
        }

        public function setAgeCheck($v)
        {
            $this->calls['age_check'] = $v;

            return $this;
        }

        public function setOnlyRecipient($v)
        {
            $this->calls['only_recipient'] = $v;

            return $this;
        }

        public function setReturn($v)
        {
            $this->calls['return'] = $v;

            return $this;
        }

        public function setSameDayDelivery($v)
        {
            $this->calls['same_day'] = $v;

            return $this;
        }

        public function setLargeFormat($v)
        {
            $this->calls['large_format'] = $v;

            return $this;
        }
    };

    MyParcel::applyOptionsToConsignment($spy, ['signature' => true, 'insurance' => 5000]);

    expect($spy->calls)->toMatchArray(['signature' => true, 'insurance' => 5000]);
});

it('applies no setters when options are false/zero', function () {
    $spy = new class () {
        public array $calls = [];

        public function setSignature($v)
        {
            $this->calls['signature'] = $v;

            return $this;
        }

        public function setInsurance($v)
        {
            $this->calls['insurance'] = $v;

            return $this;
        }
    };

    MyParcel::applyOptionsToConsignment($spy, ['signature' => false, 'insurance' => 0]);

    expect($spy->calls)->toBe([]);
});
