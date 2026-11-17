<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rules', function (Blueprint $t) {
            // Langages disponibles pour la règle, entourés d'espaces (« sigma splunk ») pour un filtre LIKE simple
            $t->string('langs', 160)->default(' sigma ');
            $t->string('native', 30)->nullable();
        });
        Schema::table('sources', function (Blueprint $t) {
            $t->boolean('minor')->default(false);
        });
        Schema::table('source_overrides', function (Blueprint $t) {
            $t->boolean('minor')->default(false);
        });
        DB::table('sources')->where('auto', true)->update(['minor' => true]);
        // Les règles déjà importées : langages recalculés au prochain import ; en attendant, tout est supposé disponible
        DB::table('rules')->where('kind', '!=', 'admin')->update(['langs' => ' sigma splunk elastic_lucene elastic_esql sentinel_kql falcon_logscale cortex_xql ']);
    }

    public function down(): void
    {
        Schema::table('rules', fn (Blueprint $t) => $t->dropColumn(['langs', 'native']));
        Schema::table('sources', fn (Blueprint $t) => $t->dropColumn('minor'));
        Schema::table('source_overrides', fn (Blueprint $t) => $t->dropColumn('minor'));
    }
};
