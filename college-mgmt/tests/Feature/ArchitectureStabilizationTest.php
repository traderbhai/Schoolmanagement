<?php

namespace Tests\Feature;

use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\TimetableEntry;
use App\Services\DashboardViewModelService;
use App\Services\FinanceAccessPolicyService;
use App\Services\PmcTimetableBridgeSyncService;
use App\Services\PmcTimetableGenerationService;
use App\Services\PmcTimetablePublishService;
use App\Services\PmcTimetableReadinessGateService;
use App\Services\PmcTimetableReadModelService;
use App\Services\PmcTimetableRevisionService;
use App\Services\PortalAccessPolicyService;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ArchitectureStabilizationTest extends TestCase
{
    public function test_modular_route_files_register_critical_routes(): void
    {
        $this->assertCount(1297, Route::getRoutes(), 'Route count changed unexpectedly; preserve URLs/names unless adding a deliberate feature route.');

        foreach ([
            'public.php',
            'applicant.php',
            'admission.php',
            'admin.php',
            'academics.php',
            'teacher.php',
            'student.php',
            'accounts.php',
            'approvals.php',
            'cmc.php',
        ] as $routeFile) {
            $this->assertFileExists(base_path('routes/'.$routeFile));
        }

        $criticalRoutes = [
            'academics.pmc.command',
            'academics.pmc.official-timetable.index',
            'academics.pmc.timetable-launch.index',
            'admission.dashboard',
            'admin.dashboard',
            'teacher.dashboard',
            'student.dashboard',
            'accounts.dashboard',
            'cmc.dashboard',
            'approvals.inbox',
        ];

        foreach ($criticalRoutes as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Expected route [{$routeName}] to remain registered.");
            $this->assertContains('auth', $route->gatherMiddleware(), "Expected route [{$routeName}] to remain authenticated.");
        }
    }

    public function test_generated_and_local_context_artifacts_are_not_tracked(): void
    {
        $root = dirname(base_path());
        exec('git -C '.escapeshellarg($root).' ls-files', $trackedFiles, $exitCode);

        $this->assertSame(0, $exitCode);

        $forbiddenPatterns = [
            '#(^|/)CODEX_PROJECT_CONTEXT\.md$#',
            '#(^|/)CLAUDE\.md$#',
            '#college-mgmt/graphify-out/cache/#',
            '#college-mgmt/graphify-out/\.graphify_(analysis|labels)\.json$#',
            '#college-mgmt/graphify-out/manifest\.json$#',
            '#(^|/)tmp_[^/]+\.txt$#',
        ];

        foreach ($trackedFiles as $file) {
            foreach ($forbiddenPatterns as $pattern) {
                $this->assertDoesNotMatchRegularExpression($pattern, str_replace('\\', '/', $file));
            }
        }
    }

    public function test_structural_service_boundaries_exist(): void
    {
        foreach ([
            FinanceAccessPolicyService::class,
            PortalAccessPolicyService::class,
            DashboardViewModelService::class,
            PmcTimetableGenerationService::class,
            PmcTimetablePublishService::class,
            PmcTimetableBridgeSyncService::class,
            PmcTimetableRevisionService::class,
            PmcTimetableReadModelService::class,
            PmcTimetableReadinessGateService::class,
        ] as $serviceClass) {
            $this->assertTrue(class_exists($serviceClass), "Expected structural service [{$serviceClass}] to exist.");
        }
    }

    public function test_no_unapproved_new_god_services_or_controllers_exist(): void
    {
        $allowedLargeFiles = [
            'app/Http/Controllers/Academics/PmcOperatingController.php',
            'app/Http/Controllers/Departmental/PmcTimetableController.php',
            'app/Services/AcademicPmcV004Service.php',
            'app/Services/AcademicPmcTimetableV041Service.php',
        ];

        $paths = collect([
            ...glob(base_path('app/Services/*.php')),
            ...$this->phpFilesUnder(base_path('app/Http/Controllers')),
        ]);

        $oversized = $paths
            ->map(fn (string $path) => [
                'path' => str_replace(str_replace('\\', '/', base_path()).'/', '', str_replace('\\', '/', $path)),
                'lines' => count(file($path)),
            ])
            ->filter(fn (array $file) => $file['lines'] > 1500 && ! in_array($file['path'], $allowedLargeFiles, true))
            ->values()
            ->all();

        $this->assertSame([], $oversized);
    }

    public function test_pmc_timetable_services_do_not_regress_past_current_size_budgets(): void
    {
        $budgets = [
            'app/Services/AcademicPmcTimetableV041Service.php' => 1987,
            'app/Services/PmcTimetableDashboardReadModelService.php' => 126,
            'app/Services/PmcTimetableDataReconciliationService.php' => 723,
            'app/Services/PmcTimetableStudentPortalService.php' => 359,
            'app/Services/PmcTimetableGenerationService.php' => 1181,
            'app/Services/PmcTimetablePublishService.php' => 522,
            'app/Services/PmcTimetableReadinessGateService.php' => 666,
            'app/Services/PmcTimetableReadModelService.php' => 688,
            'app/Services/PmcTimetableRevisionService.php' => 509,
            'app/Services/PmcTimetableBridgeSyncService.php' => 208,
        ];

        foreach ($budgets as $relativePath => $maxLines) {
            $this->assertLessThanOrEqual(
                $maxLines,
                count(file(base_path($relativePath))),
                "{$relativePath} exceeded its current stabilization line budget; extract new behavior into a focused service instead."
            );
        }
    }

    public function test_no_new_oversized_demo_seeders_are_added(): void
    {
        $allowedLargeSeeders = [];

        $oversized = collect(glob(base_path('database/seeders/*.php')))
            ->map(fn (string $path) => [
                'name' => basename($path),
                'lines' => count(file($path)),
            ])
            ->filter(fn (array $file) => $file['lines'] > 1000 && ! in_array($file['name'], $allowedLargeSeeders, true))
            ->values()
            ->all();

        $this->assertSame([], $oversized, 'New demo seeder growth should be split into module seeders.');
    }

    public function test_timetable_canonical_source_boundary_is_explicit(): void
    {
        $this->assertSame('academic_pmc_timetable_generation_items', (new AcademicPmcTimetableGenerationItem())->getTable());
        $this->assertSame('timetable_entries', (new TimetableEntry())->getTable());
        $this->assertTrue(class_exists(PmcTimetableBridgeSyncService::class));
    }

    public function test_pmc_controller_does_not_write_compatibility_timetable_rows_directly(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/Departmental/PmcTimetableController.php'));

        $this->assertStringNotContainsString('TimetableEntry::create', $controller);
        $this->assertStringNotContainsString('new TimetableEntry', $controller);
    }

    private function phpFilesUnder(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
