<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/** Tâches planifiées : veille de version ATT&CK, import hebdomadaire, purge de rétention, alertes. */
class Watchdog
{
    public function latestAttack(): ?string
    {
        try {
            $index = Http::timeout(8)->get(config('radar.attack_index'))->throw()->json();
            foreach ($index['collections'] ?? [] as $c) {
                if (($c['name'] ?? '') === 'Enterprise ATT&CK') {
                    $versions = array_column($c['versions'] ?? [], 'version');
                    usort($versions, 'version_compare');

                    return end($versions) ?: null;
                }
            }
        } catch (Throwable $e) {
            return null;
        }

        return null;
    }

    public function checkAttackVersion(): void
    {
        $current = DB::table('packs')->orderByDesc('id')->value('attack_version');
        $latest = $this->latestAttack();
        if (! $current || ! $latest || version_compare($latest, $current, '<=')) {
            return;
        }
        if ($this->setting('attack_notified') === $latest) {
            return;
        }
        $this->alert('MITRE ATT&CK '.$latest.' est disponible',
            "MITRE a publié ATT&CK $latest. ATT&CK Radar utilise la version $current.\n\nLe paquet de référentiel est reconstruit chaque lundi : la nouvelle version sera prise en compte au prochain import. Vous pouvez aussi lancer le workflow GitHub puis « Importer maintenant » depuis l'administration.");
        $this->setting('attack_notified', $latest);
    }

    public function importPack(): string
    {
        $url = config('radar.pack_url');
        if (! $url) {
            return 'Aucune adresse de paquet configurée.';
        }
        try {
            $code = Artisan::call('radar:import', ['--url' => $url]);
            $out = trim(Artisan::output());
            if ($code !== 0) {
                throw new \RuntimeException($out ?: 'code '.$code);
            }

            return $out ?: 'Import terminé.';
        } catch (Throwable $e) {
            $msg = Str::limit($e->getMessage(), 400);
            $this->alert('Échec de la mise à jour du référentiel', "L'import du référentiel a échoué. L'ancienne version reste en ligne.\n\nDétail : $msg");

            return 'Échec de l\'import : '.$msg;
        }
    }

    /** Supprime les demandes d'export plus anciennes que la durée de conservation, avec leurs plans. */
    public function purgeOld(): int
    {
        $days = (int) config('radar.retention_days');
        if ($days <= 0) {
            return 0;
        }
        $ids = DB::table('export_requests')->where('created_at', '<', now()->subDays($days))->pluck('plan_id');

        return $this->deletePlans($ids->all());
    }

    public function purgeEmail(string $email): int
    {
        return $this->deletePlans(DB::table('export_requests')->where('email', $email)->pluck('plan_id')->all());
    }

    private function deletePlans(array $planIds): int
    {
        if (! $planIds) {
            return 0;
        }
        $n = DB::table('export_requests')->whereIn('plan_id', $planIds)->delete();
        DB::table('plans')->whereIn('id', $planIds)->delete();

        return $n;
    }

    public function alert(string $subject, string $body): void
    {
        foreach (config('radar.admins') as $to) {
            try {
                Mail::raw($body."\n\n".url('/admin'), fn ($m) => $m->to($to)->subject('[ATT&CK Radar] '.$subject));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    public function setting(string $name, ?string $value = null): ?string
    {
        if (func_num_args() === 2) {
            DB::table('radar_settings')->updateOrInsert(['name' => $name], ['value' => $value]);

            return $value;
        }

        return DB::table('radar_settings')->where('name', $name)->value('value');
    }
}
