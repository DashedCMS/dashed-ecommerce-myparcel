<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// Voegt velden toe voor het per-bestelling aanmaken van verzendlabels en
// retourlabels. is_return markeert een MyParcelOrder als retourzending,
// is_label_email_sent houdt bij of de retourmail al verstuurd is, en
// personal_note bevat de optionele notitie aan de klant.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashed__order_my_parcel', function (Blueprint $table) {
            if (! Schema::hasColumn('dashed__order_my_parcel', 'is_return')) {
                $table->boolean('is_return')
                    ->after('error')
                    ->default(false);
            }

            if (! Schema::hasColumn('dashed__order_my_parcel', 'is_label_email_sent')) {
                $table->boolean('is_label_email_sent')
                    ->after('is_return')
                    ->default(false);
            }

            if (! Schema::hasColumn('dashed__order_my_parcel', 'personal_note')) {
                $table->text('personal_note')
                    ->after('is_label_email_sent')
                    ->nullable();
            }

            if (! Schema::hasColumn('dashed__order_my_parcel', 'label_pdf_path')) {
                $table->string('label_pdf_path')
                    ->after('personal_note')
                    ->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('dashed__order_my_parcel', function (Blueprint $table) {
            if (Schema::hasColumn('dashed__order_my_parcel', 'label_pdf_path')) {
                $table->dropColumn('label_pdf_path');
            }
            if (Schema::hasColumn('dashed__order_my_parcel', 'personal_note')) {
                $table->dropColumn('personal_note');
            }
            if (Schema::hasColumn('dashed__order_my_parcel', 'is_label_email_sent')) {
                $table->dropColumn('is_label_email_sent');
            }
            if (Schema::hasColumn('dashed__order_my_parcel', 'is_return')) {
                $table->dropColumn('is_return');
            }
        });
    }
};
