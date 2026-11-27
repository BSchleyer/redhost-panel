<?php

declare(strict_types=1);

namespace Nebrix\Tests\Unit\Security;

use Nebrix\Security\ValidationException;
use Nebrix\Security\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testReturnsOnlyFieldsWithRulesAndNormalizesTypes(): void
    {
        $data = Validator::validate(
            ['email' => ' USER@Example.COM ', 'qty' => '3', 'active' => 'on', 'role_id' => '1', 'is_admin' => '1'],
            ['email' => 'required|email', 'qty' => 'required|int|min:1|max:5', 'active' => 'bool'],
        );

        self::assertSame(['email' => 'user@example.com', 'qty' => 3, 'active' => true], $data);
        self::assertArrayNotHasKey('is_admin', $data, 'Unexpected fields must never pass validation (mass assignment).');
    }

    public function testMissingCheckboxBecomesFalse(): void
    {
        self::assertSame(['enabled' => false], Validator::validate([], ['enabled' => 'bool']));
    }

    public function testCollectsErrorsAndStripsSecretsFromFlashedInput(): void
    {
        try {
            Validator::validate(
                ['email' => 'not-an-email', 'password' => 'short', 'name' => 'Ada'],
                ['email' => 'required|email', 'password' => 'required|min:10', 'name' => 'required|string', 'age' => 'required|int'],
            );
            self::fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('email', $exception->errors());
            self::assertArrayHasKey('password', $exception->errors());
            self::assertArrayHasKey('age', $exception->errors());
            self::assertArrayNotHasKey('name', $exception->errors());
            self::assertArrayNotHasKey('password', $exception->input(), 'Passwords must not be flashed back into forms.');
            self::assertSame('Ada', $exception->input()['name']);
        }
    }

    public function testRegexMayContainPipes(): void
    {
        self::assertSame(['code' => 'b'], Validator::validate(['code' => 'b'], ['code' => 'required|regex:/^(a|b)$/']));
    }

    public function testInRuleRejectsUnknownValues(): void
    {
        $this->expectException(ValidationException::class);
        Validator::validate(['cycle' => 'weekly'], ['cycle' => 'required|in:monthly,annually']);
    }

    public function testRejectsNonHttpUrls(): void
    {
        $this->expectException(ValidationException::class);
        Validator::validate(['url' => 'javascript:alert(1)'], ['url' => 'url']);
    }

    public function testDecimalAcceptsCommaAndRejectsThreeDecimals(): void
    {
        self::assertSame(['price' => '12.50'], Validator::validate(['price' => '12,50'], ['price' => 'decimal']));

        $this->expectException(ValidationException::class);
        Validator::validate(['price' => '1.005'], ['price' => 'decimal']);
    }

    public function testConfirmedUsesConstantTimeComparison(): void
    {
        self::assertSame(
            ['password' => 'abcdefghij'],
            Validator::validate(['password' => 'abcdefghij', 'password_confirmation' => 'abcdefghij'], ['password' => 'required|confirmed']),
        );

        $this->expectException(ValidationException::class);
        Validator::validate(['password' => 'abcdefghij', 'password_confirmation' => 'abcdefghik'], ['password' => 'required|confirmed']);
    }
}
