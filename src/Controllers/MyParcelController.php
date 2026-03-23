<?php

namespace Dashed\DashedEcommerceMyParcel\Controllers;

use Illuminate\Support\Facades\Storage;
use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;
use App\Http\Controllers\Controller;;

class MyParcelController extends Controller
{
    public function downloadLabels()
    {
        $myParcelOrders = MyParcelOrder::where('label_printed', 0)->get();

        $response = MyParcel::getLabelsFromShipments($myParcelOrders->pluck('shipment_id')->toArray());
        if (isset($response['labels'])) {
            $fileName = '/dashed/myparcel/labels/labels-' . time() . '.pdf';
            Storage::disk('dashed')->put($fileName, base64_decode($response['labels']));
            foreach ($myParcelOrders as $myParcelOrder) {
                $myParcelOrder->label_printed = 1;
                $myParcelOrder->save();
            }

            return Storage::disk('dashed')->download($fileName);
        } else {
            echo "<script>window.close();</script>";
        }
    }
}
