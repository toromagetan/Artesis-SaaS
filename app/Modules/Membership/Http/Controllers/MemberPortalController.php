<?php

namespace App\Modules\Membership\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Membership\Services\MemberPortalService;
use Illuminate\View\View;

/**
 * Halaman portal member PUBLIK ber-token (F3.7). Tanpa autentikasi —
 * token di URL adalah satu-satunya kredensial; resolusi gagal = 404
 * (tidak membedakan token tidak ada vs dicabut).
 */
class MemberPortalController extends Controller
{
    public function show(string $token, MemberPortalService $portal): View
    {
        $customer = $portal->resolve($token);
        abort_if($customer === null, 404);

        return view('portal.show', $portal->dataFor($customer));
    }
}
