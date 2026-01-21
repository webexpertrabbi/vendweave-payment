<?php

namespace VendWeave\Gateway\Console;

use Illuminate\Console\Command;
use VendWeave\Gateway\Services\CertificationManager;

class CertVerifyCommand extends Command
{
    protected $signature = 'vendweave:cert-verify {hash : The verification hash to check}';
    protected $description = 'Verify a VendWeave certification badge hash';

    public function handle(): int
    {
        $hash = $this->argument('hash');

        $this->line("Verifying badge hash: {$hash}");
        $this->newLine();

        $result = CertificationManager::verifyBadge($hash);

        if ($result === null) {
            $this->error('Verification request failed.');
            $this->line('The Authority API may be unavailable.');
            return self::FAILURE;
        }

        $valid = $result['valid'] ?? false;

        if ($valid) {
            $this->info('✅ Badge is VALID');
            $this->newLine();

            $this->table(
                ['Field', 'Value'],
                [
                    ['Project', $result['project_name'] ?? 'N/A'],
                    ['Domain', $result['domain'] ?? 'N/A'],
                    ['Badge Code', $result['badge_code'] ?? 'N/A'],
                    ['Badge Name', $result['badge_name'] ?? 'N/A'],
                    ['Status', $result['status'] ?? 'N/A'],
                    ['SDK Version', $result['sdk_version'] ?? 'N/A'],
                    ['Issued At', $result['issued_at'] ?? 'N/A'],
                    ['Expires At', $result['expires_at'] ?? 'N/A'],
                ]
            );

            if (!empty($result['features'])) {
                $this->newLine();
                $this->line('Certified Features:');
                foreach ($result['features'] as $feature) {
                    $this->line("  • {$feature}");
                }
            }
        } else {
            $status = $result['status'] ?? 'invalid';

            if ($status === CertificationManager::STATUS_REVOKED) {
                $this->error('❌ Badge is REVOKED');
                $this->line('Revoked at: ' . ($result['revoked_at'] ?? 'Unknown'));
                $this->line('Reason: ' . ($result['reason'] ?? 'Unknown'));
            } elseif ($status === CertificationManager::STATUS_EXPIRED) {
                $this->warn('⏰ Badge is EXPIRED');
                $this->line('Expired at: ' . ($result['expires_at'] ?? 'Unknown'));
            } else {
                $this->error('❌ Badge is INVALID');
                $this->line('Message: ' . ($result['message'] ?? 'Unknown'));
            }
        }

        return self::SUCCESS;
    }
}
