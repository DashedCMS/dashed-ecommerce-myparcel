<?php

namespace Dashed\DashedEcommerceMyParcel\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class MyParcelShippingMethodService extends Model
{
    use LogsActivity;

    protected static $logFillable = true;

    protected $table = 'dashed__myparcel_shipping_method_services';

    protected $fillable = [
        'myparcel_shipping_method_id',
        'name',
        'value',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function myparcelShippingMethod()
    {
        return $this->belongsTo(MyParcelShippingMethod::class);
    }

    public function MyParcelShippingMethodServiceOptions()
    {
        return $this->hasMany(MyParcelShippingMethodServiceOption::class);
    }
}
