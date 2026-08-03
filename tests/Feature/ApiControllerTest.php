<?php

declare(strict_types=1);

namespace Mindflex\Tests\Feature;

use Mindflex\Http\Controller\ApiController;
use Mindflex\Http\JsonResponse;
use Mindflex\Http\Request;
use Mindflex\Tests\DatabaseTestCase;

final class ApiControllerTest extends DatabaseTestCase
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    private function call(string $method, array $query, array $body = [], array $headers = []): JsonResponse
    {
        return (new ApiController($this->container))->handle(new Request($method, $query, $body, $headers));
    }

    public function test_it_lists_active_tutors_for_an_exact_subject(): void
    {
        $this->makeTutor('Charlie Brown', 20.00, ['Math', 'Computer Science']);
        $this->makeTutor('Evan Wright', 20.00, ['Science']);

        $response = $this->call('GET', ['action' => 'get_tutors', 'subject' => 'Science']);

        self::assertSame(200, $response->statusCode);
        self::assertCount(1, $response->payload['data']);
        self::assertSame('Evan Wright', $response->payload['data'][0]['name']);
    }

    public function test_it_skips_inactive_tutors(): void
    {
        $this->makeTutor('Active Tutor', 20.00, ['Math']);
        $this->makeTutor('Retired Tutor', 20.00, ['Math'], status: 'inactive');

        $response = $this->call('GET', ['action' => 'get_tutors', 'subject' => 'Math']);

        self::assertCount(1, $response->payload['data']);
    }

    /**
     * Payload ini melewati filter lama karena nilainya masuk langsung ke SQL.
     */
    public function test_an_injection_payload_returns_an_empty_list_instead_of_running(): void
    {
        $this->makeTutor('Alice Smith', 20.00, ['Math']);

        $response = $this->call('GET', [
            'action' => 'get_tutors',
            'subject' => "Math' OR '1'='1",
        ]);

        self::assertSame(200, $response->statusCode);
        self::assertSame([], $response->payload['data']);
        self::assertNotNull($this->container->tutorRepository()->find(1));
    }

    public function test_update_rate_needs_an_api_key(): void
    {
        $tutorId = $this->makeTutor('Alice Smith', 20.00, ['Math']);

        $response = $this->call('POST', ['action' => 'update_rate'], [
            'tutor_id' => (string) $tutorId,
            'hourly_rate' => '99.00',
        ]);

        self::assertSame(401, $response->statusCode);
        self::assertSame('unauthorized', $response->payload['error']['code']);
        self::assertSame(2000, $this->container->tutorRepository()->find($tutorId)?->hourlyRate->cents());
    }

    public function test_update_rate_rejects_a_get_request(): void
    {
        $response = $this->call('GET', ['action' => 'update_rate'], [], ['x-api-key' => 'test-api-key']);

        self::assertSame(405, $response->statusCode);
        self::assertSame('method_not_allowed', $response->payload['error']['code']);
    }

    public function test_update_rate_rejects_a_negative_amount(): void
    {
        $tutorId = $this->makeTutor('Alice Smith', 20.00, ['Math']);

        $response = $this->call(
            'POST',
            ['action' => 'update_rate'],
            ['tutor_id' => (string) $tutorId, 'hourly_rate' => '-50'],
            ['x-api-key' => 'test-api-key']
        );

        self::assertSame(422, $response->statusCode);
        self::assertSame('validation_failed', $response->payload['error']['code']);
    }

    public function test_update_rate_succeeds_with_a_valid_key(): void
    {
        $tutorId = $this->makeTutor('Alice Smith', 20.00, ['Math']);

        $response = $this->call(
            'POST',
            ['action' => 'update_rate'],
            ['tutor_id' => (string) $tutorId, 'hourly_rate' => '55.25'],
            ['x-api-key' => 'test-api-key']
        );

        self::assertSame(200, $response->statusCode);
        self::assertSame(55.25, $response->payload['data']['hourly_rate']);
    }

    public function test_match_student_returns_ranked_candidates(): void
    {
        $this->makeTutor('Cheap Tutor', 10.00, ['Math'], rating: 4.9, reviewCount: 30);
        $this->makeTutor('Pricey Tutor', 45.00, ['Math'], rating: 4.0, reviewCount: 30);
        $studentId = $this->makeStudent('Sarah Connor', 200.00);

        $response = $this->call('GET', [
            'action' => 'match_student',
            'student_id' => (string) $studentId,
            'subject' => 'Math',
            'weekly_hours' => '2',
        ]);

        self::assertSame(200, $response->statusCode);
        self::assertTrue($response->payload['data']['match_found']);
        self::assertSame('Cheap Tutor', $response->payload['data']['candidates'][0]['tutor']['name']);
        self::assertNotSame(1.0, $response->payload['data']['candidates'][0]['match_score']);
    }

    public function test_match_student_reports_a_missing_student(): void
    {
        $response = $this->call('GET', [
            'action' => 'match_student',
            'student_id' => '999',
            'subject' => 'Math',
        ]);

        self::assertSame(404, $response->statusCode);
        self::assertSame('not_found', $response->payload['error']['code']);
    }

    public function test_match_student_requires_both_parameters(): void
    {
        $response = $this->call('GET', ['action' => 'match_student']);

        self::assertSame(422, $response->statusCode);
        self::assertArrayHasKey('student_id', $response->payload['error']['details']);
        self::assertArrayHasKey('subject', $response->payload['error']['details']);
    }

    public function test_an_unknown_action_returns_json_not_plain_text(): void
    {
        $response = $this->call('GET', ['action' => 'drop_everything']);

        self::assertSame(404, $response->statusCode);
        self::assertSame('unknown_action', $response->payload['error']['code']);
        self::assertJson($response->toJson());
    }

    public function test_a_missing_action_returns_a_clear_message(): void
    {
        $response = $this->call('GET', []);

        self::assertSame(400, $response->statusCode);
        self::assertSame('missing_action', $response->payload['error']['code']);
    }
}
