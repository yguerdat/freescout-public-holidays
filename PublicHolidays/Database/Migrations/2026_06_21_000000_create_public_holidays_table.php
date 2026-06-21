<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePublicHolidaysTable extends Migration
{
    public function up()
    {
        Schema::create('public_holidays', function (Blueprint $table) {
            $table->increments('id');
            $table->date('date')->index();
            $table->smallInteger('year')->index();
            // Region the holiday belongs to, e.g. "CH-JU". Empty for global custom days.
            $table->string('canton', 10)->default('')->index();
            // Stable identifier of the holiday, e.g. "corpus_christi". Used for translations.
            $table->string('holiday_key', 60)->default('custom');
            // Human readable name stored in the configured default language.
            $table->string('name', 191);
            // national | cantonal | custom
            $table->string('type', 20)->default('cantonal');
            // Whether the row was added/edited by an admin (protected from regeneration).
            $table->boolean('is_custom')->default(false);
            $table->timestamps();

            $table->unique(['date', 'canton', 'holiday_key']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('public_holidays');
    }
}
