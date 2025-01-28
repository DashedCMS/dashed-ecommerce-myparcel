<div class="grid gap-2">
    @foreach($order->myparcelOrders as $myparcelOrder)
        <span
            class="w-full justify-center bg-green-100 text-green-800 inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium">
                                Bestelling naar MyParcel verstuurd met ID: {{$myparcelOrder->shipment_id}}
                                </span>
        @if(!$loop->last)
            <hr>
        @endif
    @endforeach
</div>
