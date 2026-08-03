<?php

declare(strict_types=1);

namespace Mindflex\Http\Controller;

use Mindflex\Container;
use Mindflex\Exception\BusinessRuleException;
use Mindflex\Exception\RecordNotFoundException;
use Mindflex\Exception\ValidationException;
use Mindflex\Http\Flash;
use Mindflex\Http\RedirectResponse;
use Mindflex\Http\Request;
use Mindflex\Support\Validator;

/**
 * Semua aksi yang mengubah data.
 *
 * Setiap aksi memakai POST dan token CSRF. Versi lama memindahkan penghapusan dan
 * penyelesaian assignment lewat tautan GET, sehingga perayap atau satu klik dari
 * halaman lain sudah bisa mengubah data.
 */
final class AdminActionController
{
    private const MINIMUM_RATE_CENTS = 500;
    private const MAXIMUM_RATE_CENTS = 100_000;
    private const MAXIMUM_BUDGET_CENTS = 1_000_000;

    public function __construct(private readonly Container $container)
    {
    }

    public function handle(Request $request): RedirectResponse
    {
        return match ($request->action()) {
            'add_tutor' => $this->addTutor($request),
            'add_student' => $this->addStudent($request),
            'create_assignment' => $this->createAssignment($request),
            'update_rate' => $this->updateTutorRate($request),
            'complete_assignment' => $this->changeAssignmentStatus($request, complete: true),
            'cancel_assignment' => $this->changeAssignmentStatus($request, complete: false),
            default => $this->unknownAction(),
        };
    }

    private function addTutor(Request $request): RedirectResponse
    {
        return $this->guarded(function () use ($request): RedirectResponse {
            $validator = new Validator($request->bodyParameters);
            $name = $validator->requiredString('name', 120, 'full name');
            $email = $validator->email('email');
            $hourlyRateCents = $validator->moneyCents(
                'hourly_rate',
                self::MINIMUM_RATE_CENTS,
                self::MAXIMUM_RATE_CENTS,
                'hourly rate'
            );
            $subjectNames = $validator->subjectList('subjects');
            $maxWeeklyHours = $validator->integer('max_weekly_hours', 1, 60, 'maximum weekly hours');
            $validator->assertValid();

            $this->container->tutorService()->register(
                $name,
                $email,
                $hourlyRateCents,
                $subjectNames,
                $maxWeeklyHours
            );

            Flash::success(sprintf('You added %s to the tutor directory.', $name));

            return RedirectResponse::toDashboard();
        });
    }

    private function addStudent(Request $request): RedirectResponse
    {
        return $this->guarded(function () use ($request): RedirectResponse {
            $validator = new Validator($request->bodyParameters);
            $name = $validator->requiredString('name', 120, 'full name');
            $gradeLevel = $validator->requiredString('grade_level', 40, 'grade level');
            $weeklyBudgetCents = $validator->moneyCents('weekly_budget', 0, self::MAXIMUM_BUDGET_CENTS, 'weekly budget');
            $validator->assertValid();

            $this->container->studentRepository()->create($name, $gradeLevel, $weeklyBudgetCents);

            Flash::success(sprintf('You registered %s.', $name));

            return RedirectResponse::toDashboard();
        });
    }

    private function createAssignment(Request $request): RedirectResponse
    {
        return $this->guarded(function () use ($request): RedirectResponse {
            $validator = new Validator($request->bodyParameters);
            $studentId = $validator->id('student_id', 'student');
            $tutorId = $validator->id('tutor_id', 'tutor');
            $subjectId = $validator->id('subject_id', 'subject');
            $weeklyHours = $validator->integer('weekly_hours', 1, 40, 'hours per week');
            $validator->assertValid();

            $assignmentId = $this->container->assignmentService()->create(
                $studentId,
                $tutorId,
                $subjectId,
                $weeklyHours
            );

            $assignment = $this->container->assignmentRepository()->find($assignmentId);
            $currency = $this->container->config()->currency();

            if ($assignment === null) {
                Flash::success('You created the match.');

                return RedirectResponse::toDashboard();
            }

            Flash::success(sprintf(
                'You matched %s with %s for %d hours per week at %s.',
                $assignment->studentName,
                $assignment->tutorName,
                $weeklyHours,
                $assignment->weeklyCost()->format($currency)
            ));

            return RedirectResponse::toDashboard();
        });
    }

    private function updateTutorRate(Request $request): RedirectResponse
    {
        return $this->guarded(function () use ($request): RedirectResponse {
            $validator = new Validator($request->bodyParameters);
            $tutorId = $validator->id('tutor_id', 'tutor');
            $hourlyRateCents = $validator->moneyCents(
                'hourly_rate',
                self::MINIMUM_RATE_CENTS,
                self::MAXIMUM_RATE_CENTS,
                'hourly rate'
            );
            $validator->assertValid();

            $tutor = $this->container->tutorService()->changeHourlyRate($tutorId, $hourlyRateCents);

            Flash::success(sprintf(
                'You set the rate for %s to %s per hour. Running matches keep their agreed rate.',
                $tutor->name,
                $tutor->hourlyRate->format($this->container->config()->currency())
            ));

            return RedirectResponse::toDashboard();
        });
    }

    private function changeAssignmentStatus(Request $request, bool $complete): RedirectResponse
    {
        return $this->guarded(function () use ($request, $complete): RedirectResponse {
            $validator = new Validator($request->bodyParameters);
            $assignmentId = $validator->id('assignment_id', 'match');
            $validator->assertValid();

            $service = $this->container->assignmentService();
            $assignment = $complete ? $service->complete($assignmentId) : $service->cancel($assignmentId);

            Flash::success(sprintf(
                'You marked the match between %s and %s as %s.',
                $assignment->studentName,
                $assignment->tutorName,
                strtolower($assignment->status->label())
            ));

            return RedirectResponse::toDashboard();
        });
    }

    private function unknownAction(): RedirectResponse
    {
        Flash::error('That action does not exist.');

        return RedirectResponse::toDashboard();
    }

    /**
     * Terjemahkan exception domain menjadi pesan yang bisa dibaca admin.
     *
     * @param callable(): RedirectResponse $operation
     */
    private function guarded(callable $operation): RedirectResponse
    {
        try {
            return $operation();
        } catch (ValidationException $exception) {
            Flash::error($exception->firstMessage());
        } catch (BusinessRuleException $exception) {
            Flash::error($exception->getMessage());
        } catch (RecordNotFoundException $exception) {
            Flash::error($exception->getMessage());
        }

        return RedirectResponse::toDashboard();
    }
}
