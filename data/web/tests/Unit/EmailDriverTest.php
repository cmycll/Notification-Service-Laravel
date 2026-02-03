<?php

namespace Tests\Unit;

use App\Services\Notifications\Drivers\EmailDriver;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailDriverTest extends TestCase
{
    #[Test]
    public function itRejectsEmailSubjectOver255Characters(): void
    {
        $driver = new EmailDriver();

        $this->expectException(ValidationException::class);
        $driver->validateTemplateLimits([
            'subject' => str_repeat('a', 256),
            'body' => 'ok',
        ]);
    }

    #[Test]
    public function itRejectsEmailBodyOver10000Characters(): void
    {
        $driver = new EmailDriver();

        $this->expectException(ValidationException::class);
        $driver->validateTemplateLimits([
            'subject' => 'ok',
            'body' => str_repeat('b', 10001),
        ]);
    }

    #[Test]
    public function itStoresEmailBodyAndReturnsBodyPath(): void
    {
        Storage::fake('local');
        request()->headers->set('X-Correlation-ID', 'test-corr');

        $driver = new EmailDriver();
        $result = $driver->prepareTemplateData([
            'subject' => 'Subject',
            'body' => 'Email body',
        ]);

        $this->assertSame('', $result['body']);
        $this->assertSame('notifications/email_test-corr.txt', $result['body_path']);
        $this->assertTrue(Storage::disk('local')->exists('notifications/email_test-corr.txt'));
    }
}
