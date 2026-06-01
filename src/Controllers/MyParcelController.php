<?php

namespace Dashed\DashedEcommerceMyParcel\Controllers;

use App\Http\Controllers\Controller;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceMyParcel\Jobs\CreateShippingLabelsJob;

class MyParcelController extends Controller
{
    public function downloadLabels()
    {
        CreateShippingLabelsJob::dispatch(auth()->user())->onQueue('ecommerce');

        Notification::make()
            ->body('Labels worden aangemaakt, ze staan over een paar minuten klaar om te downloaden')
            ->success()
            ->send();

        return redirect()->back();
    }
}
