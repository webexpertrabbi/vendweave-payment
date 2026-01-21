<?php

namespace VendWeave\Gateway\Console;

use Illuminate\Console\Command;
use VendWeave\Gateway\Services\CertificationManager;

class CertRenewCommand extends Command
{
    protected $signature = 'vendweave:cert-renew';
    protected $description = 'Renew VendWeave certification before expiry';

    public function handle(): int
    {
        if (!CertificationManager::isEnabled()) {
            $this->warn('Certification system is disabled.');
            $this->line('Enable with: VENDWEAVE_CERTIFICATION_ENABLED=true');
            return self::SUCCESS;
        }

        // Check current status
        $status = CertificationManager::status();

        if (!$status) {
            $this->error('No active certification found.');
            $this->line('Request certification first with: php artisan vendweave:cert-request');
            return self::FAILURE;
        }

        if (($status['status'] ?? null) === CertificationManager::STATUS_REVOKED) {
            $this->error('Certification has been revoked and cannot be renewed.');
            $this->line('Request new certification with: php artisan vendweave:cert-request');
            return self::FAILURE;
        }

        $this->info('Current Certification:');
        $this->line("Badge: {$status['badge_code']}");
        $this->line("Expires: {$status['expires_at']}");
        $this->newLine();

        // Check if renewal is needed
        if (!empty($status['expires_at'])) {
            $expiresAt = \Carbon\Carbon::parse($status['expires_at']);
            $daysRemaining = now()->diffInDays($expiresAt, false);

            if ($daysRemaining > 30) {
                $this->warn("Certification doesn't expire for {$daysRemaining} days.");
                if (!$this->confirm('Renew anyway?', false)) {
                    $this->line('Renewal cancelled.');
                    return self::SUCCESS;
                }
            } else {
                $this->line("⏰ {$daysRemaining} days until expiry. Renewal recommended.");
            }
        }

        $this->line('Sending renewal request to VendWeave Authority...');

        $result = CertificationManager::renewCertification();

        if ($result === null) {
            $this->error('Renewal request failed.');
            $this->line('Check your credentials and try again.');
            return self::FAILURE;
        }

        $newStatus = $result['status'] ?? 'unknown';

        if ($newStatus === CertificationManager::STATUS_ACTIVE) {
            $this->newLine();
            $this->info('✅ Certification RENEWED!');
            $this->newLine();
            $this->line("Badge: <fg=green>{$result['badge_code']}</>");
            $this->line("New Expiry: {$result['expires_at']}");

            // Check for badge upgrade/downgrade
            $oldBadge = $status['badge_code'] ?? null;
            $newBadge = $result['badge_code'] ?? null;

            if ($oldBadge && $newBadge && $oldBadge !== $newBadge) {
                $oldTier = CertificationManager::getBadgeTier($oldBadge);
                $newTier = CertificationManager::getBadgeTier($newBadge);

                if ($newTier > $oldTier) {
                    $this->info("🎉 UPGRADED: {$oldBadge} → {$newBadge}");
                } elseif ($newTier < $oldTier) {
                    $this->warn("⚠️ DOWNGRADED: {$oldBadge} → {$newBadge}");
                    $this->line('Enable more features to regain higher certification.');
                }
            }
        } else {
            $this->error("Renewal failed. Status: {$newStatus}");
            $this->line('Message: ' . ($result['message'] ?? 'Unknown'));
        }

        return self::SUCCESS;
    }
}
