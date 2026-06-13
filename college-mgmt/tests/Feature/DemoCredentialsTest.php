<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DemoCredentialsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function advertisedDemoUserProvider(): array
    {
        return [
            'admin' => ['admin@demo.edu'],
            'admission officer' => ['officer@college.com'],
            'admission head' => ['head@college.com'],
            'accounts officer' => ['accounts@college.com'],
            'dean academics' => ['dean@college.com'],
            'hod' => ['hod@college.com'],
            'program chair' => ['chair@college.com'],
            'cmc' => ['cmc@college.com'],
            'director' => ['director@college.com'],
            'exam cell' => ['exam@college.com'],
            'teacher' => ['anjali@demo.edu'],
            'student' => ['arjun.k@demo.edu'],
            'parent' => ['parent@demo.edu'],
            'applicant' => ['priya.sharma@applicant.demo'],
        ];
    }

    #[DataProvider('advertisedDemoUserProvider')]
    public function test_advertised_demo_user_can_use_standard_password(string $email): void
    {
        $user = User::where('email', $email)->first();

        $this->assertNotNull($user, "Expected demo user {$email} to be seeded.");
        $this->assertTrue(Hash::check('password', $user->password), "Expected {$email} to use the standard demo password.");
    }
}
