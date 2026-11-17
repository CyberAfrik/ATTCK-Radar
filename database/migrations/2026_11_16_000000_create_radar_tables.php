<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packs', function (Blueprint $t) {
            $t->id();
            $t->string('version', 40);
            $t->string('attack_version', 20);
            $t->string('sigmahq_commit', 64)->nullable();
            $t->json('counts');
            $t->json('conversion')->nullable();
            $t->timestamp('generated_at')->nullable();
            $t->timestamp('imported_at');
        });

        Schema::create('tactics', function (Blueprint $t) {
            $t->string('id', 12)->primary();
            $t->string('shortname', 60);
            $t->string('name', 120);
            $t->unsignedSmallInteger('position');
        });

        Schema::create('techniques', function (Blueprint $t) {
            $t->string('id', 12)->primary();
            $t->string('name', 200);
            $t->boolean('is_sub')->default(false);
            $t->string('parent_id', 12)->nullable()->index();
            $t->string('url', 200);
        });

        Schema::create('tactic_technique', function (Blueprint $t) {
            $t->string('tactic_id', 12);
            $t->string('technique_id', 12);
            $t->primary(['tactic_id', 'technique_id']);
        });

        Schema::create('source_families', function (Blueprint $t) {
            $t->string('id', 40)->primary();
            $t->string('label', 120);
            $t->unsignedSmallInteger('position');
        });

        Schema::create('sources', function (Blueprint $t) {
            $t->string('id', 80)->primary();
            $t->string('family_id', 40)->index();
            $t->string('label', 160);
            $t->string('description', 400)->default('');
            $t->json('products');
            $t->boolean('auto')->default(false);
            $t->unsignedInteger('rules_count')->default(0);
            $t->unsignedInteger('techniques_count')->default(0);
        });

        Schema::create('rules', function (Blueprint $t) {
            $t->id();
            $t->string('uid', 64)->unique();
            $t->string('origin', 30)->index();
            $t->string('title', 255);
            $t->text('description');
            $t->string('level', 20)->index();
            $t->unsignedTinyInteger('level_rank')->index();
            $t->string('status', 20);
            $t->string('author', 255)->default('');
            $t->string('rule_date', 10)->default('');
            $t->string('modified', 10)->default('')->index();
            $t->string('ls_product', 60)->nullable();
            $t->string('ls_service', 80)->nullable();
            $t->string('ls_category', 80)->nullable();
            $t->string('kind', 40);
            $t->string('upstream_url', 400);
            $t->string('license', 40);
            $t->json('cves');
            $t->json('refs');
            $t->json('falsepositives');
            $t->string('technique_list', 400)->default('');
            $t->longText('sigma');
            $t->longText('queries');
        });

        Schema::create('rule_source', function (Blueprint $t) {
            $t->unsignedBigInteger('rule_id');
            $t->string('source_id', 80);
            $t->primary(['rule_id', 'source_id']);
            $t->index('source_id');
        });

        Schema::create('rule_technique', function (Blueprint $t) {
            $t->unsignedBigInteger('rule_id');
            $t->string('technique_id', 12);
            $t->primary(['rule_id', 'technique_id']);
            $t->index('technique_id');
        });

        Schema::create('rule_tactic', function (Blueprint $t) {
            $t->unsignedBigInteger('rule_id');
            $t->string('tactic_id', 12);
            $t->primary(['rule_id', 'tactic_id']);
            $t->index('tactic_id');
        });

        Schema::create('playbooks', function (Blueprint $t) {
            $t->string('id', 60)->primary();
            $t->string('label', 160);
            $t->string('description', 400)->default('');
            $t->json('techniques');
            $t->unsignedSmallInteger('position');
        });

        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('token', 64)->unique();
            $t->json('sources');
            $t->json('playbooks');
            $t->string('language', 40);
            $t->string('pack_version', 40)->nullable();
            $t->timestamps();
        });

        Schema::create('export_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $t->string('email', 190)->index();
            $t->boolean('marketing_consent')->default(false);
            $t->timestamp('consent_at')->nullable();
            $t->string('unsubscribe_token', 64)->unique();
            $t->timestamp('unsubscribed_at')->nullable();
            $t->string('ip_hash', 64)->index();
            $t->string('status', 20)->default('queued')->index();
            $t->string('error', 500)->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['export_requests', 'plans', 'playbooks', 'rule_tactic', 'rule_technique', 'rule_source', 'rules',
            'sources', 'source_families', 'tactic_technique', 'techniques', 'tactics', 'packs'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
