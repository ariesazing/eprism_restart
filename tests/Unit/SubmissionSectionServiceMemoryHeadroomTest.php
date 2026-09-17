<?php

namespace Tests\Unit;

use App\Services\SubmissionSectionService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * withHeadroomForLargeEmbeddedImages() is private (a thin ini_set/finally wrapper, no DB
 * dependency), so reflection exercises it directly: it must raise memory_limit for the
 * duration of the callback and always restore the previous value afterward, even when the
 * callback throws — a leaked raised limit would defeat the whole point of scoping it to just
 * this one call.
 */
class SubmissionSectionServiceMemoryHeadroomTest extends TestCase
{
    private function callHeadroom(callable $callback): mixed
    {
        $method = new ReflectionMethod(SubmissionSectionService::class, 'withHeadroomForLargeEmbeddedImages');
        $method->setAccessible(true);

        return $method->invoke(new SubmissionSectionService, $callback);
    }

    public function test_memory_limit_is_raised_during_the_callback_and_restored_after(): void
    {
        $previous = ini_get('memory_limit');

        $duringCallback = $this->callHeadroom(fn () => ini_get('memory_limit'));

        $this->assertSame('512M', $duringCallback);
        $this->assertSame($previous, ini_get('memory_limit'));
    }

    public function test_memory_limit_is_restored_even_if_the_callback_throws(): void
    {
        $previous = ini_get('memory_limit');

        try {
            $this->callHeadroom(function () {
                throw new \RuntimeException('boom');
            });
            $this->fail('Expected exception was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame($previous, ini_get('memory_limit'));
    }
}
