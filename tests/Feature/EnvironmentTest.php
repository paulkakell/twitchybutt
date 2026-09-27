<?php

namespace Tests\Feature;

use Tests\TestCase;

class EnvironmentTest extends TestCase
{
    public function test_feature_tests_use_isolated_array_state(): void
    {
        self::assertSame('array', config('cache.default'));
        self::assertSame('array', config('session.driver'));
        self::assertSame(4, config('hashing.bcrypt.rounds'));
        self::assertTrue($this->app->environment('testing'));
    }
}
