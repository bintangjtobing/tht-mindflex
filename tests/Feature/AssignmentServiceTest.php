<?php

declare(strict_types=1);

namespace Mindflex\Tests\Feature;

use Mindflex\Exception\BusinessRuleException;
use Mindflex\Model\AssignmentStatus;
use Mindflex\Tests\DatabaseTestCase;

final class AssignmentServiceTest extends DatabaseTestCase
{
    /**
     * Cacat harga paling mahal pada kode lama.
     * Tarif tutor naik, lalu tagihan minggu lalu ikut naik tanpa persetujuan siapa pun.
     */
    public function test_a_rate_change_does_not_reprice_a_running_match(): void
    {
        $tutorId = $this->makeTutor('Alice Smith', 45.00, ['Math', 'Physics']);
        $studentId = $this->makeStudent('John Doe', 200.00);

        $assignmentId = $this->container->assignmentService()->create(
            $studentId,
            $tutorId,
            $this->subjectId('Math'),
            2
        );

        $this->container->tutorService()->changeHourlyRate($tutorId, 9999);

        $assignment = $this->container->assignmentRepository()->find($assignmentId);

        self::assertNotNull($assignment);
        self::assertSame(9000, $assignment->weeklyCost()->cents());
        self::assertSame(4500, $assignment->agreedHourlyRate->cents());
        self::assertSame(9999, $assignment->currentTutorHourlyRate->cents());
        self::assertTrue($assignment->tutorRateHasChanged());

        // Laporan pendapatan memakai tarif kesepakatan, jadi angkanya tidak bergeser.
        self::assertSame(9000, $this->container->assignmentRepository()->stats()->weeklyRevenue->cents());
    }

    public function test_it_blocks_a_match_that_passes_the_student_budget(): void
    {
        $tutorId = $this->makeTutor('Charlie Brown', 50.00, ['Math']);
        $studentId = $this->makeStudent('Tommy Shelby', 60.00);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('This match costs $100.00 per week');

        $this->container->assignmentService()->create($studentId, $tutorId, $this->subjectId('Math'), 2);
    }

    /**
     * Budget dihitung dari sisa, bukan dari nol. Dua match kecil bisa melewati batas bersama.
     */
    public function test_it_counts_running_matches_against_the_remaining_budget(): void
    {
        $firstTutorId = $this->makeTutor('Alice Smith', 40.00, ['Math']);
        $secondTutorId = $this->makeTutor('Bob Jones', 40.00, ['Physics']);
        $studentId = $this->makeStudent('Jane Miller', 100.00);

        $this->container->assignmentService()->create($studentId, $firstTutorId, $this->subjectId('Math'), 2);

        $this->expectException(BusinessRuleException::class);

        $this->container->assignmentService()->create($studentId, $secondTutorId, $this->subjectId('Physics'), 2);
    }

    public function test_it_blocks_a_match_when_the_tutor_has_no_free_hours(): void
    {
        $tutorId = $this->makeTutor('Diana Prince', 10.00, ['English'], maxWeeklyHours: 4);
        $firstStudentId = $this->makeStudent('Student One', 500.00);
        $secondStudentId = $this->makeStudent('Student Two', 500.00);

        $this->container->assignmentService()->create($firstStudentId, $tutorId, $this->subjectId('English'), 4);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('has 0 free hours per week');

        $this->container->assignmentService()->create($secondStudentId, $tutorId, $this->subjectId('English'), 1);
    }

    public function test_it_blocks_a_match_with_an_inactive_tutor(): void
    {
        $tutorId = $this->makeTutor('Evan Wright', 30.00, ['Math'], status: 'inactive');
        $studentId = $this->makeStudent('John Doe', 500.00);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('is inactive');

        $this->container->assignmentService()->create($studentId, $tutorId, $this->subjectId('Math'), 2);
    }

    public function test_it_blocks_a_subject_the_tutor_does_not_teach(): void
    {
        $tutorId = $this->makeTutor('Alice Smith', 20.00, ['Math']);
        $studentId = $this->makeStudent('John Doe', 500.00);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('does not teach');

        $this->container->assignmentService()->create($studentId, $tutorId, $this->subjectId('Chemistry'), 2);
    }

    public function test_it_blocks_a_duplicate_open_match(): void
    {
        $tutorId = $this->makeTutor('Alice Smith', 10.00, ['Math']);
        $studentId = $this->makeStudent('John Doe', 500.00);
        $subjectId = $this->subjectId('Math');

        $this->container->assignmentService()->create($studentId, $tutorId, $subjectId, 2);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('already has an open Math match');

        $this->container->assignmentService()->create($studentId, $tutorId, $subjectId, 1);
    }

    /**
     * Tombol lama menjalankan DELETE. Riwayat pendapatan ikut hilang bersama barisnya.
     */
    public function test_cancelling_keeps_the_record(): void
    {
        $tutorId = $this->makeTutor('Alice Smith', 20.00, ['Math']);
        $studentId = $this->makeStudent('John Doe', 500.00);

        $assignmentId = $this->container->assignmentService()->create(
            $studentId,
            $tutorId,
            $this->subjectId('Math'),
            2
        );

        $cancelled = $this->container->assignmentService()->cancel($assignmentId);

        self::assertSame(AssignmentStatus::Cancelled, $cancelled->status);
        self::assertNotNull($this->container->assignmentRepository()->find($assignmentId));
        self::assertSame(0, $this->container->assignmentRepository()->stats()->activeAssignments);
    }

    public function test_it_refuses_to_cancel_a_finished_match(): void
    {
        $tutorId = $this->makeTutor('Alice Smith', 20.00, ['Math']);
        $studentId = $this->makeStudent('John Doe', 500.00);

        $assignmentId = $this->container->assignmentService()->create(
            $studentId,
            $tutorId,
            $this->subjectId('Math'),
            2
        );

        $this->container->assignmentService()->complete($assignmentId);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('already completed');

        $this->container->assignmentService()->cancel($assignmentId);
    }

    /**
     * Setelah match selesai, jam tutor kembali tersedia.
     */
    public function test_finishing_a_match_frees_tutor_hours(): void
    {
        $tutorId = $this->makeTutor('Diana Prince', 10.00, ['English'], maxWeeklyHours: 4);
        $studentId = $this->makeStudent('Student One', 500.00);

        $assignmentId = $this->container->assignmentService()->create(
            $studentId,
            $tutorId,
            $this->subjectId('English'),
            4
        );

        $bookedTutor = $this->container->tutorRepository()->find($tutorId);
        self::assertNotNull($bookedTutor);
        self::assertSame(0, $bookedTutor->remainingWeeklyHours());

        $this->container->assignmentService()->complete($assignmentId);

        $freedTutor = $this->container->tutorRepository()->find($tutorId);
        self::assertNotNull($freedTutor);
        self::assertSame(4, $freedTutor->remainingWeeklyHours());
    }
}
