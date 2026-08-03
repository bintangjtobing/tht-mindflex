<?php

declare(strict_types=1);

namespace Mindflex\Http\Controller;

use Mindflex\Container;
use Mindflex\Exception\BusinessRuleException;
use Mindflex\Exception\RecordNotFoundException;
use Mindflex\Exception\ValidationException;
use Mindflex\Http\JsonResponse;
use Mindflex\Http\Request;
use Mindflex\Model\Tutor;
use Mindflex\Service\PricingService;
use Mindflex\Support\ErrorHandler;
use Mindflex\Support\Validator;
use Throwable;

/**
 * Endpoint JSON.
 *
 * Nama aksi lama tetap dipakai supaya klien yang sudah ada tidak putus:
 * get_tutors, update_rate, match_student.
 * Yang berubah adalah cara kerjanya. Semua query memakai prepared statement,
 * setiap balasan punya bentuk yang sama, dan kode HTTP mencerminkan hasilnya.
 */
final class ApiController
{
    private const MINIMUM_RATE_CENTS = 500;
    private const MAXIMUM_RATE_CENTS = 100_000;
    private const DEFAULT_PER_PAGE = 25;
    private const METHOD_NOT_ALLOWED = 'method_not_allowed';

    public function __construct(private readonly Container $container)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        try {
            return match ($request->query('action')) {
                'get_tutors' => $this->getTutors($request),
                'update_rate' => $this->updateRate($request),
                'match_student' => $this->matchStudent($request),
                'get_subjects' => $this->getSubjects(),
                '' => JsonResponse::error('missing_action', 'Add an action parameter to the request.', 400),
                default => JsonResponse::error('unknown_action', 'That action does not exist.', 404, [
                    'supported_actions' => ['get_tutors', 'update_rate', 'match_student', 'get_subjects'],
                ]),
            };
        } catch (ValidationException $exception) {
            return JsonResponse::error('validation_failed', 'Check the request parameters.', 422, $exception->errors());
        } catch (BusinessRuleException $exception) {
            // Salah metode adalah masalah protokol, bukan bentrok data.
            $statusCode = $exception->ruleCode() === self::METHOD_NOT_ALLOWED ? 405 : 409;

            return JsonResponse::error($exception->ruleCode(), $exception->getMessage(), $statusCode, $exception->context());
        } catch (RecordNotFoundException $exception) {
            return JsonResponse::error('not_found', $exception->getMessage(), 404);
        } catch (Throwable $exception) {
            ErrorHandler::log($this->container->config(), $exception);

            $message = $this->container->config()->isDebug()
                ? $exception->getMessage()
                : 'The request failed. The team has the details in the log.';

            return JsonResponse::error('server_error', $message, 500);
        }
    }

    /**
     * Daftar tutor aktif. Filter subject sekarang memakai pencocokan persis.
     * Filter lama memakai LIKE '%kata%', jadi subject=Science ikut membawa tutor
     * Computer Science. Pada data contoh cara itu memberi 15 hasil yang semuanya salah.
     */
    private function getTutors(Request $request): JsonResponse
    {
        $this->assertMethod($request, 'GET');

        $subject = $request->query('subject');

        if ($subject !== '') {
            $catalogEntry = $this->container->subjectRepository()->findBySlug($subject);

            if ($catalogEntry === null) {
                return JsonResponse::success([], [
                    'subject' => $subject,
                    'total' => 0,
                    'note' => 'No subject in the catalog matches that name.',
                ]);
            }

            $tutors = $this->container->tutorRepository()->findActiveBySubjectSlug($catalogEntry['slug']);

            return JsonResponse::success(
                array_map($this->presentTutor(...), $tutors),
                ['subject' => $catalogEntry['name'], 'total' => count($tutors)]
            );
        }

        $page = max(1, $request->queryInt('page', 1));
        $perPage = min(100, max(1, $request->queryInt('per_page', self::DEFAULT_PER_PAGE)));
        $result = $this->container->tutorRepository()->paginate($request->query('search'), $page, $perPage);

        $activeTutors = array_values(array_filter(
            $result['tutors'],
            static fn (Tutor $tutor): bool => $tutor->isActive()
        ));

        return JsonResponse::success(
            array_map($this->presentTutor(...), $activeTutors),
            [
                'page' => $result['page'],
                'per_page' => $result['perPage'],
                'total' => $result['total'],
                'last_page' => $result['lastPage'],
            ]
        );
    }

    /**
     * Ubah tarif tutor. Endpoint ini menulis data, jadi memerlukan API key dan POST.
     * Versi lama menerima permintaan dari siapa pun tanpa autentikasi.
     */
    private function updateRate(Request $request): JsonResponse
    {
        $this->assertMethod($request, 'POST');

        $providedKey = $request->header('x-api-key');

        if (! hash_equals($this->container->config()->apiKey(), $providedKey)) {
            return JsonResponse::error('unauthorized', 'Send a valid X-Api-Key header.', 401);
        }

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

        return JsonResponse::success($this->presentTutor($tutor), [
            'note' => 'Running matches keep the rate agreed when they started.',
        ]);
    }

    /**
     * Cari tutor untuk seorang student.
     * Balasan berisi daftar berperingkat, bukan satu tutor pertama yang ditemukan.
     */
    private function matchStudent(Request $request): JsonResponse
    {
        $this->assertMethod($request, 'GET');

        $validator = new Validator($request->queryParameters);
        $studentId = $validator->id('student_id', 'student');
        $subjectName = $validator->requiredString('subject', 60, 'subject');
        $validator->assertValid();

        $student = $this->container->studentRepository()->find($studentId);

        if ($student === null) {
            throw RecordNotFoundException::for('Student', $studentId);
        }

        $weeklyHours = min(40, max(1, $request->queryInt('weekly_hours', PricingService::DEFAULT_WEEKLY_HOURS)));
        $limit = min(20, max(1, $request->queryInt('limit', 5)));

        $result = $this->container->matchmakingService()->findMatches($student, $subjectName, $weeklyHours, $limit);
        $currency = $this->container->config()->currency();

        return JsonResponse::success([
            'match_found' => $result->hasMatch(),
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'weekly_budget' => $student->weeklyBudget->toDecimal(),
                'committed_weekly_cost' => $student->committedWeeklyCost->toDecimal(),
                'remaining_weekly_budget' => $student->remainingWeeklyBudget()->toDecimal(),
            ],
            'subject' => $result->subjectName,
            'proposed_hours' => $result->weeklyHours,
            'candidates' => array_map(
                static fn ($candidate): array => $candidate->toArray($currency),
                $result->candidates
            ),
        ], [
            'tutors_teaching_subject' => $result->tutorsTeachingSubject,
            'filtered_out' => $result->filteredOut,
            'explanation' => $result->hasMatch() ? null : $result->explainNoMatch(),
        ]);
    }

    private function getSubjects(): JsonResponse
    {
        $subjects = $this->container->subjectRepository()->allWithTutorCount();

        return JsonResponse::success($subjects, ['total' => count($subjects)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentTutor(Tutor $tutor): array
    {
        $currency = $this->container->config()->currency();

        return [
            'id' => $tutor->id,
            'name' => $tutor->name,
            'email' => $tutor->email,
            'hourly_rate' => $tutor->hourlyRate->toDecimal(),
            'hourly_rate_formatted' => $tutor->hourlyRate->format($currency),
            'subjects' => $tutor->subjectNames,
            'rating' => $tutor->rating,
            'review_count' => $tutor->reviewCount,
            'status' => $tutor->status,
            'max_weekly_hours' => $tutor->maxWeeklyHours,
            'booked_weekly_hours' => $tutor->bookedWeeklyHours,
            'remaining_weekly_hours' => $tutor->remainingWeeklyHours(),
        ];
    }

    private function assertMethod(Request $request, string $expectedMethod): void
    {
        if ($request->method !== $expectedMethod) {
            throw new BusinessRuleException(
                self::METHOD_NOT_ALLOWED,
                sprintf('Use %s for this action.', $expectedMethod),
                ['received' => $request->method]
            );
        }
    }
}
