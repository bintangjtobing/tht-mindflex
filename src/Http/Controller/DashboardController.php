<?php

declare(strict_types=1);

namespace Mindflex\Http\Controller;

use Mindflex\Container;
use Mindflex\Http\Flash;
use Mindflex\Http\Request;
use Mindflex\Model\MatchResult;
use Mindflex\Service\PricingService;

/**
 * Menyusun data halaman admin.
 * Semua pengambilan data terjadi di sini, view hanya mencetak.
 */
final class DashboardController
{
    private const TUTORS_PER_PAGE = 15;

    public function __construct(private readonly Container $container)
    {
    }

    public function index(Request $request): string
    {
        $searchTerm = $request->query('search');
        $page = max(1, $request->queryInt('page', 1));

        $tutorPage = $this->container->tutorRepository()->paginate($searchTerm, $page, self::TUTORS_PER_PAGE);

        return $this->container->view()->render('dashboard', [
            'pageTitle' => 'Mindflex admin dashboard',
            'currency' => $this->container->config()->currency(),
            'adminUsername' => $this->container->auth()->username(),
            'flash' => Flash::pull(),
            'stats' => $this->container->assignmentRepository()->stats(),
            'assignments' => $this->container->assignmentRepository()->all(),
            'budgetRisks' => $this->container->assignmentRepository()->openAndOverBudget(),
            'tutors' => $tutorPage['tutors'],
            'tutorTotal' => $tutorPage['total'],
            'tutorPage' => $tutorPage['page'],
            'tutorLastPage' => $tutorPage['lastPage'],
            'searchTerm' => $searchTerm,
            'students' => $this->container->studentRepository()->all(),
            'activeTutors' => $this->container->tutorRepository()->listActive(),
            'subjects' => $this->container->subjectRepository()->allWithTutorCount(),
            'matchResult' => $this->runMatchSearch($request),
            'matchStudentId' => $request->queryInt('match_student_id', 0),
            'matchSubjectSlug' => $request->query('match_subject'),
            'matchWeeklyHours' => $this->requestedWeeklyHours($request),
        ]);
    }

    public function login(Request $request): string
    {
        return $this->container->view()->render('login', [
            'pageTitle' => 'Sign in to Mindflex admin',
            'flash' => Flash::pull(),
            'submittedUsername' => $request->input('username'),
        ], layout: 'layout-blank');
    }

    /**
     * Jalankan pencarian tutor hanya bila admin mengisi form pencocokan.
     */
    private function runMatchSearch(Request $request): ?MatchResult
    {
        $studentId = $request->queryInt('match_student_id', 0);
        $subjectSlug = $request->query('match_subject');

        if ($studentId <= 0 || $subjectSlug === '') {
            return null;
        }

        $student = $this->container->studentRepository()->find($studentId);

        if ($student === null) {
            return null;
        }

        return $this->container->matchmakingService()->findMatches(
            $student,
            $subjectSlug,
            $this->requestedWeeklyHours($request),
        );
    }

    private function requestedWeeklyHours(Request $request): int
    {
        $hours = $request->queryInt('match_hours', PricingService::DEFAULT_WEEKLY_HOURS);

        return max(1, min(40, $hours));
    }
}
