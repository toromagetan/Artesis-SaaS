<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A1 — Adopsi model produk persis Artesis (keputusan owner §188,
 * 14 Nov 2026): kategori → produk (master) → detail per tipe.
 *
 * Bentuk lama (ADR-015): `product_parents` (induk) + `products`
 * (varian ber-SKU). Bentuk baru: `products` menjadi master ringan
 * (category_id, brand, name) dan unit jual/stok adalah baris pada
 * lima tabel detail — frame_details, lens_details, rx_details,
 * softlens_details, accessories_details — masing-masing ber-id dan
 * ber-SKU sendiri, persis Artesis.
 *
 * Rujukan transaksional mengikuti pola Artesis: polimorfik
 * `detail_id` + `detail_type` (alias pendek frame/lens/rx/
 * softlens/accessory), terindeks, tanpa FK tunggal. Id detail =
 * id produk lama (ditanam eksplisit), jadi nilai rujukan pada 12
 * tabel transaksional tidak berubah angka — hanya kolomnya berganti
 * nama dan bertambah penanda tipe.
 *
 * Cap SaaS (OD-2, konfirmasi owner §192 "semua operasional per
 * tenant"): setiap tabel baru ber-`tenant_id` dan SKU unik per
 * tenant (Artesis unik global — tidak dipakai di sini).
 *
 * Penyimpangan kecil yang disengaja dari Artesis: rx_details.type
 * dan rx_details.index nullable (SaaS lama tidak punya padanannya;
 * mengarang nilai merusak makna data), barcode & image_path
 * dipertahankan per detail (fitur SaaS yang sudah berjalan), dan
 * is_process_item dipertahankan pada accessories_details.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> indeks penyangga FK sementara (down) */
    private array $fkSupportIndexes = [];

    /** @var array<string, string> tipe lama → tabel detail */
    private array $detailTables = [
        'frame' => 'frame_details',
        'lens' => 'lens_details',
        'rx' => 'rx_details',
        'softlens' => 'softlens_details',
        'accessory' => 'accessories_details',
    ];

    /** @var list<string> tabel transaksional ber-product_id */
    private array $referencingTables = [
        'broken_stocks',
        'incentive_adjustments',
        'incentive_items',
        'incentive_rules',
        'purchase_order_items',
        'purchase_request_items',
        'sale_items',
        'stock_layers',
        'stock_movements',
        'stock_opname_items',
        'stock_transfer_items',
    ];

    public function up(): void
    {
        $this->createNewTables();
        [$masterByParent, $orphanMasters] = $this->populateMasters();
        $this->populateDetails($masterByParent, $orphanMasters);
        $this->convertReferences();
        $this->swapProductTables();
    }

    public function down(): void
    {
        // Urutan wajib: tabel lama dibuat → varian diisi kembali →
        // baru FK rujukan boleh dipasang lagi (validasi FK menuntut
        // baris products sudah ada).
        $this->unswapProductTables();
        $this->restoreVariantsAndParents();
        $this->unconvertReferences();
        $this->dropNewTables();
    }

    // ---------------------------------------------------------------
    // up() langkah 1: tabel-tabel baru
    // ---------------------------------------------------------------

    private function createNewTables(): void
    {
        Schema::create('products_new_master', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->restrictOnDelete();
            $table->string('brand', 80)->nullable();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            // Jejak asal untuk down(): id product_parents lama.
            $table->unsignedBigInteger('legacy_parent_id')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'name']);
        });

        $detail = function (string $name, Closure $extra): void {
            Schema::create($name, function (Blueprint $table) use ($extra) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
                $table->foreignId('product_id')->constrained('products_new_master')->cascadeOnDelete();
                $table->string('sku', 50);
                $table->string('barcode', 50)->nullable();
                $table->boolean('is_active')->default(true);
                $table->decimal('cost_price', 15, 2)->default(0);
                $table->decimal('sale_price', 15, 2)->default(0);
                $table->string('image_path')->nullable();
                $extra($table);
                $table->timestamps();
                $table->unique(['tenant_id', 'sku']);
                $table->index(['tenant_id', 'product_id']);
            });
        };

        $detail('frame_details', function (Blueprint $table) {
            $table->string('color', 50)->nullable();
        });
        $detail('lens_details', function (Blueprint $table) {
            $table->enum('type', ['SV', 'Progresif', 'Bifocal'])->default('SV');
            $table->string('index', 20)->default('');
            $table->json('variant')->nullable();
            $table->enum('eye_side', ['R', 'L', 'Both'])->default('Both');
            $table->decimal('sph', 6, 2)->nullable();
            $table->decimal('cyl', 6, 2)->nullable();
            $table->decimal('add', 6, 2)->nullable();
        });
        $detail('rx_details', function (Blueprint $table) {
            $table->string('type', 50)->nullable();
            $table->string('index', 10)->nullable();
            $table->json('variant')->nullable();
        });
        $detail('softlens_details', function (Blueprint $table) {
            $table->string('color', 50)->nullable();
            $table->decimal('power', 6, 2)->nullable();
            // Produk berkedaluwarsa (perintah owner 14 Nov 2026):
            // penanda skema di detail; tanggalnya sendiri tetap per
            // batch pada receipt_items.expiry_date dan dibaca FIFO
            // lewat receipt_item_id — skema FIFO tidak berubah.
            $table->boolean('expiry_tracked')->default(false);
        });
        $detail('accessories_details', function (Blueprint $table) {
            $table->string('variant', 80)->nullable();
            $table->boolean('is_process_item')->default(false);
            // Cairan softlens hidup sebagai aksesoris dan sama-sama
            // berkedaluwarsa — kolom penanda yang sama.
            $table->boolean('expiry_tracked')->default(false);
        });

        Schema::create('product_detail_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('detail_id');
            $table->string('detail_type', 20);
            $table->string('key', 50);
            $table->string('value', 150);
            $table->timestamps();
            $table->index(['detail_id', 'detail_type']);
            $table->unique(['detail_id', 'detail_type', 'key']);
        });
    }

    // ---------------------------------------------------------------
    // up() langkah 2: induk lama & kelompok yatim → master baru
    // ---------------------------------------------------------------

    /**
     * @return array{0: array<int, int>, 1: array<string, int>} peta
     *                                                          product_parents.id → master.id, dan peta kelompok
     *                                                          yatim (kunci komposit) → master.id
     */
    private function populateMasters(): array
    {
        $map = [];

        foreach (DB::table('product_parents')->orderBy('id')->get() as $parent) {
            $map[(int) $parent->id] = (int) DB::table('products_new_master')->insertGetId([
                'tenant_id' => $parent->tenant_id,
                'category_id' => $parent->category_id,
                'brand' => $parent->brand,
                'name' => $parent->name,
                'is_active' => (bool) $parent->is_active,
                'legacy_parent_id' => (int) $parent->id,
                'created_at' => $parent->created_at,
                'updated_at' => $parent->updated_at,
            ]);
        }

        // Produk yatim (tanpa induk) dikelompokkan per
        // (tenant, tipe, brand, name) — pola backfill ADR-015.
        // Petanya disimpan di memori dengan kunci komposit; kolom
        // parent_id bertanda unsigned sehingga penanda negatif pada
        // baris tidak mungkin dipakai.
        $orphanMap = [];
        $orphanGroups = DB::table('products')
            ->select('tenant_id', 'type', 'brand', 'name')
            ->whereNull('parent_id')
            ->groupBy('tenant_id', 'type', 'brand', 'name')
            ->get();

        foreach ($orphanGroups as $group) {
            $orphanMap[$this->orphanKey($group->tenant_id, (string) $group->type, $group->brand, (string) $group->name)] = (int) DB::table('products_new_master')->insertGetId([
                'tenant_id' => $group->tenant_id,
                'category_id' => $this->defaultCategoryId((string) $group->type),
                'brand' => $group->brand,
                'name' => $group->name,
                'is_active' => true,
                'legacy_parent_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [$map, $orphanMap];
    }

    private function orphanKey(mixed $tenantId, string $type, mixed $brand, string $name): string
    {
        return json_encode([$tenantId, $type, $brand, $name]);
    }

    private function defaultCategoryId(string $type): ?int
    {
        $id = DB::table('product_categories')
            ->where('type', $type)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    // ---------------------------------------------------------------
    // up() langkah 3: baris products lama → detail per tipe
    // ---------------------------------------------------------------

    /**
     * @param  array<int, int>  $masterByParent
     * @param  array<string, int>  $orphanMasters
     */
    private function populateDetails(array $masterByParent, array $orphanMasters): void
    {
        $attributes = [];
        foreach (DB::table('product_attributes')->get() as $attr) {
            $attributes[(int) $attr->product_id][(string) $attr->key] = (string) $attr->value;
        }

        DB::table('products')->orderBy('id')->chunk(500, function ($rows) use ($masterByParent, $orphanMasters, $attributes) {
            foreach ($rows as $row) {
                $type = (string) $row->type;
                $table = $this->detailTables[$type] ?? null;
                if ($table === null) {
                    continue;
                }

                $masterId = $row->parent_id !== null
                    ? ($masterByParent[(int) $row->parent_id] ?? null)
                    : ($orphanMasters[$this->orphanKey($row->tenant_id, $type, $row->brand, (string) $row->name)] ?? null);
                if ($masterId === null) {
                    continue;
                }

                $attrs = $attributes[(int) $row->id] ?? [];
                $base = [
                    'id' => (int) $row->id,
                    'tenant_id' => $row->tenant_id,
                    'product_id' => $masterId,
                    'sku' => $row->sku,
                    'barcode' => $row->barcode,
                    'is_active' => (bool) $row->is_active,
                    'cost_price' => $row->cost_price,
                    'sale_price' => $row->sale_price,
                    'image_path' => $row->image_path,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];

                $consumed = [];
                $extra = match ($type) {
                    'frame' => ['color' => $row->color],
                    'lens' => $this->lensColumns($row, $attrs, $consumed),
                    'rx' => ['type' => null, 'index' => null, 'variant' => null],
                    'softlens' => $this->softlensColumns($row, $attrs, $consumed),
                    'accessory' => ['variant' => null, 'is_process_item' => (bool) $row->is_process_item],
                    default => [],
                };

                DB::table($table)->insert([...$base, ...$extra]);

                foreach ($attrs as $key => $value) {
                    if (in_array($key, $consumed, true)) {
                        continue;
                    }
                    DB::table('product_detail_attributes')->insert([
                        'detail_id' => (int) $row->id,
                        'detail_type' => $type,
                        'key' => $key,
                        'value' => $value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    /**
     * @param  array<string, string>  $attrs
     * @param  list<string>  $consumed
     * @return array<string, mixed>
     */
    private function lensColumns(object $row, array $attrs, array &$consumed): array
    {
        $consumed = ['index', 'lens_kind'];

        $raw = strtolower((string) ($attrs['lens_kind'] ?? ''));
        $kind = match (true) {
            str_contains($raw, 'prog') => 'Progresif',
            str_contains($raw, 'bif') => 'Bifocal',
            default => 'SV',
        };

        return [
            'type' => $kind,
            'index' => (string) ($attrs['index'] ?? ''),
            'variant' => null,
            'eye_side' => in_array($row->eye_side, ['R', 'L'], true) ? $row->eye_side : 'Both',
            'sph' => $row->sph,
            'cyl' => $row->cyl,
            'add' => $row->add,
        ];
    }

    /**
     * @param  array<string, string>  $attrs
     * @param  list<string>  $consumed
     * @return array<string, mixed>
     */
    private function softlensColumns(object $row, array $attrs, array &$consumed): array
    {
        $consumed = ['expiry_tracked'];

        return [
            'color' => $row->color,
            'power' => $row->power,
            'expiry_tracked' => ($attrs['expiry_tracked'] ?? '0') === '1',
        ];
    }

    // ---------------------------------------------------------------
    // up() langkah 4: rujukan transaksional → morph detail
    // ---------------------------------------------------------------

    private function convertReferences(): void
    {
        $this->convertWarrantyReplacement();

        foreach ($this->referencingTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'product_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['product_id']);
                $blueprint->string('detail_type', 20)->nullable()->after('product_id');
            });

            DB::table($table)
                ->join('products', "{$table}.product_id", '=', 'products.id')
                ->update(["{$table}.detail_type" => DB::raw('products.type')]);

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->renameColumn('product_id', 'detail_id');
                $blueprint->index(['detail_id', 'detail_type']);
            });

            $this->fixUniqueIndexes($table);
        }
    }

    /**
     * Indeks UNIK warisan yang mencakup kolom produk berganti nama
     * (mis. stock_opname_items: unik (stock_opname_id, product_id))
     * akan mencakup (…, detail_id) TANPA detail_type sesudah rename
     * — dua detail bertipe berbeda ber-id sama akan bertabrakan.
     * Indeks semacam itu dibangun ulang dengan detail_type
     * disisipkan sebelum detail_id. (Ditemukan uji OpnameTest:
     * "Duplicate entry '1-5'" untuk frame #5 + lens #5.)
     */
    private function fixUniqueIndexes(string $table): void
    {
        $rows = DB::select(
            'SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX FROM information_schema.STATISTICS '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0 AND INDEX_NAME != ? '
            .'ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$table, 'PRIMARY'],
        );
        $indexes = [];
        foreach ($rows as $row) {
            $indexes[$row->INDEX_NAME][] = $row->COLUMN_NAME;
        }
        foreach ($indexes as $name => $columns) {
            if (! in_array('detail_id', $columns, true) || in_array('detail_type', $columns, true)) {
                continue;
            }
            $newColumns = [];
            foreach ($columns as $column) {
                if ($column === 'detail_id') {
                    $newColumns[] = 'detail_type';
                }
                $newColumns[] = $column;
            }
            $newName = str_replace('_product_id_', '_detail_', $name);
            // Indeks lama bisa jadi penyangga FK (1553 bila langsung
            // dijatuhkan): buat indeks baru DULU (bentuknya lebih
            // longgar dari yang lama, jadi selalu sah atas data
            // berjalan), baru jatuhkan yang lama.
            Schema::table($table, function (Blueprint $blueprint) use ($newName, $newColumns) {
                $blueprint->unique($newColumns, $newName);
            });
            Schema::table($table, function (Blueprint $blueprint) use ($name) {
                $blueprint->dropUnique($name);
            });
        }
    }

    /**
     * Rujukan ke-14 yang bernama lain: warranty_replacements.
     * replacement_product_id (produk pengganti garansi) menjadi
     * replacement_detail_id + replacement_detail_type. Ditemukan
     * lewat enumerasi FK (KEY_COLUMN_USAGE), bukan nama kolom —
     * audit berbasis nama kolom melewatkan kolom bernama khusus.
     */
    private function convertWarrantyReplacement(): void
    {
        $table = 'warranty_replacements';
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'replacement_product_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropForeign(['replacement_product_id']);
            $blueprint->string('replacement_detail_type', 20)->nullable()->after('replacement_product_id');
        });

        DB::table($table)
            ->join('products', "{$table}.replacement_product_id", '=', 'products.id')
            ->update(["{$table}.replacement_detail_type" => DB::raw('products.type')]);

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->renameColumn('replacement_product_id', 'replacement_detail_id');
            $blueprint->index(['replacement_detail_id', 'replacement_detail_type'], 'warranty_replacements_detail_idx');
        });
    }

    /**
     * Pasangan down() dari fixUniqueIndexes: jatuhkan indeks unik
     * morph (mengandung detail_type + detail_id + kolom lain) dan
     * kembalikan peta nama→kolom bentuk asalnya (detail_type
     * dibuang, detail_id menjadi product_id, '_detail_' kembali
     * '_product_id_').
     *
     * @return array<string, list<string>>
     */
    private function dropMorphedUniqueIndexes(string $table): array
    {
        $rows = DB::select(
            'SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX FROM information_schema.STATISTICS '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0 AND INDEX_NAME != ? '
            .'ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$table, 'PRIMARY'],
        );
        $indexes = [];
        foreach ($rows as $row) {
            $indexes[$row->INDEX_NAME][] = $row->COLUMN_NAME;
        }
        $restored = [];
        foreach ($indexes as $name => $columns) {
            if (! in_array('detail_type', $columns, true) || ! in_array('detail_id', $columns, true) || count($columns) < 3) {
                continue;
            }
            $original = [];
            foreach ($columns as $column) {
                if ($column === 'detail_type') {
                    continue;
                }
                $original[] = $column === 'detail_id' ? 'product_id' : $column;
            }
            $restored[str_replace('_detail_', '_product_id_', $name)] = $original;
            // Indeks morph bisa jadi penyangga FK: pasang indeks
            // penyangga polos pada kolom awalan (sebelum detail_type)
            // lebih dulu, jatuhkan unik morph, dan catat penyangga
            // untuk dibuang sesudah unik asal pulih.
            $prefix = [];
            foreach ($columns as $column) {
                if ($column === 'detail_type') {
                    break;
                }
                $prefix[] = $column === 'detail_id' ? 'product_id' : $column;
            }
            $support = 'tmp_morph_fk_'.$table;
            if ($prefix !== [] && ! in_array($support, $this->fkSupportIndexes[$table] ?? [], true)) {
                $prefixNow = array_map(fn ($c) => $c === 'product_id' ? 'detail_id' : $c, $prefix);
                Schema::table($table, function (Blueprint $blueprint) use ($support, $prefixNow) {
                    $blueprint->index($prefixNow, $support);
                });
                $this->fkSupportIndexes[$table][] = $support;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($name) {
                $blueprint->dropUnique($name);
            });
        }

        return $restored;
    }

    private function unconvertWarrantyReplacement(): void
    {
        $table = 'warranty_replacements';
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'replacement_detail_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropIndex('warranty_replacements_detail_idx');
            $blueprint->renameColumn('replacement_detail_id', 'replacement_product_id');
            $blueprint->dropColumn('replacement_detail_type');
        });

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->foreign('replacement_product_id')->references('id')->on('products')->restrictOnDelete();
        });
    }

    // ---------------------------------------------------------------
    // up() langkah 5: tukar tabel products
    // ---------------------------------------------------------------

    private function swapProductTables(): void
    {
        Schema::drop('product_attributes');
        Schema::drop('products');
        Schema::drop('product_parents');
        Schema::rename('products_new_master', 'products');
    }

    // ---------------------------------------------------------------
    // down(): kebalikan up() — parents & varian direkonstruksi.
    // Nama/brand varian kembali dari master (pada bentuk ADR-015
    // nama varian selalu sama dengan nama induknya — resolveParent
    // mengelompokkan per (type, brand, name)); lens_kind teks bebas
    // kembali dalam bentuk ternormalisasi (single_vision /
    // progressive / bifocal) — satu-satunya titik lossy pada down().
    // ---------------------------------------------------------------

    private function unswapProductTables(): void
    {
        Schema::rename('products', 'products_new_master');

        Schema::create('product_parents', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('brand', 80)->nullable();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->restrictOnDelete();
            $table->string('type', 20);
            $table->boolean('is_active')->default(true);
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
            $table->timestamps();
        });

        foreach (DB::table('products_new_master')->whereNotNull('legacy_parent_id')->orderBy('legacy_parent_id')->get() as $master) {
            DB::table('product_parents')->insert([
                'id' => (int) $master->legacy_parent_id,
                'name' => $master->name,
                'brand' => $master->brand,
                'category_id' => $master->category_id,
                'type' => $this->masterType($master),
                'is_active' => (bool) $master->is_active,
                'tenant_id' => $master->tenant_id,
                'created_at' => $master->created_at,
                'updated_at' => $master->updated_at,
            ]);
        }

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('product_parents')->nullOnDelete();
            $table->string('sku', 50);
            $table->string('barcode', 50)->nullable();
            $table->string('type', 20);
            $table->string('name', 150);
            $table->string('brand', 80)->nullable();
            $table->string('color', 50)->nullable();
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('sale_price', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_process_item')->default(false);
            $table->decimal('sph', 6, 2)->nullable();
            $table->decimal('cyl', 6, 2)->nullable();
            $table->decimal('add', 6, 2)->nullable();
            $table->decimal('power', 6, 2)->nullable();
            $table->string('eye_side', 10)->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();
        });

        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('key', 50);
            $table->string('value', 150);
            $table->timestamps();
        });
    }

    private function masterType(object $master): string
    {
        if ($master->category_id !== null) {
            $type = DB::table('product_categories')->where('id', $master->category_id)->value('type');
            if ($type !== null) {
                return (string) $type;
            }
        }

        foreach ($this->detailTables as $alias => $table) {
            if (DB::table($table)->where('product_id', $master->id)->exists()) {
                return $alias;
            }
        }

        return 'frame';
    }

    private function restoreVariantsAndParents(): void
    {
        $masters = DB::table('products_new_master')->get()->keyBy('id');

        foreach ($this->detailTables as $alias => $table) {
            foreach (DB::table($table)->orderBy('id')->get() as $detail) {
                $master = $masters->get($detail->product_id);
                if ($master === null) {
                    continue;
                }

                $extra = match ($alias) {
                    'frame' => ['color' => $detail->color],
                    'lens' => [
                        'color' => null,
                        'sph' => $detail->sph, 'cyl' => $detail->cyl, 'add' => $detail->add,
                        'eye_side' => $detail->eye_side === 'Both' ? null : $detail->eye_side,
                    ],
                    'softlens' => ['color' => $detail->color, 'power' => $detail->power],
                    'accessory' => ['is_process_item' => (bool) $detail->is_process_item],
                    default => [],
                };

                DB::table('products')->insert([
                    'id' => (int) $detail->id,
                    'tenant_id' => $detail->tenant_id,
                    'parent_id' => $master->legacy_parent_id !== null ? (int) $master->legacy_parent_id : null,
                    'sku' => $detail->sku,
                    'barcode' => $detail->barcode,
                    'type' => $alias,
                    'name' => $master->name,
                    'brand' => $master->brand,
                    'cost_price' => $detail->cost_price,
                    'sale_price' => $detail->sale_price,
                    'is_active' => (bool) $detail->is_active,
                    'image_path' => $detail->image_path,
                    'created_at' => $detail->created_at,
                    'updated_at' => $detail->updated_at,
                    ...$extra,
                ]);

                if ($alias === 'softlens') {
                    DB::table('product_attributes')->insert([
                        'product_id' => (int) $detail->id, 'key' => 'expiry_tracked',
                        'value' => $detail->expiry_tracked ? '1' : '0',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

                if ($alias === 'lens') {
                    $kind = match ($detail->type) {
                        'Progresif' => 'progressive',
                        'Bifocal' => 'bifocal',
                        default => 'single_vision',
                    };
                    DB::table('product_attributes')->insert([
                        ['product_id' => (int) $detail->id, 'key' => 'index', 'value' => (string) $detail->index, 'created_at' => now(), 'updated_at' => now()],
                        ['product_id' => (int) $detail->id, 'key' => 'lens_kind', 'value' => $kind, 'created_at' => now(), 'updated_at' => now()],
                    ]);
                }
            }
        }

        foreach (DB::table('product_detail_attributes')->get() as $attr) {
            DB::table('product_attributes')->insert([
                'product_id' => (int) $attr->detail_id,
                'key' => $attr->key,
                'value' => $attr->value,
                'created_at' => $attr->created_at,
                'updated_at' => $attr->updated_at,
            ]);
        }
    }

    private function unconvertReferences(): void
    {
        foreach ($this->referencingTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'detail_id')) {
                continue;
            }

            // Balikkan indeks unik hasil fixUniqueIndexes: buang
            // bentuk morph-nya, buat ulang bentuk asal (tanpa
            // detail_type, kolom bernama product_id lagi).
            $restoredUniques = $this->dropMorphedUniqueIndexes($table);

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropIndex(['detail_id', 'detail_type']);
                $blueprint->renameColumn('detail_id', 'product_id');
                $blueprint->dropColumn('detail_type');
            });

            foreach ($restoredUniques as $name => $columns) {
                Schema::table($table, function (Blueprint $blueprint) use ($name, $columns) {
                    $blueprint->unique($columns, $name);
                });
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            });

            // Unik asal sudah pulih & FK punya penyangga: indeks
            // penyangga sementara dibuang.
            foreach ($this->fkSupportIndexes[$table] ?? [] as $support) {
                Schema::table($table, function (Blueprint $blueprint) use ($support) {
                    $blueprint->dropIndex($support);
                });
            }
            unset($this->fkSupportIndexes[$table]);
        }

        $this->unconvertWarrantyReplacement();
    }

    private function dropNewTables(): void
    {
        Schema::drop('product_detail_attributes');
        foreach ($this->detailTables as $table) {
            Schema::drop($table);
        }
        Schema::drop('products_new_master');
    }
};
