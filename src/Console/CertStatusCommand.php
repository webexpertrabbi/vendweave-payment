<?php

namespace VendWeave\Gateway\Console;

use Illuminate\Console\Command;
use VendWeave\Gateway\Services\CertificationManager;

class CertStatusCommand extends Command
{
    protected $signature = 'vendweave:cert-status';
    protected $description = 'Check current VendWeave certification status';

    public function handle(): int
    {
        if (!CertificationManager::isEnabled()) {
            $this->warn('Certification system is disabled.');
            $this->line('Enable with: VENDWEAVE_CERTIFICATION_ENABLED=true');
            return self::SUCCESS;
        }

        $this->info('📋 VendWeave Certification Status');
        $this->newLine();

        // Show detected qualification
        $qualifiedBadge = CertificationManager::detectQualifiedBadge();
        $badgeName = CertificationManager::getBadgeName($qualifiedBadge);
        $tier = CertificationManager::getBadgeTier($qualifiedBadge);

        $this->line("🏅 <fg=yellow>Qualified Badge:</> {$qualifiedBadge} ({$badgeName})");
        $this->line("📊 <fg=yellow>Tier Level:</> {$tier}/5");
        $this->newLine();

        // Show feature snapshot
        $snapshot = CertificationManager::getFeatureSnapshot();
        
        $this->line('<fg=cyan>Feature Detection:</>');
        $this->table(
            ['Feature', 'Status'],
            [
                ['Base Integration', $snapshot['features']['base'] ? '✅ Yes' : '❌ No'],
                ['Reference Strict Mode', $snapshot['features']['reference_strict'] ? '✅ Yes' : '❌ No'],
                ['Governance Engine', $snapshot['features']['governance'] ? '✅ Yes' : '❌ No'],
                ['Financial Engine', $snapshot['features']['financial'] ? '✅ Yes' : '❌ No'],
                ['Currency Normalization', $snapshot['features']['currency'] ? '✅ Yes' : '❌ No'],
            ]
        );

        $this->newLine();
        $this->line('<fg=cyan>Service Availability:</>');
        $this->table(
            ['Service', 'Available'],
            [
                ['TransactionVerifier', $snapshot['services']['transaction_verifier'] ? '✅' : '❌'],
                ['ReferenceGovernor', $snapshot['services']['reference_governor'] ? '✅' : '❌'],
                ['FinancialRecordManager', $snapshot['services']['financial_record_manager'] ? '✅' : '❌'],
                ['CurrencyNormalizer', $snapshot['services']['currency_normalizer'] ? '✅' : '❌'],
            ]
        );

        // Check remote status
        $this->newLine();
        $status = CertificationManager::status();

        if ($status) {
            $this->line('<fg=cyan>Remote Certification Status:</>');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Status', $status['status'] ?? 'unknown'],
                    ['Badge Code', $status['badge_code'] ?? 'none'],
                    ['Project', $status['project_name'] ?? 'N/A'],
                    ['Domain', $status['domain'] ?? 'N/A'],
                    ['Issued At', $status['issued_at'] ?? 'N/A'],
                    ['Expires At', $status['expires_at'] ?? 'N/A'],
                ]
            );

            if (($status['status'] ?? null) === CertificationManager::STATUS_REVOKED) {
                $this->error('⚠️ Certification has been REVOKED');
                $this->line('Reason: ' . ($status['revoke_reason'] ?? 'Unknown'));
            }
        } else {
            $this->warn('No remote certification found.');
            $this->line('Request certification with: php artisan vendweave:cert-request');
        }

        return self::SUCCESS;
    }
}
