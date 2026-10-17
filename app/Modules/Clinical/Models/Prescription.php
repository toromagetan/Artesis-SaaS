<?php

namespace App\Modules\Clinical\Models;

use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Support\Concerns\BranchScoped;
use Database\Factories\PrescriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resep / hasil refraksi (Blueprint B.2 + D.5).
 * Satu-satunya tempat PDR/PDL hidup. Terkunci dari edit begitu
 * used_at terisi (pertama kali dipakai transaksi).
 */
class Prescription extends Model
{
    use BranchScoped, HasFactory;

    protected $fillable = [
        'customer_id', 'branch_id', 'examined_at', 'examiner_id',
        'od_sph', 'od_cyl', 'od_axis', 'od_add', 'od_pdr',
        'os_sph', 'os_cyl', 'os_axis', 'os_add', 'os_pdl',
        'pd_total', 'prism', 'notes', 'used_at',
    ];

    protected function casts(): array
    {
        return [
            'examined_at' => 'date',
            'used_at' => 'datetime',
            'od_sph' => 'decimal:2', 'od_cyl' => 'decimal:2', 'od_add' => 'decimal:2', 'od_pdr' => 'decimal:1',
            'os_sph' => 'decimal:2', 'os_cyl' => 'decimal:2', 'os_add' => 'decimal:2', 'os_pdl' => 'decimal:1',
            'pd_total' => 'decimal:1',
        ];
    }

    protected static function newFactory()
    {
        return PrescriptionFactory::new();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function examiner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'examiner_id');
    }

    public function isLocked(): bool
    {
        return $this->used_at !== null;
    }

    /** Nilai satu mata sebagai array terstruktur ('od'|'os'). */
    public function eye(string $side): array
    {
        return [
            'sph' => (string) $this->{$side.'_sph'},
            'cyl' => (string) $this->{$side.'_cyl'},
            'axis' => $this->{$side.'_axis'},
            'add' => $this->{$side.'_add'} !== null ? (string) $this->{$side.'_add'} : null,
            'pd' => $side === 'od'
                ? ($this->od_pdr !== null ? (string) $this->od_pdr : null)
                : ($this->os_pdl !== null ? (string) $this->os_pdl : null),
        ];
    }
}
