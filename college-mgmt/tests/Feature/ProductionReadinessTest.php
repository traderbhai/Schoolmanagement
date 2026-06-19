<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    public function test_production_environment_template_declares_launch_safe_defaults(): void
    {
        $template = file_get_contents(base_path('.env.production.example'));

        $requiredLines = [
            'APP_NAME=EduManage',
            'APP_ENV=production',
            'APP_DEBUG=false',
            'DB_CONNECTION=mysql',
            'SESSION_DRIVER=redis',
            'SESSION_ENCRYPT=true',
            'FILESYSTEM_DISK=s3',
            'QUEUE_CONNECTION=redis',
            'CACHE_STORE=redis',
            'MAIL_MAILER=smtp',
            'MAIL_FROM_NAME="${APP_NAME}"',
            'ANTHROPIC_API_KEY=',
        ];

        foreach ($requiredLines as $requiredLine) {
            $this->assertStringContainsString($requiredLine, $template);
        }

        $this->assertStringNotContainsString('your_api_key_here', $template);
        $this->assertStringNotContainsString('APP_DEBUG=true', $template);
    }

    public function test_local_environment_example_avoids_fake_secret_values(): void
    {
        $template = file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('APP_NAME=EduManage', $template);
        $this->assertStringContainsString('QUEUE_CONNECTION=database', $template);
        $this->assertStringContainsString('SESSION_DRIVER=database', $template);
        $this->assertStringContainsString('CACHE_STORE=database', $template);
        $this->assertStringContainsString('ANTHROPIC_API_KEY=', $template);
        $this->assertStringNotContainsString('your_api_key_here', $template);
    }

    public function test_production_runbook_covers_required_operational_gates(): void
    {
        $runbook = file_get_contents(base_path('PRODUCTION_READINESS_CHECKLIST.md'));

        $requiredTopics = [
            'Environment',
            'Database',
            'Queue Workers',
            'Scheduler',
            'Storage And Files',
            'Mail And Notifications',
            'Security',
            'Release Gate',
            'php artisan test',
            'npm run build',
            'npm audit --audit-level=critical',
            'composer audit',
        ];

        foreach ($requiredTopics as $requiredTopic) {
            $this->assertStringContainsString($requiredTopic, $runbook);
        }
    }

    public function test_scheduler_registers_core_operational_jobs(): void
    {
        $schedule = file_get_contents(base_path('routes/console.php'));

        $commands = [
            'admission:deadline-reminders',
            'admission:followup-reminders',
            'admission:close-expired-windows',
            'accounts:mark-overdue-demands',
            'fees:apply-late-fees',
            'library:apply-fines',
        ];

        foreach ($commands as $command) {
            $this->assertStringContainsString("Schedule::command('{$command}')", $schedule);
        }
    }

    public function test_mail_sender_fallback_uses_product_brand(): void
    {
        $mailConfig = file_get_contents(config_path('mail.php'));

        $this->assertStringContainsString("env('APP_NAME', 'EduManage')", $mailConfig);
        $this->assertStringNotContainsString("env('APP_NAME', 'Laravel')", $mailConfig);
    }

    public function test_shared_sidebar_css_keeps_long_navigation_scrollable(): void
    {
        $css = file_get_contents(public_path('css/app.css'));

        $this->assertStringContainsString('.sidebar > .flex-grow-1', $css);
        $this->assertStringContainsString('min-height: 0;', $css);
        $this->assertStringContainsString('overflow-y: auto;', $css);
        $this->assertStringContainsString('.offcanvas[id="mobileSidebar"] .offcanvas-body', $css);
        $this->assertStringContainsString('max-height: calc(100vh - var(--topbar-height));', $css);
    }

    public function test_applicant_mobile_sidebar_uses_mobile_sidebar_class(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/applicant.blade.php'));

        $this->assertStringContainsString('offcanvas offcanvas-start sidebar-mobile', $layout);
        $this->assertStringNotContainsString('offcanvas offcanvas-start sidebar" tabindex="-1" id="mobileSidebar"', $layout);
    }
}
