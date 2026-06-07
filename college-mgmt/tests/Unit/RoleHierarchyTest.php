<?php
namespace Tests\Unit;

use App\Services\RoleHierarchyService;
use PHPUnit\Framework\TestCase;

class RoleHierarchyTest extends TestCase
{
    private RoleHierarchyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RoleHierarchyService();
    }

    public function test_admin_is_highest(): void
    {
        $this->assertTrue($this->service->isHigherThan('admin', 'dean_academics'));
        $this->assertTrue($this->service->isHigherThan('admin', 'student'));
    }

    public function test_student_is_lowest(): void
    {
        $this->assertFalse($this->service->isHigherThan('student', 'teacher'));
        $this->assertFalse($this->service->isHigherThan('student', 'faculty'));
    }

    public function test_dean_higher_than_program_chair(): void
    {
        $this->assertTrue($this->service->isHigherThan('dean_academics', 'program_chair'));
        $this->assertFalse($this->service->isHigherThan('program_chair', 'dean_academics'));
    }

    public function test_admin_inherits_all_roles(): void
    {
        $inherited = $this->service->getInheritedRoles(['admin']);
        $this->assertContains('student', $inherited);
        $this->assertContains('faculty', $inherited);
        $this->assertContains('dean_academics', $inherited);
        $this->assertContains('admin', $inherited);
    }

    public function test_student_inherits_nothing_lower(): void
    {
        $inherited = $this->service->getInheritedRoles(['student']);
        $this->assertContains('student', $inherited);
        $this->assertNotContains('faculty', $inherited);
        $this->assertNotContains('admin', $inherited);
    }

    public function test_program_chair_inherits_teacher_but_not_dean(): void
    {
        $inherited = $this->service->getInheritedRoles(['program_chair']);
        $this->assertContains('teacher', $inherited);
        $this->assertContains('faculty', $inherited);
        $this->assertNotContains('dean_academics', $inherited);
        $this->assertNotContains('admin', $inherited);
    }

    public function test_multiple_roles_uses_highest(): void
    {
        // user with both student and teacher roles: teacher is higher, inherits student
        $inherited = $this->service->getInheritedRoles(['student', 'teacher']);
        $this->assertContains('student', $inherited);
        $this->assertContains('teacher', $inherited);
        $this->assertNotContains('program_chair', $inherited);
    }
}
