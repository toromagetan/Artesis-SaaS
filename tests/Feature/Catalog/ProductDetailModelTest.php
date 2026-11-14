<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\FrameDetail;
use App\Modules\Catalog\Models\LensDetail;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductDetailAttribute;
use App\Modules\Catalog\Models\SoftlensDetail;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A1 — lapisan model detail: master → detail per tipe, morph map,
 * atribut morph, dan deskriptor detail.
 */
class ProductDetailModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_exposes_details_by_category_type(): void
    {
        $master = Product::factory()->forType('lens')->create(['name' => 'Lensa Uji']);
        $lens = LensDetail::factory()->create(['product_id' => $master->id, 'sph' => -2.5, 'cyl' => -0.75]);

        $this->assertSame('lens', $master->typeKey());
        $this->assertTrue($master->details()->get()->contains($lens));
        $this->assertTrue($master->allDetails()->contains(fn ($d) => $d->is($lens)));
        $this->assertTrue($lens->product->is($master));
        $this->assertSame('SPH -2.50 · CYL -0.75', $lens->detailLabel());
    }

    public function test_morph_map_resolves_short_aliases(): void
    {
        $this->assertSame(FrameDetail::class, Relation::getMorphedModel('frame'));
        $this->assertSame('lens', Relation::getMorphAlias(LensDetail::class));

        $soft = SoftlensDetail::factory()->create();
        $attr = $soft->detailAttributes()->create(['key' => 'wear_period', 'value' => 'monthly']);

        $this->assertInstanceOf(ProductDetailAttribute::class, $attr);
        $this->assertTrue($attr->detail->is($soft));
        $this->assertSame('monthly', $soft->attributeValue('wear_period'));
        $this->assertSame('softlens', $attr->detail_type);
    }

    public function test_detail_factories_cover_all_types(): void
    {
        foreach (Product::DETAIL_MODELS as $alias => $model) {
            $detail = $model::factory()->create();
            $this->assertSame($alias, $detail::DETAIL_TYPE);
            $this->assertNotNull($detail->sku);
            $this->assertNotNull($detail->product);
        }
    }
}
