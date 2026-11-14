<?php

namespace App\Modules\Platform\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Setting;
use App\Modules\Platform\Models\AiCreditLedger;
use App\Modules\Platform\Models\AiCreditRequest;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saldo pertanyaan Asisten AI — bayar per penggunaan (keputusan
 * owner 2026-11-14). Kunci API tetap milik platform; tenant membeli
 * kredit pertanyaan (prabayar): ajukan isi ulang → transfer →
 * platform mengonfirmasi → saldo bertambah di buku besar.
 * Satu pertanyaan BERHASIL (status ok) memotong 1 kredit; intent
 * unknown & kegagalan penyedia GRATIS.
 *
 * Konkurensi meniru gerbang penagihan B-01: setiap mutasi saldo
 * berjalan di transaksi yang mengunci baris tenant lebih dulu,
 * sehingga potongan & konfirmasi terserialisasi per tenant dan
 * saldo tidak pernah negatif.
 */
class AiCreditService
{
    public const PRICE_KEY = 'ai_price_per_question';

    public const DEFAULT_PRICE = 250;

    public const MIN_TOPUP = 10;

    public const MAX_TOPUP = 10000;

    /** Harga per pertanyaan (Rp) dari pengaturan platform; default terbaca bila belum diatur. */
    public function pricePerQuestion(): int
    {
        $value = (int) (Setting::value(self::PRICE_KEY) ?? self::DEFAULT_PRICE);

        return $value > 0 ? $value : self::DEFAULT_PRICE;
    }

    /** Saldo pertanyaan tenant saat ini (0 bila belum pernah bergerak). */
    public function balance(Tenant $tenant): int
    {
        $last = AiCreditLedger::where('tenant_id', $tenant->id)
            ->orderByDesc('id')
            ->first();

        return $last === null ? 0 : (int) $last->balance_after;
    }

    /**
     * Ajukan isi ulang: harga dibekukan dari pengaturan saat ini.
     * Ditolak bila masih ada pengajuan menunggu.
     *
     * @throws ValidationException
     */
    public function requestTopup(Tenant $tenant, int $questions, User $actor): AiCreditRequest
    {
        if ($questions < self::MIN_TOPUP || $questions > self::MAX_TOPUP) {
            throw ValidationException::withMessages([
                'questions' => __('ui.ai_credits.questions_range', ['min' => self::MIN_TOPUP, 'max' => self::MAX_TOPUP]),
            ]);
        }

        return DB::transaction(function () use ($tenant, $questions) {
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();

            $pending = $tenant->aiCreditRequests()
                ->where('status', AiCreditRequest::STATUS_PENDING)->exists();
            if ($pending) {
                throw ValidationException::withMessages([
                    'questions' => __('ui.ai_credits.pending_exists'),
                ]);
            }

            $price = $this->pricePerQuestion();

            return AiCreditRequest::create([
                'tenant_id' => $tenant->id,
                'questions' => $questions,
                'unit_price' => $price,
                'amount' => $price * $questions,
                'status' => AiCreditRequest::STATUS_PENDING,
            ]);
        });
    }

    /**
     * Konfirmasi platform: saldo bertambah tepat satu kali —
     * status diperiksa ulang sesudah baris tenant + pengajuan
     * dikunci, sehingga konfirmasi ganda ditolak.
     *
     * @throws ValidationException
     */
    public function confirm(AiCreditRequest $request, User $actor): AiCreditRequest
    {
        return DB::transaction(function () use ($request, $actor) {
            Tenant::whereKey($request->tenant_id)->lockForUpdate()->firstOrFail();
            $fresh = AiCreditRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! $fresh->isPending()) {
                throw ValidationException::withMessages([
                    'status' => __('ui.ai_credits.not_pending'),
                ]);
            }

            $balance = $this->balance($fresh->tenant) + $fresh->questions;
            AiCreditLedger::create([
                'tenant_id' => $fresh->tenant_id,
                'kind' => 'topup',
                'questions_delta' => $fresh->questions,
                'balance_after' => $balance,
                'unit_price' => $fresh->unit_price,
                'amount' => $fresh->amount,
                'request_id' => $fresh->id,
                'actor_id' => $actor->id,
            ]);

            $fresh->update([
                'status' => AiCreditRequest::STATUS_CONFIRMED,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]);

            return $fresh;
        });
    }

    /** @throws ValidationException */
    public function reject(AiCreditRequest $request, User $actor): AiCreditRequest
    {
        return DB::transaction(function () use ($request, $actor) {
            Tenant::whereKey($request->tenant_id)->lockForUpdate()->firstOrFail();
            $fresh = AiCreditRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! $fresh->isPending()) {
                throw ValidationException::withMessages([
                    'status' => __('ui.ai_credits.not_pending'),
                ]);
            }

            $fresh->update([
                'status' => AiCreditRequest::STATUS_REJECTED,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]);

            return $fresh;
        });
    }

    /**
     * Potong 1 kredit untuk satu jawaban berhasil. Mengembalikan
     * false (tanpa memotong) bila saldo sudah habis — pemanggil
     * memeriksa saldo SEBELUM bertanya; fungsi ini adalah penjaga
     * terakhir agar saldo tidak pernah negatif.
     */
    public function chargeForAnswer(Tenant $tenant, User $actor): bool
    {
        return DB::transaction(function () use ($tenant, $actor) {
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();

            $balance = $this->balance($tenant);
            if ($balance < 1) {
                return false;
            }

            AiCreditLedger::create([
                'tenant_id' => $tenant->id,
                'kind' => 'usage',
                'questions_delta' => -1,
                'balance_after' => $balance - 1,
                'actor_id' => $actor->id,
            ]);

            return true;
        });
    }
}
