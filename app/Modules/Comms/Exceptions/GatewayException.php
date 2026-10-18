<?php

namespace App\Modules\Comms\Exceptions;

use RuntimeException;

/** Kegagalan di sisi penyedia gateway (HTTP, kredensial, jaringan). */
class GatewayException extends RuntimeException {}
