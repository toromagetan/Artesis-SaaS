<?php

namespace App\Modules\Platform\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\ApiToken;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Penerbitan & verifikasi token API (F4.6): token mentah 48 char
 * diterbitkan sekali, yang disimpan hanya sha256-nya. Verifikasi
 * menolak token tak dikenal / tercabut dan mencatat pemakaian.
 * Token adalah kredensial integrasi tingkat tenant yang diterbitkan
 * pemegang api_tokens.manage — pagar cabang aktor tidak berlaku di
 * jalur API; filter cabang tersedia sebagai parameter kueri.
 */
class ApiTokenService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  string[]  $scopes
     * @return array{token: ApiToken, plain: string}
     */
    public function issue(User $actor, string $name, array $scopes, ?\DateTimeInterface $expiresAt = null): array
    {
        $scopes = array_values(array_intersect(ApiToken::SCOPES, $scopes));
        if ($scopes === []) {
            throw ValidationException::withMessages(['scopes' => __('ui.api_tokens.scopes_required')]);
        }
        if ($expiresAt !== null && $expiresAt <= now()) {
            throw ValidationException::withMessages(['expires_at' => __('ui.api_tokens.expires_future')]);
        }
        $plain = Str::random(48);
        $token = ApiToken::create([
            'name' => $name,
            'token_hash' => hash('sha256', $plain),
            'scopes' => $scopes,
            'expires_at' => $expiresAt,
            'created_by' => $actor->id,
        ]);
        $this->audit->log(
            action: 'api_token.issue', subjectType: 'api_token', subjectId: $token->id,
            newValues: ['name' => $name, 'scopes' => $scopes, 'expires_at' => $expiresAt?->format('Y-m-d H:i:s')], actor: $actor,
        );

        return ['token' => $token, 'plain' => $plain];
    }

    /** Verifikasi token mentah; mencatat last_used_at bila sah. */
    public function verify(?string $plain): ?ApiToken
    {
        if ($plain === null || $plain === '') {
            return null;
        }
        $token = ApiToken::where('token_hash', hash('sha256', $plain))->first();
        if ($token === null || ! $token->isUsable()) {
            return null;
        }
        $token->forceFill(['last_used_at' => now()])->save();

        return $token;
    }

    public function revoke(User $actor, ApiToken $token): ApiToken
    {
        if ($token->revoked_at === null) {
            $token->forceFill(['revoked_at' => now()])->save();
            $this->audit->log(
                action: 'api_token.revoke', subjectType: 'api_token', subjectId: $token->id, actor: $actor,
            );
        }

        return $token;
    }
}
