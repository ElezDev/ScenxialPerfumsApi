<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Promo;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'manage products',
            'manage categories',
            'manage brands',
            'manage banners',
            'manage promos',
            'manage orders',
            'manage users',
            'view dashboard',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $customerRole = Role::firstOrCreate(['name' => 'customer']);
        $adminRole->syncPermissions($permissions);

        $admins = [
            [
                'name' => 'Adrian',
                'email' => 'adrian@permuferia.com',
                'phone' => '+5491100000001',
            ],
            [
                'name' => 'Edwin',
                'email' => 'edwin@permuferia.com',
                'phone' => '+5491100000002',
            ],
        ];

        foreach ($admins as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'password' => Hash::make('password123'),
                    'is_active' => true,
                ]
            );
            if (! $user->hasRole('admin')) {
                $user->assignRole('admin');
            }
        }

        if (User::role('customer')->count() === 0) {
            User::factory(15)->create()->each(fn (User $user) => $user->assignRole('customer'));
        }

        $mainCategories = [
            ['name' => 'Perfumes', 'slug' => 'perfumes', 'description' => 'Fragancias premium para él y ella'],
            ['name' => 'Vapers', 'slug' => 'vapers', 'description' => 'Dispositivos y líquidos de vapeo'],
            ['name' => 'Ropa', 'slug' => 'ropa', 'description' => 'Moda urbana y streetwear'],
        ];

        foreach ($mainCategories as $index => $data) {
            Category::firstOrCreate(
                ['slug' => $data['slug']],
                [
                    ...$data,
                    'is_active' => true,
                    'sort_order' => $index,
                ]
            );
        }

        if (Category::count() <= count($mainCategories)) {
            Category::factory(5)->create();
        }

        $mainBrands = ['Permuferia', 'Urban Vape', 'Noir Collection', 'Gold Essence', 'Street Noir'];
        foreach ($mainBrands as $name) {
            Brand::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'is_active' => true,
                ]
            );
        }

        if (Brand::count() <= count($mainBrands)) {
            Brand::factory(8)->create();
        }

        Banner::firstOrCreate(
            ['title' => 'Descubrí tu esencia'],
            [
                'subtitle' => 'Perfumes exclusivos, vapers premium y ropa con estilo.',
                'link_url' => '/catalogo',
                'link_text' => 'Explorar catálogo',
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        Banner::firstOrCreate(
            ['title' => 'Nueva colección disponible'],
            [
                'subtitle' => 'Fragancias premium para él y ella.',
                'link_url' => '/catalogo?categoria=perfumes',
                'link_text' => 'Ver perfumes',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        Promo::firstOrCreate(
            ['code' => 'BIENVENIDO15'],
            [
                'title' => '15% OFF en tu primera compra',
                'description' => 'Usá el código BIENVENIDO15 al finalizar tu pedido.',
                'discount_type' => 'percentage',
                'discount_value' => 15,
                'is_active' => true,
                'min_purchase' => 10000,
                'usage_limit' => 100,
            ]
        );

        Promo::firstOrCreate(
            ['title' => 'Envío gratis'],
            [
                'description' => 'En compras mayores a $50.000',
                'discount_type' => 'fixed',
                'discount_value' => 5000,
                'is_active' => true,
                'min_purchase' => 50000,
            ]
        );

        if (Product::count() === 0) {
            $categories = Category::all();
            $brands = Brand::all();

            Product::factory(60)->create([
                'category_id' => fn () => $categories->random()->id,
                'brand_id' => fn () => $brands->random()->id,
            ]);
        }

        Product::doesntHave('images')->each(function (Product $product) {
            $imageCount = rand(1, 2);

            for ($i = 0; $i < $imageCount; $i++) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => "https://picsum.photos/seed/product-{$product->id}-{$i}/800/800",
                    'is_primary' => $i === 0,
                    'sort_order' => $i,
                ]);
            }
        });

        if (Order::count() === 0) {
            $customers = User::role('customer')->get();
            $products = Product::all();

            Order::factory(25)->create([
                'user_id' => fn () => $customers->random()->id,
            ])->each(function (Order $order) use ($products) {
                $itemsCount = rand(1, 4);
                $selectedProducts = $products->random(min($itemsCount, $products->count()));
                $subtotal = 0;

                foreach ($selectedProducts as $product) {
                    $quantity = rand(1, 3);
                    $lineTotal = $product->price * $quantity;
                    $subtotal += $lineTotal;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'quantity' => $quantity,
                        'unit_price' => $product->price,
                        'total_price' => $lineTotal,
                    ]);
                }

                $order->update([
                    'subtotal' => $subtotal,
                    'total' => $subtotal + $order->shipping_cost,
                ]);
            });
        }
    }
}
