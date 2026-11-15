<?php

namespace App\Console\Commands;

use App\Services\DeploymentReadinessService;
use Illuminate\Console\Command;

class CheckDeployment extends Command
{
    protected $signature = 'app:check-deployment {--json : Print a machine-readable report without secret values}';

    protected $description = 'Read-only production configuration checks; not approval to go live or proof of external services';

    public function handle(DeploymentReadinessService $readiness): int
    {
        $report = $readiness->report();
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } else {
            $this->table(['Check', 'Status', 'Requirement / limitation'], array_map(
                fn ($check) => [$check['id'], strtoupper($check['status']), $check['message']], $report['checks']));
            $this->warn('Configuration checks are not go-live approval. Complete every manual acceptance item:');
            foreach ($report['manual_acceptance_required'] as $item) {
                $this->line('- '.$item);
            }
        }

        return $report['automatic_checks_passed'] ? self::SUCCESS : self::FAILURE;
    }
}
