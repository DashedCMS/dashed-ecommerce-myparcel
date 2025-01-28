<?php

namespace Dashed\DashedEcommerceMyParcel\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Dashed\DashedEcommerceCore\Models\Order;

class MyParcelOrder extends Model
{
    use LogsActivity;

    protected static $logFillable = true;

    protected $table = 'dashed__order_my_parcel';

    protected $fillable = [
        'order_id',
        'shipment_id',
        'label',
        'label_url',
        'track_and_trace',
        'label_printed',
    ];

    protected $casts = [
        'track_and_trace' => 'array',
        'label_printed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
