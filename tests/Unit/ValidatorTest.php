<?php

declare(strict_types=1);

namespace Mindflex\Tests\Unit;

use Mindflex\Exception\ValidationException;
use Mindflex\Support\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function test_it_accepts_a_well_formed_tutor_payload(): void
    {
        $validator = new Validator([
            'name' => '  Alice Smith ',
            'email' => 'Alice.Smith@Example.com',
            'hourly_rate' => '45.00',
            'subjects' => 'Math, Physics, math',
        ]);

        self::assertSame('Alice Smith', $validator->requiredString('name'));
        self::assertSame('alice.smith@example.com', $validator->email('email'));
        self::assertSame(4500, $validator->moneyCents('hourly_rate', 500, 100000));
        self::assertSame(['Math', 'Physics'], $validator->subjectList('subjects'));

        $validator->assertValid();
        self::assertFalse($validator->fails());
    }

    /**
     * Form lama menyimpan tarif negatif apa adanya.
     */
    public function test_it_rejects_a_negative_rate(): void
    {
        $validator = new Validator(['hourly_rate' => '-20']);
        $validator->moneyCents('hourly_rate', 500, 100000, 'hourly rate');

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('hourly_rate', $validator->errors());
    }

    public function test_it_rejects_a_rate_that_is_not_a_number(): void
    {
        $validator = new Validator(['hourly_rate' => '45 OR 1=1']);
        $validator->moneyCents('hourly_rate', 500, 100000, 'hourly rate');

        self::assertTrue($validator->fails());
    }

    public function test_it_reports_every_error_in_one_pass(): void
    {
        $validator = new Validator(['name' => '', 'email' => 'not-an-email', 'hourly_rate' => 'abc']);
        $validator->requiredString('name', 120, 'full name');
        $validator->email('email');
        $validator->moneyCents('hourly_rate', 500, 100000, 'hourly rate');

        self::assertCount(3, $validator->errors());
    }

    public function test_assert_valid_throws_with_all_messages(): void
    {
        $validator = new Validator([]);
        $validator->requiredString('name', 120, 'full name');

        $this->expectException(ValidationException::class);

        $validator->assertValid();
    }

    public function test_it_keeps_weekly_hours_inside_the_allowed_range(): void
    {
        $validator = new Validator(['weekly_hours' => '80']);
        $validator->integer('weekly_hours', 1, 40, 'hours per week');

        self::assertTrue($validator->fails());
        self::assertSame(
            'Kolom hours per week harus antara 1 dan 40.',
            $validator->errors()['weekly_hours']
        );
    }
}
