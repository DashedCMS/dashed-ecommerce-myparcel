<div class="grid gap-2">
    @foreach($order->myParcelOrders as $myparcelOrder)
        @if($myparcelOrder->shipment_id)
            <span
                class="w-full justify-center bg-green-100 text-green-800 inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium">
                                Bestelling naar MyParcel verstuurd met ID: {{$myparcelOrder->shipment_id}}
                                </span>
        @elseif($myparcelOrder->error)
            <span
                class="w-full justify-center bg-red-100 text-red-800 inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium">
                                Bestelling niet naar MyParcel verstuurd met error: {{$myparcelOrder->error}}
                                </span>
        @else
            <span
                class="w-full justify-center bg-yellow-100 text-yellow-800 inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium">
                                Bestelling klaar gezet voor MyParcel, download label in het overzicht of verander hier de waardes
                                </span>
        @endif
        @if(!$loop->last)
            <hr>
        @endif
    @endforeach
</div>
