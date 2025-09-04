<?php

namespace App\Tests\Unit\Exception;

use App\Exception\MissingRequestParameterException;
use PHPUnit\Framework\TestCase;

class MissingRequestParameterExceptionTest extends TestCase
{
    public function testNewWithValidParameters(): void
    {
        $name = 'userId';
        $type = 'query';

        $exception = MissingRequestParameterException::new($name, $type);

        $this->assertInstanceOf(MissingRequestParameterException::class, $exception);
        $this->assertEquals('Required "userId" parameter in "query" is not present.', $exception->getMessage());
    }

    public function testNewWithDifferentParameters(): void
    {
        $name = 'token';
        $type = 'header';

        $exception = MissingRequestParameterException::new($name, $type);

        $this->assertEquals('Required "token" parameter in "header" is not present.', $exception->getMessage());
    }

    public function testNewWithEmptyParameters(): void
    {
        $name = '';
        $type = '';

        $exception = MissingRequestParameterException::new($name, $type);

        $this->assertEquals('Required "" parameter in "" is not present.', $exception->getMessage());
    }

    public function testNewWithSpecialCharacters(): void
    {
        $name = 'user-id';
        $type = 'request_body';

        $exception = MissingRequestParameterException::new($name, $type);

        $this->assertEquals('Required "user-id" parameter in "request_body" is not present.', $exception->getMessage());
    }

    public function testNewWithLongParameters(): void
    {
        $name = 'very_long_parameter_name_that_should_still_work';
        $type = 'complex_nested_request_structure';

        $exception = MissingRequestParameterException::new($name, $type);

        $expectedMessage = 'Required "very_long_parameter_name_that_should_still_work" parameter in "complex_nested_request_structure" is not present.';
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function testNewWithNumericParameters(): void
    {
        $name = '123';
        $type = 'form';

        $exception = MissingRequestParameterException::new($name, $type);

        $this->assertEquals('Required "123" parameter in "form" is not present.', $exception->getMessage());
    }

    public function testNewReturnsDifferentInstances(): void
    {
        $exception1 = MissingRequestParameterException::new('param1', 'type1');
        $exception2 = MissingRequestParameterException::new('param2', 'type2');

        $this->assertNotSame($exception1, $exception2);
        $this->assertNotEquals($exception1->getMessage(), $exception2->getMessage());
    }

    public function testNewIsInstanceOfException(): void
    {
        $exception = MissingRequestParameterException::new('test', 'test');

        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testNewHasDefaultCode(): void
    {
        $exception = MissingRequestParameterException::new('test', 'test');

        $this->assertEquals(0, $exception->getCode());
    }

    public function testNewHasNullPreviousException(): void
    {
        $exception = MissingRequestParameterException::new('test', 'test');

        $this->assertNull($exception->getPrevious());
    }

    public function testMessageFormatConsistency(): void
    {
        $testCases = [
            ['id', 'query', 'Required "id" parameter in "query" is not present.'],
            ['auth_token', 'header', 'Required "auth_token" parameter in "header" is not present.'],
            ['data', 'body', 'Required "data" parameter in "body" is not present.'],
        ];

        foreach ($testCases as [$name, $type, $expectedMessage]) {
            $exception = MissingRequestParameterException::new($name, $type);
            $this->assertEquals($expectedMessage, $exception->getMessage());
        }
    }
}