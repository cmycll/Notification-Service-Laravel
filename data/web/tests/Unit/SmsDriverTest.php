<?php

namespace Tests\Unit;

use App\Services\Notifications\Drivers\SmsDriver;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SmsDriverTest extends TestCase
{
    #[Test]
    public function itRejectsSmsBodyOver160Characters(): void
    {
        $driver = new SmsDriver();

        $this->expectException(ValidationException::class);
        $driver->validateTemplateLimits([
            'subject' => 'Hello',
            'body' => str_repeat('a', 161),
        ]);
    }
}
