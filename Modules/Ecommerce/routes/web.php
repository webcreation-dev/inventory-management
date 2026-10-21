<?php

use Illuminate\Support\Facades\Route;
use Modules\Ecommerce\Http\Controllers\Admin\SiteSettingsController;
use Modules\Ecommerce\Http\Controllers\Admin\BannerController;
use Modules\Ecommerce\Http\Controllers\Admin\BrandingController;
use Modules\Ecommerce\Http\Controllers\Admin\ContactController;
use Modules\Ecommerce\Http\Controllers\Admin\DeliveryAreaController;
use Modules\Ecommerce\Http\Controllers\Admin\DeliveryPartnerController;
use Modules\Ecommerce\Http\Controllers\Admin\FeaturedProductController;
use Modules\Ecommerce\Http\Controllers\Admin\FlashSaleController;
use Modules\Ecommerce\Http\Controllers\Admin\HeaderCategoryController;
use Modules\Ecommerce\Http\Controllers\Admin\HomeCategoryController;
use Modules\Ecommerce\Http\Controllers\Admin\NewTrendController;
use Modules\Ecommerce\Http\Controllers\Admin\PopularBrandController;
use Modules\Ecommerce\Http\Controllers\Admin\PopularProductController;
use Modules\Ecommerce\Http\Controllers\EcommerceLanguageController;
use Modules\Ecommerce\Http\Controllers\EcommerceController;
use Modules\Ecommerce\Support\Storefront;

$ecMode = Storefront::envEnabled();
$storefrontPrefix = Storefront::pathPrefix();

/*
|--------------------------------------------------------------------------
| Admin CMS — always under /ecommerce/admin
|--------------------------------------------------------------------------
*/
Route::prefix('ecommerce')->name('ecommerce.')->group(function () {
    Route::middleware(['auth', 'verified', 'permission:ecommerce-cms'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('branding', [BrandingController::class, 'edit'])->name('branding.edit');
        Route::put('branding', [BrandingController::class, 'update'])->name('branding.update');
        Route::get('site-settings', [SiteSettingsController::class, 'edit'])->name('site-settings.edit');
        Route::put('site-settings', [SiteSettingsController::class, 'update'])->name('site-settings.update');

        Route::resource('banners', BannerController::class)->except(['show']);

        Route::post('categories/sort-order', [HomeCategoryController::class, 'updateSortOrder'])->name('categories.sort-order');
        Route::resource('categories', HomeCategoryController::class)->except(['show']);

        Route::get('header-categories', [HeaderCategoryController::class, 'edit'])->name('header-categories.edit');
        Route::put('header-categories', [HeaderCategoryController::class, 'update'])->name('header-categories.update');

        Route::post('brands/sort-order', [PopularBrandController::class, 'updateSortOrder'])->name('brands.sort-order');
        Route::resource('brands', PopularBrandController::class)->except(['show']);

        Route::post('featured/sort-order', [FeaturedProductController::class, 'updateSortOrder'])->name('featured.sort-order');
        Route::resource('featured', FeaturedProductController::class)->except(['show']);

        Route::post('new-trends/sort-order', [NewTrendController::class, 'updateSortOrder'])->name('new-trends.sort-order');
        Route::resource('new-trends', NewTrendController::class)->except(['show']);

        Route::post('popular-products/sort-order', [PopularProductController::class, 'updateSortOrder'])->name('popular-products.sort-order');
        Route::resource('popular-products', PopularProductController::class)->except(['show']);

        Route::get('flash-sale-items/search', [FlashSaleController::class, 'searchItems'])->name('flash-sales.search-items');
        Route::resource('flash-sales', FlashSaleController::class)->except(['show']);

        Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
        Route::post('contacts/{contact}/reply', [ContactController::class, 'reply'])->name('contacts.reply');
        Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

        Route::resource('delivery-partners', DeliveryPartnerController::class)->except(['show']);
        Route::resource('delivery-areas', DeliveryAreaController::class)->except(['show']);
    });
});

