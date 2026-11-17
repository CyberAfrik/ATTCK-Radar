<?php

namespace App\Jobs;

use App\Mail\PlanExportMail;
use App\Services\PlanExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SendPlanExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [60, 300];

    public function __construct(public int $exportId) {}

    public function handle(PlanExporter $exporter): void
    {
        $export = DB::table('export_requests')->find($this->exportId);
        if (! $export || $export->status === 'sent') {
            return;
        }
        $plan = DB::table('plans')->find($export->plan_id);
        $files = $exporter->build(
            json_decode($plan->sources, true) ?: [], json_decode($plan->playbooks, true) ?: [],
            $plan->language, url('/p/'.$plan->token)
        );
        // Les fichiers ne sont pas joints : le message ne contient que les liens de téléchargement.
        @unlink($files['xlsx']);
        @unlink($files['layer']);
        Mail::to($export->email)->send(new PlanExportMail(
            $files['summary'], url('/p/'.$plan->token),
            url('/export/'.$plan->token.'/classeur'), url('/export/'.$plan->token.'/calque'),
            $export->marketing_consent ? url('/desinscription/'.$export->unsubscribe_token) : null,
        ));
        DB::table('export_requests')->where('id', $export->id)
            ->update(['status' => 'sent', 'sent_at' => now(), 'error' => null, 'updated_at' => now()]);
    }

    public function failed(?Throwable $e): void
    {
        DB::table('export_requests')->where('id', $this->exportId)
            ->update(['status' => 'failed', 'error' => Str::limit((string) $e?->getMessage(), 480), 'updated_at' => now()]);
    }
}
