<?php

declare(strict_types=1);

namespace Mindflex;

use Mindflex\Auth\AdminAuth;
use Mindflex\Http\View;
use Mindflex\Repository\AssignmentRepository;
use Mindflex\Repository\StudentRepository;
use Mindflex\Repository\SubjectRepository;
use Mindflex\Repository\TutorRepository;
use Mindflex\Service\AssignmentService;
use Mindflex\Service\MatchmakingService;
use Mindflex\Service\PricingService;
use Mindflex\Service\TutorService;
use Mindflex\Support\Config;
use Mindflex\Support\Database;
use Mindflex\Support\ErrorHandler;
use PDO;

/**
 * Perakit objek.
 *
 * Aplikasi sekecil ini tidak butuh container dengan reflection. Kelas ini hanya
 * membuat setiap dependensi satu kali dan menyimpannya, sehingga jalur objek
 * tetap terbaca dari atas ke bawah.
 */
final class Container
{
    private ?PDO $connection = null;
    private ?TutorRepository $tutorRepository = null;
    private ?StudentRepository $studentRepository = null;
    private ?SubjectRepository $subjectRepository = null;
    private ?AssignmentRepository $assignmentRepository = null;
    private ?PricingService $pricingService = null;
    private ?MatchmakingService $matchmakingService = null;
    private ?AssignmentService $assignmentService = null;
    private ?TutorService $tutorService = null;
    private ?AdminAuth $auth = null;
    private ?View $view = null;

    private function __construct(private readonly Config $config)
    {
    }

    public static function boot(?string $projectRoot = null): self
    {
        $config = Config::load($projectRoot);
        ErrorHandler::register($config);

        return new self($config);
    }

    public static function fromConfig(Config $config): self
    {
        return new self($config);
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function connection(): PDO
    {
        return $this->connection ??= Database::connection($this->config);
    }

    public function tutorRepository(): TutorRepository
    {
        return $this->tutorRepository ??= new TutorRepository($this->connection());
    }

    public function studentRepository(): StudentRepository
    {
        return $this->studentRepository ??= new StudentRepository($this->connection());
    }

    public function subjectRepository(): SubjectRepository
    {
        return $this->subjectRepository ??= new SubjectRepository($this->connection());
    }

    public function assignmentRepository(): AssignmentRepository
    {
        return $this->assignmentRepository ??= new AssignmentRepository($this->connection());
    }

    public function pricingService(): PricingService
    {
        return $this->pricingService ??= new PricingService();
    }

    public function matchmakingService(): MatchmakingService
    {
        return $this->matchmakingService ??= new MatchmakingService(
            $this->tutorRepository(),
            $this->subjectRepository(),
            $this->assignmentRepository(),
            $this->pricingService(),
            $this->config->budgetTolerance(),
        );
    }

    public function assignmentService(): AssignmentService
    {
        return $this->assignmentService ??= new AssignmentService(
            $this->connection(),
            $this->assignmentRepository(),
            $this->tutorRepository(),
            $this->studentRepository(),
            $this->subjectRepository(),
            $this->pricingService(),
            $this->config->budgetTolerance(),
        );
    }

    public function tutorService(): TutorService
    {
        return $this->tutorService ??= new TutorService(
            $this->connection(),
            $this->tutorRepository(),
            $this->subjectRepository(),
        );
    }

    public function auth(): AdminAuth
    {
        return $this->auth ??= new AdminAuth($this->config);
    }

    public function view(): View
    {
        return $this->view ??= new View($this->config->projectRoot() . '/views');
    }
}
