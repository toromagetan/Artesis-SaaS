<?php

namespace App\Modules\Platform\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\AuditLog;

/**
 * Penulis jejak audit kanonis (Blueprint B. Platform + G.1).
 * Semua modul menulis audit LEWAT kelas ini — tidak ada penulisan
 * audit_logs secara langsung di tempat lain.
 *
 * Nilai yang tampak rahasia (kunci mengandung password/token/secret)
 * disamarkan sebelum disimpan.
 */
class AuditLogger
{
    private const SENSITIVE_PATTERN = '/password|token|secret|credential/i';

    public function log(
        string $action,
        string $subjectType,
        ?int $subjectId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $branchId = null,
        ?User $actor = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => ($actor ?? auth()->user())?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'branch_id' => $branchId,
            'old_values' => $oldValues === null ? null : $this->mask($oldValues),
            'new_values' => $newValues === null ? null : $this->mask($newValues),
            'ip' => app()->bound('request') ? request()?->ip() : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function mask(array $values): array
    {
        $masked = [];
        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_PATTERN, $key)) {
                $masked[$key] = '***';
            } elseif (is_array($value)) {
                $masked[$key] = $this->mask($value);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }
}
