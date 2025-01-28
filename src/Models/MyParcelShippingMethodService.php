<?php

namespace Dashed\DashedEcommerceMyParcel\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class MyParcelShippingMethodService extends Model
{
    use LogsActivity;

    protected static $logFillable = true;

    protected $table = 'dashed__my_parcel_shipping_method_services';

    protected $fillable = [
        'my_parcel_shipping_method_id',
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

    public function myParcelShippingMethod()
    {
        return $this->belongsTo(MyParcelShippingMethod::class);
    }

    public function myParcelShippingMethodServiceOptions()
    {
        return $this->hasMany(MyParcelShippingMethodServiceOption::class);
    }
}
