<?php

namespace Dashed\DashedEcommerceMyParcel\Controllers;

use Illuminate\Support\Facades\Storage;
use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;
use Dashed\DashedCore\Controllers\Frontend\FrontendController;

class MyParcelController extends FrontendController
{
    public function downloadLabels()
    {
        $myparcelOrders = MyParcelOrder::where('label_printed', 0)->get();

        $response = MyParcel::getLabelsFromShipments($myparcelOrders->pluck('shipment_id')->toArray());
        if (isset($response['labels'])) {
            $fileName = '/dashed/myparcel/labels/labels-' . time() . '.pdf';
            Storage::disk('dashed')->put($fileName, base64_decode($response['labels']));
            foreach ($myparcelOrders as $myparcelOrder) {
                $myparcelOrder->label_printed = 1;
                $myparcelOrder->save();
            }

            return Storage::disk('dashed')->download($fileName);
        } else {
            echo "<script>window.close();</script>";
        }
    }
}
