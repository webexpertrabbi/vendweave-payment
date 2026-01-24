<?php

namespace VendWeave\Gateway\Console;

use Illuminate\Console\Command;
use VendWeave\Gateway\Services\CertificationManager;

class CertRequestCommand extends Command
{
    protected $signature = 'vendweave:cert-request 
                            {--domain= : Domain or App ID to certify}
                            {--project= : Human-readable project name}';
    
    protected $description = 'Request VendWeave certification for this integration';

    public function handle(): int
    {
        if (!CertificationManager::isEnabled()) {
            $this->warn('Certification system is disabled.');
            $this->line('Enable with: VENDWEAVE_CERTIFICATION_ENABLED=true');
            return self::SUCCESS;
        }

        // Get domain
        $domain = $this->option('domain') ?? config('vendweave.certification.domain');
        if (empty($domain)) {
            $domain = $this->ask('Enter your domain or App ID (e.g., mystore.com)');
        }

        if (empty($domain)) {
            $this->error('Domain is required for certification.');
            return self::FAILURE;
        }

        // Get project name
        $projectName = $this->option('project') ?? config('vendweave.certification.project_name');
        if (empty($projectName)) {
            $projectName = $this->ask('Enter your project name (e.g., My Store)');
        }

        if (empty($projectName)) {
            $this->error('Project name is required for certification.');
            return self::FAILURE;
        }

        // Show qualification preview
        $qualifiedBadge = CertificationManager::detectQualifiedBadge();
        $badgeName = CertificationManager::getBadgeName($qualifiedBadge);

        $this->info('📋 Certification Request Preview');
        $this->newLine();
        $this->line("Domain: <fg=yellow>{$domain}</>");
        $this->line("Project: <fg=yellow>{$projectName}</>");
        $this->line("Qualified Badge: <fg=green>{$qualifiedBadge}</> ({$badgeName})");
        $this->newLine();

        if (!$this->confirm('Proceed with certification request?', true)) {
            $this->line('Certification request cancelled.');
            return self::SUCCESS;
        }

        // Make request
        $this->line('Sending certification request to VendWeave Authority...');

        $result = CertificationManager::requestCertification($domain, $projectName);

        if ($result === null) {
            $this->error('Certification request failed.');
            $this->line('Check your API credentials and try again.');
            $this->line('If the Authority API is not yet available, this is expected.');
            return self::FAILURE;
        }

        $status = $result['status'] ?? 'unknown';

        if ($status === CertificationManager::STATUS_ACTIVE) {
            $this->newLine();
            $this->info('✅ Certification APPROVED!');
            $this->newLine();
            $this->line("Badge: <fg=green>{$result['badge_code']}</>");
            $this->line("Hash: {$result['verification_hash']}");
            $this->line("Expires: {$result['expires_at']}");
            $this->newLine();
            $this->line('Embed your badge:');
            $this->line(CertificationManager::getBadgeHtml());
        } elseif ($status === CertificationManager::STATUS_PENDING) {
            $this->newLine();
            $this->warn('⏳ Certification PENDING');
            $this->line('Your request is being reviewed by VendWeave Authority.');
            $this->line('Check status later with: php artisan vendweave:cert-status');
        } else {
            $this->newLine();
            $this->error("Certification status: {$status}");
            $this->line('Message: ' . ($result['message'] ?? 'Unknown'));
        }

        return self::SUCCESS;
    }
}
