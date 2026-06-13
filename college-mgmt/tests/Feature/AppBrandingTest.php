<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppBrandingTest extends TestCase
{
    public function test_app_name_uses_commercial_brand(): void
    {
        $this->assertSame('EduManage', config('app.name'));
    }

    public function test_env_example_uses_commercial_brand(): void
    {
        $this->assertStringContainsString(
            'APP_NAME=EduManage',
            file_get_contents(base_path('.env.example'))
        );
    }

    public function test_layout_title_fallbacks_use_commercial_brand(): void
    {
        $layoutFiles = [
            resource_path('views/layouts/app.blade.php'),
            resource_path('views/layouts/guest.blade.php'),
        ];

        foreach ($layoutFiles as $layoutFile) {
            $contents = file_get_contents($layoutFile);

            $this->assertStringContainsString("config('app.name', 'EduManage')", $contents);
            $this->assertStringNotContainsString("config('app.name', 'Laravel')", $contents);
        }
    }
}
