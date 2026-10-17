<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalLine;
use App\Support\Money;

/**
 * Buku besar (F1.2, Blueprint D.9): mutasi satu akun dalam rentang
 * tanggal dengan saldo awal, saldo berjalan, dan saldo akhir.
 *
 * Saldo dinyatakan dalam sisi normal akun: aset & beban bertambah
 * di debit; liabilitas, ekuitas & pendapatan bertambah di kredit.
 * Filter cabang memakai dimensi branch_id pada baris jurnal.
 * Setiap baris membawa jurnal sumbernya (penelusuran / drill-down).
 */
class LedgerService
{
    /**
     * @return array{
     *     opening: Money, closing: Money, total_debit: Money, total_credit: Money,
     *     entries: array<int, array{date: string, journal_id: int, number: string, source_type: string,
     *         description: ?string, memo: ?string, debit: Money, credit: Money, balance: Money}>
     * }
     */
    public function ledger(Account $account, string $from, string $to, ?int $branchId = null): array
    {
        $debitNormal = in_array($account->group, ['asset', 'expense'], true);

        $base = JournalLine::query()
            ->where('journal_lines.account_id', $account->id)
            ->when($branchId !== null, fn ($q) => $q->where('journal_lines.branch_id', $branchId))
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id');

        // Saldo awal: seluruh mutasi sebelum tanggal mulai.
        $openingRow = (clone $base)
            ->where('journals.journal_date', '<', $from)
            ->selectRaw('COALESCE(SUM(journal_lines.debit),0) as d, COALESCE(SUM(journal_lines.credit),0) as c')
            ->first();
        $balance = $this->signed($debitNormal, (string) $openingRow->d, (string) $openingRow->c);
        $opening = $balance;

        $lines = (clone $base)
            ->whereBetween('journals.journal_date', [$from, $to])
            ->orderBy('journals.journal_date')
            ->orderBy('journals.id')
            ->orderBy('journal_lines.id')
            ->get([
                'journal_lines.id as line_id', 'journal_lines.debit', 'journal_lines.credit', 'journal_lines.memo',
                'journals.id as journal_id', 'journals.number', 'journals.journal_date', 'journals.source_type', 'journals.description',
            ]);

        $entries = [];
        $totalDebit = Money::zero();
        $totalCredit = Money::zero();
        foreach ($lines as $line) {
            $debit = Money::fromDecimal((string) $line->debit);
            $credit = Money::fromDecimal((string) $line->credit);
            $totalDebit = $totalDebit->add($debit);
            $totalCredit = $totalCredit->add($credit);
            $balance = $balance->add($this->signed($debitNormal, (string) $line->debit, (string) $line->credit));
            $entries[] = [
                'date' => (string) $line->journal_date,
                'journal_id' => (int) $line->journal_id,
                'number' => (string) $line->number,
                'source_type' => (string) $line->source_type,
                'description' => $line->description,
                'memo' => $line->memo,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
            ];
        }

        return [
            'opening' => $opening,
            'closing' => $balance,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'entries' => $entries,
        ];
    }

    /** Selisih bertanda menurut sisi normal akun. */
    private function signed(bool $debitNormal, string $debit, string $credit): Money
    {
        $d = Money::fromDecimal($debit);
        $c = Money::fromDecimal($credit);

        return $debitNormal ? $d->sub($c) : $c->sub($d);
    }
}
