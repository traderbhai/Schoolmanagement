import { existsSync } from 'node:fs';
import { spawnSync } from 'node:child_process';

const php = process.env.PHP_BINARY
  || (existsSync('C:/tmp/php-8.5.7/php.exe') ? 'C:/tmp/php-8.5.7/php.exe' : 'php');

const args = [
  'artisan',
  'test',
  'tests/Feature/FrontendReadinessTest.php',
  'tests/Feature/AdmissionFrontendBetaReadinessTest.php',
  'tests/Feature/AcademicsDeanFrontendBetaReadinessTest.php',
  'tests/Feature/AcademicsPmcFrontendBetaReadinessTest.php',
  'tests/Feature/AcademicsCoeFrontendBetaReadinessTest.php',
  'tests/Feature/AcademicsIqacFrontendBetaReadinessTest.php',
  'tests/Feature/AcademicsProgramLeadershipFrontendBetaReadinessTest.php',
  'tests/Feature/AcademicsCourseDeliveryFrontendBetaReadinessTest.php',
  'tests/Feature/PortalFrontendBetaReadinessTest.php',
  'tests/Feature/AdminOperationsFrontendBetaReadinessTest.php',
  'tests/Feature/ProductionReadinessTest.php',
  'tests/Feature/DemoCredentialsTest.php',
  'tests/Feature/LaunchRouteSmokeTest.php',
];

if (process.argv.includes('--mobile')) {
  args.splice(3, 0, '--filter=FrontendReadiness|sidebar|mobile|layout');
}

const env = {
  ...process.env,
  PHPRC: process.env.PHPRC || 'C:/tmp/php-8.5.7-codex-ini',
};

const result = spawnSync(php, args, {
  cwd: process.cwd(),
  env,
  stdio: 'inherit',
  shell: false,
});

process.exit(result.status ?? 1);
