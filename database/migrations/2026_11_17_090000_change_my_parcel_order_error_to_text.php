<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_my_parcel') || ! Schema::hasColumn('dashed__order_my_parcel', 'error')) {
            return;
        }

        // Foutmeldingen van de carrier kunnen langer zijn dan 255 tekens.
        Schema::table('dashed__order_my_parcel', function (Blueprint $table): void {
            $table->text('error')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__order_my_parcel') || ! Schema::hasColumn('dashed__order_my_parcel', 'error')) {
            return;
        }

        Schema::table('dashed__order_my_parcel', function (Blueprint $table): void {
            $table->string('error')->nullable()->change();
        });
    }
};
