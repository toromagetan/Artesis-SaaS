<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Concurrency\ConcurrencyDatabase;

/**
 * LNT-007: pagar nama database test konkurensi — hanya nama
 * berpola lentera_conc_<unik> yang boleh dibuat/dihapus test.
 */
class ConcurrencyDatabaseTest extends TestCase
{
    public function test_unique_name_matches_the_safe_pattern_and_varies(): void
    {
        $a = ConcurrencyDatabase::uniqueName();
        $b = ConcurrencyDatabase::uniqueName();
        $this->assertMatchesRegularExpression('/^lentera_conc_\d+_[a-f0-9]{8}$/', $a);
        $this->assertNotSame($a, $b);
        ConcurrencyDatabase::assertSafeName($a); // tidak melempar
    }

    public function test_guard_rejects_fixed_and_foreign_names(): void
    {
        foreach (['lentera_conc', 'lentera', 'lentera_test', 'lentera_conc ', 'lentera_conc-x',
            'lentera_conc_x;DROP DATABASE lentera', 'mysql', ''] as $bad) {
            try {
                ConcurrencyDatabase::assertSafeName($bad);
                $this->fail("Nama seharusnya ditolak: '{$bad}'");
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
