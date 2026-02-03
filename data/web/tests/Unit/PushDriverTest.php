<?php

namespace Tests\Unit;

use App\Services\Notifications\Drivers\PushDriver;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PushDriverTest extends TestCase
{
    #[Test]
    public function itRejectsPushSubjectOver100Characters(): void
    {
        $driver = new PushDriver();

        $this->expectException(ValidationException::class);
        $driver->validateTemplateLimits([
            'subject' => str_repeat('a', 101),
            'body' => 'ok',
        ]);
    }

    #[Test]
    public function itRejectsPushBodyOver200Characters(): void
    {
        $driver = new PushDriver();

        $this->expectException(ValidationException::class);
        $driver->validateTemplateLimits([
            'subject' => 'ok',
            'body' => str_repeat('b', 201),
        ]);
    }
}
