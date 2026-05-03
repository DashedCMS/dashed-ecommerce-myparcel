<?php

namespace Dashed\DashedEcommerceMyParcel\Livewire\Orders;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\OrderTrackAndTrace;
use Dashed\DashedEcommerceMyParcel\Jobs\CreateMyParcelConceptOrdersJob;

class ShowMyParcelOrders extends Component
{
    public $order;

    public bool $showDeleteModal = false;
    public ?int $myParcelOrderIdToDelete = null;

    public function mount($order): void
    {
        $this->order = $order;
    }

    public function confirmDeleteMyParcelOrder(int $myParcelOrderId): void
    {
        $this->myParcelOrderIdToDelete = $myParcelOrderId;
        $this->showDeleteModal = true;

        $this->dispatch('open-modal', id: 'delete-myparcel-order-modal');
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->myParcelOrderIdToDelete = null;

        $this->dispatch('close-modal', id: 'delete-myparcel-order-modal');
    }

    public function requeueMyParcelOrder(int $myParcelOrderId): void
    {
        $myParcelOrder = $this->order->myParcelOrders()
            ->where('id', $myParcelOrderId)
            ->first();

        if (! $myParcelOrder) {
            Notification::make()
                ->title('MyParcel order niet gevonden')
                ->danger()
                ->send();

            return;
        }

        if (! $myParcelOrder->shipment_id) {
            $myParcelOrder->error = null;
            $myParcelOrder->save();

            CreateMyParcelConceptOrdersJob::dispatch()->onQueue('ecommerce');

            $this->order->refresh();

            Notification::make()
                ->title('Concept wordt aangemaakt bij MyParcel')
                ->body('De job is gestart - ververs deze pagina na een paar seconden om de status bij te werken.')
                ->success()
                ->send();

            return;
        }

        if (! $myParcelOrder->label_printed) {
            Notification::make()
                ->title('Label staat al in de wachtrij')
                ->body('Dit label wordt bij de volgende download in het overzicht meegenomen.')
                ->warning()
                ->send();

            return;
        }

        $myParcelOrder->label_printed = false;
        $myParcelOrder->save();

        $this->order->refresh();

        Notification::make()
            ->title('Label opnieuw in de wachtrij gezet')
            ->body('Het label wordt bij de volgende download in het overzicht opnieuw meegedownload.')
            ->success()
            ->send();
    }

    public function deleteMyParcelOrder(): void
    {
        if (! $this->myParcelOrderIdToDelete) {
            return;
        }

        $myParcelOrder = $this->order->myParcelOrders()
            ->where('id', $this->myParcelOrderIdToDelete)
            ->first();

        if (! $myParcelOrder) {
            $this->closeDeleteModal();

            Notification::make()
                ->title('MyParcel order niet gevonden')
                ->danger()
                ->send();

            return;
        }

        DB::transaction(function () use ($myParcelOrder) {
            if (! empty($myParcelOrder->track_and_trace[0])) {
                OrderTrackAndTrace::where('order_id', $this->order->id)
                    ->where('code', array_key_first($myParcelOrder->track_and_trace[0]))
                    ->delete();
            }

            $myParcelOrder->delete();
        });

        $this->order->refresh();

        $this->closeDeleteModal();

        Notification::make()
            ->title('MyParcel label verwijderd')
            ->body('De gekoppelde track & trace is ook verwijderd.')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('dashed-ecommerce-myparcel::orders.components.show-my-parcel-orders');
    }
}
