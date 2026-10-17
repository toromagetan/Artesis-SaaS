<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Jurnal manual (F1.2, izin journals.manual — Blueprint D.9):
 * satu-satunya jalur posting jurnal di luar listener peristiwa.
 * Aturan khusus jurnal manual: akun ringkasan (memiliki anak)
 * DILARANG menjadi tujuan baris — posting harus ke akun daun.
 * Keseimbangan, akun aktif, dan kunci periode ditegakkan
 * PostingService seperti semua posting lain.
 */
class ManualJournalService
{
    public function __construct(
        private readonly PostingService $posting,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<int, array{account_id: int, branch_id?: ?int, debit?: string, credit?: string, memo?: ?string}>  $lines
     */
    public function postManual(User $actor, string $date, ?string $description, array $lines): Journal
    {
        $prepared = [];
        foreach ($lines as $i => $line) {
            $account = Account::find((int) $line['account_id']);
            if ($account === null) {
                throw ValidationException::withMessages(["lines.{$i}.account_id" => __('ui.accounting.account_missing', ['code' => (string) $line['account_id']])]);
            }
            if ($account->isSummary()) {
                throw ValidationException::withMessages(["lines.{$i}.account_id" => __('ui.accounting.summary_not_allowed', ['code' => $account->code])]);
            }
            $prepared[] = [
                'account' => $account->code,
                'branch_id' => $line['branch_id'] ?? null,
                'debit' => (string) ($line['debit'] ?? '0'),
                'credit' => (string) ($line['credit'] ?? '0'),
                'memo' => $line['memo'] ?? null,
            ];
        }

        $journal = $this->posting->post('manual', null, $date, $prepared, $description, $actor);

        $this->audit->log(action: 'journals.manual', subjectType: 'journal', subjectId: $journal->id,
            newValues: ['number' => $journal->number, 'date' => $date], actor: $actor);

        return $journal;
    }
}
