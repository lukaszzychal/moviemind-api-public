<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class CpuLoadVerificationTest extends TestCase
{
    public function test_cpu_load_function_exists(): void
    {
        $this->assertTrue(
            function_exists('sys_getloadavg'),
            'sys_getloadavg() function is not available. This is expected on Windows or if PHP was compiled without this function.'
        );
    }

    public function test_cpu_load_returns_array(): void
    {
        if (! function_exists('sys_getloadavg')) {
            $this->markTestSkipped('sys_getloadavg() is not available on this system');
        }

        $load = sys_getloadavg();

        $this->assertIsArray($load, 'sys_getloadavg() should return an array');
        $this->assertCount(3, $load, 'sys_getloadavg() should return array with 3 elements [1min, 5min, 15min]');
        $this->assertIsFloat($load[0], '1-minute load should be float');
        $this->assertIsFloat($load[1], '5-minute load should be float');
        $this->assertIsFloat($load[2], '15-minute load should be float');
    }

    public function test_cpu_load_values_reasonable(): void
    {
        if (! function_exists('sys_getloadavg')) {
            $this->markTestSkipped('sys_getloadavg() is not available on this system');
        }

        $load = sys_getloadavg();

        // Load powinien być >= 0 (nie może być ujemny)
        $this->assertGreaterThanOrEqual(0.0, $load[0], '1-minute load should be >= 0');
        $this->assertGreaterThanOrEqual(0.0, $load[1], '5-minute load should be >= 0');
        $this->assertGreaterThanOrEqual(0.0, $load[2], '15-minute load should be >= 0');

        // Load nie powinien być ekstremalnie wysoki (np. > 1000) - wskazuje na błąd
        $this->assertLessThan(1000.0, $load[0], '1-minute load seems unreasonably high (>1000)');
    }

    public function test_cpu_load_consistency(): void
    {
        if (! function_exists('sys_getloadavg')) {
            $this->markTestSkipped('sys_getloadavg() is not available on this system');
        }

        // Sprawdź czy funkcja zwraca spójne wartości (nie random)
        $load1 = sys_getloadavg();
        usleep(100000); // 0.1s
        $load2 = sys_getloadavg();

        // Load powinien być podobny (różnica < 10) w krótkim czasie
        $diff1min = abs($load1[0] - $load2[0]);
        $this->assertLessThan(10.0, $diff1min, 'Load should be relatively stable in short time');
    }
}
