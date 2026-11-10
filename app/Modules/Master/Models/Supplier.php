<?php

namespace App\Modules\Master\Models;

use App\Support\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use TenantScoped;

    protected $fillable = ['name', 'contact_person', 'phone', 'email', 'address', 'is_active', 'tenant_id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
