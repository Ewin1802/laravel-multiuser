<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use App\Support\Barcode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, BelongsToStore;

    /*
    | Jenis produk (kolom type):
    |   item        Barang        dijual di kasir, stok dihitung
    |   menu        Menu racikan  dijual di kasir, stok TIDAK dihitung
    |   ingredient  Bahan baku    tidak tampil di kasir, stok dihitung
    */
    public const TYPE_ITEM = 'item';
    public const TYPE_MENU = 'menu';
    public const TYPE_INGREDIENT = 'ingredient';

    public const TYPES = [
        self::TYPE_ITEM => 'Barang',
        self::TYPE_MENU => 'Menu racikan',
        self::TYPE_INGREDIENT => 'Bahan baku',
    ];

    protected $attributes = [
        'type' => self::TYPE_ITEM,
    ];

    protected $fillable = [
        'category_id',
        'type',
        'code',
        'barcode',
        'name',
        'description',
        'price',
        'cost_price',
        'image',
        'stock',
        'status',
        'is_favorite',
        'base_unit',
        'track_expiry',
        'expiry_warning_days',
    ];

    // price/stock/status sengaja TIDAK di-cast supaya format JSON sama
    // dengan versi lama yang sudah dibaca aplikasi kasir.
    protected $casts = [
        'track_expiry' => 'boolean',
        'expiry_warning_days' => 'integer',
    ];

        /**
     * Produk tanpa barcode otomatis mendapat kode toko EAN-13 berawalan "20".
     * Berlaku dari form, import Excel, seeder, maupun API.
     */
    protected static function booted(): void
    {
        // produk baru: id baru ada setelah tersimpan
        static::created(function (Product $product) {
            if (blank($product->barcode)) {
                $product->barcode = Barcode::internal($product->id);
                $product->saveQuietly();
            }
        });

        // satuan terkecil di product_units selalu ikut kolom produk
        static::saved(function (Product $product) {
            app(\App\Services\ProductUnitService::class)->syncBase($product);
        });

        // produk lama yang disimpan dengan barcode kosong → isi kode toko
        static::saving(function (Product $product) {
            if ($product->exists && blank($product->barcode)) {
                $product->barcode = Barcode::internal($product->id);
            }
        });
    }

    // ------------------------------------------------------------ jenis

    /** Menu racikan: dibuat saat dipesan, stok tidak dihitung. */
    public function isMenu(): bool
    {
        return $this->type === self::TYPE_MENU;
    }

    public function isIngredient(): bool
    {
        return $this->type === self::TYPE_INGREDIENT;
    }

    /** Stok dihitung (barang & bahan baku). */
    public function tracksStock(): bool
    {
        return ! $this->isMenu();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? self::TYPES[self::TYPE_ITEM];
    }

    /** Produk yang stoknya dihitung: untuk Stok Masuk, Expire, Buka Kemasan, dst. */
    public function scopeStocked($query)
    {
        return $query->where($this->qualifyColumn('type'), '!=', self::TYPE_MENU);
    }

    /** Produk yang tampil & bisa dijual di kasir. */
    public function scopeSellable($query)
    {
        return $query->where($this->qualifyColumn('type'), '!=', self::TYPE_INGREDIENT);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /** Semua satuan jual, urut dari terkecil. */
    public function units(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductUnit::class)->orderBy('conversion')->orderBy('sort');
    }

    public function baseUnitRow(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProductUnit::class)->where('is_base', true);
    }

    public function batches(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    /**
     * Stok diuraikan ke satuan-satuan produk, mis. "3 SLOP, 7 BUNGKUS, 13 BATANG".
     * Memakai relasi units bila sudah dimuat.
     */
    public function stockBreakdown(?float $baseQty = null): string
    {
        return \App\Support\UnitConverter::breakdown(
            $baseQty ?? (float) $this->stock,
            $this->units,
            $this->base_unit
        );
    }

    /** Gambar SVG barcode produk ini (kosong kalau tidak ada). */
    public function barcodeSvg(float $height = 40): string
    {
        return $this->barcode ? Barcode::svg($this->barcode, $height) : '';
    }
}
