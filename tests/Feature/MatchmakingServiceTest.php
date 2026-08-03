<?php

declare(strict_types=1);

namespace Mindflex\Tests\Feature;

use Mindflex\Tests\DatabaseTestCase;

final class MatchmakingServiceTest extends DatabaseTestCase
{
    /**
     * Regresi utama.
     * Filter lama memakai LIKE '%Science%', jadi tutor Computer Science ikut terjaring
     * saat student mencari Science.
     */
    public function test_computer_science_does_not_answer_a_science_request(): void
    {
        $this->makeTutor('Charlie Brown', 20.00, ['Math', 'Computer Science']);
        $this->makeTutor('Evan Wright', 20.00, ['Science']);
        $studentId = $this->makeStudent('John Doe', 500.00);
        $student = $this->container->studentRepository()->find($studentId);

        self::assertNotNull($student);

        $result = $this->container->matchmakingService()->findMatches($student, 'Science', 2);

        self::assertTrue($result->hasMatch());
        self::assertCount(1, $result->candidates);
        self::assertSame('Evan Wright', $result->best()?->tutor->name);
    }

    public function test_it_hides_tutors_that_pass_the_remaining_budget(): void
    {
        $this->makeTutor('Expensive Tutor', 90.00, ['Math']);
        $this->makeTutor('Affordable Tutor', 20.00, ['Math']);
        $studentId = $this->makeStudent('Tommy Shelby', 60.00);
        $student = $this->container->studentRepository()->find($studentId);

        self::assertNotNull($student);

        $result = $this->container->matchmakingService()->findMatches($student, 'Math', 2);

        self::assertCount(1, $result->candidates);
        self::assertSame('Affordable Tutor', $result->best()?->tutor->name);
        self::assertSame(1, $result->filteredOut['over_budget']);
        self::assertSame(2, $result->tutorsTeachingSubject);
    }

    public function test_it_hides_tutors_without_free_hours(): void
    {
        $busyTutorId = $this->makeTutor('Busy Tutor', 10.00, ['Math'], maxWeeklyHours: 2);
        $otherStudentId = $this->makeStudent('Other Student', 500.00);
        $this->container->assignmentService()->create($otherStudentId, $busyTutorId, $this->subjectId('Math'), 2);

        $studentId = $this->makeStudent('John Doe', 500.00);
        $student = $this->container->studentRepository()->find($studentId);

        self::assertNotNull($student);

        $result = $this->container->matchmakingService()->findMatches($student, 'Math', 2);

        self::assertFalse($result->hasMatch());
        self::assertSame(1, $result->filteredOut['no_capacity']);
        self::assertStringContainsString('no free hours left', $result->explainNoMatch());
    }

    public function test_it_hides_a_tutor_already_matched_with_the_student(): void
    {
        $tutorId = $this->makeTutor('Alice Smith', 10.00, ['Math']);
        $studentId = $this->makeStudent('John Doe', 500.00);
        $this->container->assignmentService()->create($studentId, $tutorId, $this->subjectId('Math'), 2);

        $student = $this->container->studentRepository()->find($studentId);

        self::assertNotNull($student);

        $result = $this->container->matchmakingService()->findMatches($student, 'Math', 2);

        self::assertFalse($result->hasMatch());
        self::assertSame(1, $result->filteredOut['already_matched']);
    }

    /**
     * Skor lama selalu 1.0. Sekarang tutor dengan rating dan harga lebih baik naik ke atas.
     */
    public function test_it_ranks_candidates_instead_of_returning_the_first_row(): void
    {
        $this->makeTutor('Low Rated', 40.00, ['Math'], rating: 3.0, reviewCount: 20);
        $this->makeTutor('High Rated', 25.00, ['Math'], rating: 4.9, reviewCount: 40);

        $studentId = $this->makeStudent('Sarah Connor', 200.00);
        $student = $this->container->studentRepository()->find($studentId);

        self::assertNotNull($student);

        $result = $this->container->matchmakingService()->findMatches($student, 'Math', 2);

        self::assertCount(2, $result->candidates);
        self::assertSame('High Rated', $result->candidates[0]->tutor->name);
        self::assertGreaterThan($result->candidates[1]->score, $result->candidates[0]->score);
        self::assertLessThanOrEqual(1.0, $result->candidates[0]->score);
    }

    /**
     * Satu review bintang lima tidak boleh mengalahkan rekam jejak panjang.
     */
    public function test_a_single_five_star_review_does_not_beat_a_long_track_record(): void
    {
        $this->makeTutor('Newcomer', 30.00, ['Math'], rating: 5.0, reviewCount: 1);
        $this->makeTutor('Veteran', 30.00, ['Math'], rating: 4.8, reviewCount: 50);

        $studentId = $this->makeStudent('Sarah Connor', 200.00);
        $student = $this->container->studentRepository()->find($studentId);

        self::assertNotNull($student);

        $result = $this->container->matchmakingService()->findMatches($student, 'Math', 2);

        self::assertSame('Veteran', $result->candidates[0]->tutor->name);
    }

    public function test_it_reports_an_unknown_subject_clearly(): void
    {
        $studentId = $this->makeStudent('John Doe', 500.00);
        $student = $this->container->studentRepository()->find($studentId);

        self::assertNotNull($student);

        $result = $this->container->matchmakingService()->findMatches($student, 'Underwater Basket Weaving', 2);

        self::assertFalse($result->hasMatch());
        self::assertSame(0, $result->tutorsTeachingSubject);
        self::assertStringContainsString('No active tutor teaches', $result->explainNoMatch());
    }

    public function test_the_score_breakdown_explains_the_number(): void
    {
        $this->makeTutor('Alice Smith', 20.00, ['Math'], rating: 4.5, reviewCount: 10);
        $studentId = $this->makeStudent('Sarah Connor', 200.00);
        $student = $this->container->studentRepository()->find($studentId);

        self::assertNotNull($student);

        $candidate = $this->container->matchmakingService()->findMatches($student, 'Math', 2)->best();

        self::assertNotNull($candidate);
        self::assertArrayHasKey('rating', $candidate->scoreBreakdown);
        self::assertArrayHasKey('budget_fit', $candidate->scoreBreakdown);
        self::assertArrayHasKey('capacity', $candidate->scoreBreakdown);
        self::assertSame(4000, $candidate->weeklyCost->cents());
    }
}
