import { existsSync } from 'node:fs';
import { spawnSync } from 'node:child_process';

const php = process.env.PHP_BINARY
  || (existsSync('C:/tmp/php-8.5.7/php.exe') ? 'C:/tmp/php-8.5.7/php.exe' : 'php');

const suites = {
  timetable: [
    'tests/Unit/TimetableOptimizationServiceTest.php',
    'tests/Unit/TimetableCanonicalCompletionServicesTest.php',
    'tests/Feature/AcademicsPmcTimetableV041Test.php',
    'tests/Feature/AcademicsPmcTimetableV043Test.php',
    'tests/Feature/AcademicsPmcTimetableV092Test.php',
    'tests/Feature/ProgramChairLegacyTimetableIntegrityTest.php',
    'tests/Feature/AdminDashboardCanonicalTimetableTest.php',
    'tests/Feature/StudentTimetableWorkflowTest.php',
    'tests/Feature/TimetableConflictServiceCanonicalTest.php',
    'tests/Feature/TeacherScopeWorkflowTest.php',
    'tests/Feature/StudentTeacherAttendanceCanonicalWorkflowTest.php',
  ],
  admission: [
    'tests/Feature/AdmissionFrontendBetaReadinessTest.php',
    'tests/Feature/AdmissionFlowTest.php',
    'tests/Feature/AdmissionConfigurationIntegrityTest.php',
    'tests/Feature/AdmissionDepartmentOsTest.php',
  ],
  portal: [
    'tests/Feature/StudentTimetableWorkflowTest.php',
    'tests/Feature/StudentDashboardGuidanceTest.php',
    'tests/Feature/TeacherScopeWorkflowTest.php',
    'tests/Feature/TeacherDashboardGuidanceTest.php',
  ],
  finance: [
    'tests/Feature/FeePaymentTest.php',
    'tests/Feature/AccountsDashboardGuidanceTest.php',
  ],
  production: [
    'tests/Feature/ArchitectureStabilizationTest.php',
    'tests/Feature/ProductionReadinessTest.php',
    'tests/Feature/DemoCredentialsTest.php',
    'tests/Feature/LaunchRouteSmokeTest.php',
  ],
};

const suite = process.argv[2];
if (!suite || !suites[suite]) {
  console.error(`Usage: node scripts/php-test-suite.mjs ${Object.keys(suites).join('|')}`);
  process.exit(1);
}

const result = spawnSync(php, ['artisan', 'test', ...suites[suite]], {
  cwd: process.cwd(),
  env: {
    ...process.env,
    PHPRC: process.env.PHPRC || 'C:/tmp/php-8.5.7-codex-ini',
  },
  stdio: 'inherit',
  shell: false,
});

process.exit(result.status ?? 1);

