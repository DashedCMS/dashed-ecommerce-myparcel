<?php

namespace Dashed\DashedEcommerceMyParcel\Livewire\Orders;

use Livewire\Component;

class ShowMyParcelOrders extends Component
{
    public $order;

    public function mount($order)
    {
        $this->order = $order;
    }

    public function render()
    {
        return view('dashed-ecommerce-myparcel::orders.components.show-my-parcel-orders');
    }
}
