<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Models\AiSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Penerjemah pertanyaan → intent (F5.1). Satu-satunya tugas model
 * AI: memetakan bahasa alami ke SATU intent dari daftar putih +
 * parameter tervalidasi. Model TIDAK PERNAH menghitung angka dan
 * TIDAK menerima data toko — hanya teks pertanyaan + konteks
 * tanggal. Keluaran di luar skema = unknown.
 */
class AiTranslator
{
    public const INTENTS = ['sales_total', 'stock_qty', 'top_product', 'payables_total', 'unknown'];

    /** @return array{intent: string, params: array<string, mixed>} */
    public function translate(string $question): array
    {
        $setting = AiSetting::current();
        if (! $setting->isConfigured()) {
            throw new RuntimeException('ai_not_configured');
        }

        $system = 'Anda menerjemahkan pertanyaan pemilik toko optik menjadi JSON. '
            .'Balas HANYA JSON {"intent": ..., "params": {...}} tanpa teks lain. '
            .'intent yang sah: sales_total (total penjualan/omzet), stock_qty (sisa stok produk), '
            .'top_product (produk terlaris), payables_total (total utang supplier), unknown (di luar itu, '
            .'termasuk semua permintaan menulis/mengubah/menghapus data). '
            .'params boleh berisi: period ("current_month", "today", atau "YYYY-MM"), '
            .'product (nama produk yang disebut), branch (nama cabang yang disebut). '
            .'Hari ini: '.now()->toDateString().'.';

        $response = Http::withToken($setting->apiKey())
            ->timeout(25)
            ->acceptJson()
            ->post(rtrim($setting->base_url, '/').'/chat/completions', [
                'model' => $setting->model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $question],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('ai_provider_error');
        }

        $content = (string) $response->json('choices.0.message.content', '');
        $decoded = json_decode($content, true);
        if (! is_array($decoded) || ! in_array($decoded['intent'] ?? null, self::INTENTS, true)) {
            return ['intent' => 'unknown', 'params' => []];
        }

        $params = is_array($decoded['params'] ?? null) ? $decoded['params'] : [];
        $clean = [];
        if (isset($params['period']) && is_string($params['period'])
            && ($params['period'] === 'current_month' || $params['period'] === 'today' || preg_match('/^\d{4}-\d{2}$/', $params['period']) === 1)) {
            $clean['period'] = $params['period'];
        }
        foreach (['product', 'branch'] as $key) {
            if (isset($params[$key]) && is_string($params[$key]) && $params[$key] !== '') {
                $clean[$key] = mb_substr($params[$key], 0, 100);
            }
        }

        return ['intent' => $decoded['intent'], 'params' => $clean];
    }
}
