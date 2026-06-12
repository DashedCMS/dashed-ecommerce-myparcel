<?php

namespace Dashed\DashedEcommerceMyParcel\Support;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;
use Dashed\DashedEcommerceCore\Contracts\ShippingLabelProvider;

class MyParcelShippingProvider implements ShippingLabelProvider
{
    public function key(): string
    {
        return 'myparcel';
    }

    public function label(): string
    {
        return 'MyParcel';
    }

    public function failedOrders(): array
    {
        $siteId = Sites::getActive();

        return MyParcelOrder::whereNotNull('error')
            ->where('label_printed', 0)
            ->whereHas('order', fn ($q) => $q->where('site_id', $siteId))
            ->with('order')
            ->get()
            ->map(fn (MyParcelOrder $o) => [
                'id' => $o->id,
                'order_id' => $o->order_id,
                'invoice_id' => $o->order?->invoice_id,
                'error' => (string) $o->error,
            ])
            ->all();
    }

    public function retry(int $id): void
    {
        $order = MyParcelOrder::find($id);
        if ($order) {
            $order->error = null;
            $order->save();
        }
    }

    public function syncOrderStatuses(Order $order): int
    {
        return MyParcel::syncShipmentStatusesForOrder($order);
    }

    public function hasLabelsForOrder(Order $order): bool
    {
        return MyParcelOrder::where('order_id', $order->id)
            ->whereNotNull('shipment_id')
            ->exists();
    }
}
