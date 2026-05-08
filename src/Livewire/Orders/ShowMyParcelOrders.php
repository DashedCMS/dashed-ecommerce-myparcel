<?php

namespace Dashed\DashedEcommerceMyParcel\Livewire\Orders;

use Dashed\DashedEcommerceCore\Models\OrderTrackAndTrace;
use Dashed\DashedEcommerceMyParcel\Jobs\CreateMyParcelConceptOrdersJob;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    /**
     * Markeert het MyParcel label als gedownload en stuurt het PDF terug
     * naar de browser. Wordt aangeroepen vanuit de download-knop in de
     * order-overzicht-blade — voorheen was die knop een directe link naar
     * de public storage URL waardoor `label_printed` nooit werd bijgewerkt.
     */
    public function downloadLabel(int $myParcelOrderId): ?BinaryFileResponse
    {
        $myParcelOrder = $this->order->myParcelOrders()
            ->where('id', $myParcelOrderId)
            ->first();

        if (! $myParcelOrder || ! $myParcelOrder->label_pdf_path) {
            Notification::make()
                ->title('Label niet gevonden')
                ->body('Er staat geen PDF klaar voor dit label.')
                ->danger()
                ->send();

            return null;
        }

        if (! Storage::disk('public')->exists($myParcelOrder->label_pdf_path)) {
            Notification::make()
                ->title('Label-bestand ontbreekt')
                ->body('Het PDF-bestand is niet meer aanwezig op de server. Maak het label opnieuw aan.')
                ->danger()
                ->send();

            return null;
        }

        if (! $myParcelOrder->label_printed) {
            $myParcelOrder->label_printed = true;
            $myParcelOrder->save();
        }

        $this->order->refresh();

        $filename = ($myParcelOrder->is_return ? 'retour-label-' : 'label-')
            . ($myParcelOrder->order->invoice_id ?? $myParcelOrder->id)
            . '.pdf';

        return Storage::disk('public')->download($myParcelOrder->label_pdf_path, $filename);
    }

    public function render()
    {
        return view('dashed-ecommerce-myparcel::orders.components.show-my-parcel-orders');
    }
}
