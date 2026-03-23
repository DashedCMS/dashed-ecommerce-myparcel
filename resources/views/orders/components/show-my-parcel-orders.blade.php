<div class="space-y-3">
    @forelse($order->myParcelOrders as $myparcelOrder)
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-start justify-between gap-4">
                <div class="grid gap-2 space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        @if($myparcelOrder->shipment_id)
                            <x-filament::badge color="success" icon="heroicon-m-check-circle">
                                Verstuurd naar MyParcel
                            </x-filament::badge>
                        @elseif($myparcelOrder->error)
                            <x-filament::badge color="danger" icon="heroicon-m-x-circle">
                                Fout bij versturen
                            </x-filament::badge>
                        @else
                            <x-filament::badge color="warning" icon="heroicon-m-clock">
                                Klaargezet
                            </x-filament::badge>
                        @endif
                    </div>

                    <div class="space-y-1 text-sm">
                        @if($myparcelOrder->shipment_id)
                            <p class="text-gray-950 dark:text-white">
                                <span class="font-medium">Shipment ID:</span>
                                {{ $myparcelOrder->shipment_id }}
                            </p>
                        @elseif($myparcelOrder->error)
                            <p class="text-danger-600 dark:text-danger-400">
                                {{ $myparcelOrder->error }}
                            </p>
                        @else
                            <p class="text-gray-600 dark:text-gray-400">
                                Bestelling is klaargezet voor MyParcel. Download het label in het overzicht of pas hier
                                de waardes aan.
                            </p>
                        @endif
                    </div>
                </div>

                <x-filament::icon-button
                    color="danger"
                    icon="heroicon-m-trash"
                    size="sm"
                    tooltip="Verwijder label"
                    wire:click="confirmDeleteMyParcelOrder({{ $myparcelOrder->id }})"
                />
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
            Er zijn nog geen MyParcel orders gekoppeld aan deze bestelling.
        </div>
    @endforelse

    <x-filament::modal
        id="delete-myparcel-order-modal"
        width="md"
        alignment="center"
        close-by-clicking-away="false"
    >
        <x-slot name="heading">
            MyParcel label verwijderen
        </x-slot>

        <x-slot name="description">
            Weet je zeker dat je dit MyParcel label wilt verwijderen? De gekoppelde track & trace wordt ook verwijderd.
        </x-slot>

        <x-slot name="footerActions">
            <x-filament::button
                color="gray"
                wire:click="closeDeleteModal"
            >
                Annuleren
            </x-filament::button>

            <x-filament::button
                color="danger"
                wire:click="deleteMyParcelOrder"
            >
                Ja, verwijderen
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</div>