/*
|--------------------------------------------------------------------------
| Storefront
| - EC=false → /ecommerce/...
| - EC=true  → /web/... (home registered at / in routes/web.php)
|--------------------------------------------------------------------------
*/
Route::prefix($storefrontPrefix)
    ->name('ecommerce.')
    ->middleware(['ecommerce.storefront'])
    ->group(function () use ($ecMode) {
        Route::controller(EcommerceController::class)->group(function () use ($ecMode) {
            Route::get('/image/{file}', 'image')->name('image');

            if (! $ecMode) {
                Route::get('/', 'home')->name('home');
            } else {
                // /web → site root home
                Route::get('/', function () {
                    return redirect()->route('ecommerce.home');
                });
            }

            Route::get('/collections/{type}', 'collection')
                ->where('type', 'new-trend|popular')
                ->name('collection');
            Route::get('/collections/{type}/products', 'collectionProducts')
                ->where('type', 'new-trend|popular')
                ->name('collection.products');
            Route::get('/category/{slug}/{sub}/products', 'categoryProducts')
                ->where('sub', '^(?!products$).+')
                ->name('category.sub.products');
            Route::get('/category/{slug}/products', 'categoryProducts')->name('category.products');
            Route::get('/category/{slug}/{sub}', 'category')
                ->where('sub', '^(?!products$).+')
                ->name('category.sub');
            Route::get('/category/{slug}', 'category')->name('category');
            Route::get('/search/suggest', 'searchSuggest')->name('search.suggest');
            Route::get('/compare', 'compare')->name('compare');
            Route::get('/contact', 'contact')->name('contact');
            Route::post('/contact', 'submitContact')->name('contact.submit');
            Route::get('/privacy', 'privacy')->name('privacy');
            Route::get('/terms', 'terms')->name('terms');
            Route::get('/offers', 'offers')->name('offers');
            Route::get('/offers/{offer}', 'offerShow')->name('offers.show');
            Route::get('/offers/{offer}/products', 'offerProducts')->name('offers.products');
            Route::get('/item-details/{id?}', 'itemDetails')->name('item-details');
            Route::post('/item-details/{id}/rating', 'storeProductRating')->name('item-details.rating');
            Route::get('/cart', 'cart')->name('cart');
            Route::post('/cart/add', 'addToCart')->name('cart.add');
            Route::post('/cart/update', 'updateCart')->name('cart.update');
            Route::post('/cart/remove', 'removeFromCart')->name('cart.remove');
            Route::post('/cart/clear', 'clearCart')->name('cart.clear');
            Route::get('/checkout', 'checkout')->name('checkout');
            Route::post('/checkout', 'placeOrder')->name('checkout.place');
            Route::get('/payment/paypal/return/{sale}', 'paypalReturn')->name('payment.paypal.return');
            Route::get('/payment/paypal/cancel/{sale}', 'paypalCancel')->name('payment.paypal.cancel');
            Route::get('/payment/stripe/return/{sale}', 'stripeReturn')->name('payment.stripe.return');
            Route::get('/payment/stripe/cancel/{sale}', 'stripeCancel')->name('payment.stripe.cancel');
            Route::get('/order-success', 'orderSuccess')->name('order-success');
            Route::get('/wishlist', 'wishlist')->name('wishlist');
            Route::post('/wishlist/toggle', 'toggleWishlist')->name('wishlist.toggle');
            Route::post('/wishlist/remove', 'removeFromWishlist')->name('wishlist.remove');
            Route::get('/customer-panel', 'customerPanel')->name('customer-panel');
            Route::post('/customer-panel/profile', 'updateCustomerProfile')->name('customer-panel.profile');
            Route::post('/customer-panel/orders/{sale}/cancel', 'cancelCustomerOrder')->name('customer-panel.orders.cancel');
            Route::get('/login', 'login')->name('login');
            Route::post('/login', 'customerLogin')->name('login.submit');
            Route::post('/logout', 'customerLogout')->name('logout');
            Route::post('/logout-admin-for-customer', 'logoutAdminForCustomer')->name('logout-admin-for-customer');
            Route::get('/forgot-password', 'forgotPassword')->name('forgot-password');
            Route::post('/forgot-password', 'forgotPasswordSubmit')->name('forgot-password.submit');
            Route::get('/reset-password/{customer}', 'resetPassword')
                ->middleware('signed')
                ->name('reset-password');
            Route::post('/reset-password/{customer}', 'resetPasswordSubmit')
                ->middleware('signed')
                ->name('reset-password.submit');
            Route::get('/register', 'register')->name('register');
            Route::post('/register', 'customerRegister')->name('register.submit');
            Route::get('/verify-email/{customer}', 'verifyEmail')
                ->middleware('signed')
                ->name('verify-email');
            Route::post('/resend-verification', 'resendVerification')->name('resend-verification');
        });

        Route::get('/lang/{locale}', [EcommerceLanguageController::class, 'switch'])
            ->where('locale', '[a-z]{2}')
            ->name('language.switch');
    });
