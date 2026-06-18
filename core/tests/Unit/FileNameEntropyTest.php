<?php

namespace Tests\Unit;

use App\Lib\FileManager;
use Illuminate\Http\UploadedFile;
use ReflectionMethod;
use Tests\TestCase;

/**
 * B5: upload filenames must be unguessable.
 *
 * KYC/verification documents are stored in web-served directories under the name
 * getFileName() returns. The previous uniqid()+time() scheme was fully
 * time-derived (guessable). These DB-free tests assert NEW filenames carry
 * cryptographic entropy and never collide, while keeping the original extension.
 */
class FileNameEntropyTest extends TestCase
{
    private function generate(string $originalName): string
    {
        $fm     = new FileManager(UploadedFile::fake()->create($originalName));
        $method = new ReflectionMethod($fm, 'getFileName');
        $method->setAccessible(true);

        return $method->invoke($fm);
    }

    public function test_generated_name_carries_128_bits_of_entropy_and_keeps_extension(): void
    {
        $name = $this->generate('passport.pdf');

        // A 32-hex-char (128-bit) token immediately before the extension.
        $this->assertMatchesRegularExpression('/[0-9a-f]{32}\.pdf$/', $name);
        $this->assertStringEndsWith('.pdf', $name);
    }

    public function test_generated_names_do_not_collide(): void
    {
        $names = [];
        for ($i = 0; $i < 200; $i++) {
            $names[] = $this->generate('doc.png');
        }

        $this->assertCount(200, array_unique($names), 'every generated filename is unique');
    }
}
