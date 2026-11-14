<?php

namespace App\Modules\Catalog\Models;

use App\Support\Concerns\TenantScoped;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Master produk (A1 — persis Artesis): tingkat atas katalog.
 * Ringan — category_id, brand, name, is_active. Unit jual/stok
 * adalah baris detail per tipe di bawah master ini (FrameDetail,
 * LensDetail, RxDetail, SoftlensDetail, AccessoriesDetail).
 */
class Product extends Model
{
    use HasFactory, TenantScoped;

    /** Alias tipe = kunci kategori & alias morph detail. */
    public const TYPES = ['frame', 'lens', 'rx', 'softlens', 'accessory'];

    /** @var array<string, class-string<Model>> alias → model detail */
    public const DETAIL_MODELS = [
        'frame' => FrameDetail::class,
        'lens' => LensDetail::class,
        'rx' => RxDetail::class,
        'softlens' => SoftlensDetail::class,
        'accessory' => AccessoriesDetail::class,
    ];

    /** @var array<string, string> alias → nama relasi detail pada master */
    public const DETAIL_RELATIONS = [
        'frame' => 'frameDetails',
        'lens' => 'lensDetails',
        'rx' => 'rxDetails',
        'softlens' => 'softlensDetails',
        'accessory' => 'accessoriesDetails',
    ];

    protected $fillable = [
        'tenant_id', 'category_id', 'brand', 'name', 'is_active', 'legacy_parent_id',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function frameDetails(): HasMany
    {
        return $this->hasMany(FrameDetail::class);
    }

    public function lensDetails(): HasMany
    {
        return $this->hasMany(LensDetail::class);
    }

    public function rxDetails(): HasMany
    {
        return $this->hasMany(RxDetail::class);
    }

    public function softlensDetails(): HasMany
    {
        return $this->hasMany(SoftlensDetail::class);
    }

    public function accessoriesDetails(): HasMany
    {
        return $this->hasMany(AccessoriesDetail::class);
    }

    /** Kunci tipe master dari kategorinya (null bila tanpa kategori). */
    public function typeKey(): ?string
    {
        return $this->category?->type;
    }

    /** Relasi detail yang sesuai tipe kategori master ini. */
    public function details(): HasMany
    {
        return match ($this->typeKey()) {
            'lens' => $this->lensDetails(),
            'rx' => $this->rxDetails(),
            'softlens' => $this->softlensDetails(),
            'accessory' => $this->accessoriesDetails(),
            default => $this->frameDetails(),
        };
    }

    /** Seluruh baris detail master ini, apapun tabelnya. */
    public function allDetails(): Collection
    {
        return collect()
            ->merge($this->frameDetails)
            ->merge($this->lensDetails)
            ->merge($this->rxDetails)
            ->merge($this->softlensDetails)
            ->merge($this->accessoriesDetails);
    }

    public function typeLabel(): string
    {
        $type = $this->typeKey();

        return $type !== null ? __('ui.products.types.'.$type) : '';
    }

    protected static function newFactory()
    {
        return ProductFactory::new();
    }
}
