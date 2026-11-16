<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Corrections manuelles du catalogue : elles survivent aux imports du référentiel.
        Schema::create('source_overrides', function (Blueprint $t) {
            $t->string('source_id', 80)->primary();
            $t->string('label', 160);
            $t->string('family_id', 40);
            $t->string('description', 400)->default('');
            $t->timestamps();
        });
        Schema::create('playbook_overrides', function (Blueprint $t) {
            $t->string('id', 60)->primary();
            $t->string('label', 160);
            $t->string('description', 400)->default('');
            $t->json('techniques');
            $t->boolean('hidden')->default(false);
            $t->timestamps();
        });
        Schema::create('custom_rules', function (Blueprint $t) {
            $t->id();
            $t->string('uid', 64)->unique();
            $t->string('title', 250);
            $t->text('description');
            $t->string('level', 20);
            $t->json('sources');
            $t->json('techniques');
            $t->string('falsepositives', 500)->default('');
            $t->longText('sigma');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('pack_changes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('pack_id')->index();
            $t->string('kind', 10);
            $t->string('uid', 64);
            $t->string('title', 255);
            $t->string('level', 20)->default('');
            $t->string('techniques', 400)->default('');
        });
        Schema::create('usage_daily', function (Blueprint $t) {
            $t->date('day');
            $t->string('metric', 40);
            $t->unsignedInteger('hits')->default(0);
            $t->primary(['day', 'metric']);
        });
        Schema::create('radar_settings', function (Blueprint $t) {
            $t->string('name', 60)->primary();
            $t->text('value')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['radar_settings', 'usage_daily', 'pack_changes', 'custom_rules', 'playbook_overrides', 'source_overrides'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
