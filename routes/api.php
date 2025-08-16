<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post("/register",'App\Http\Controllers\AuthenticationController@register');
Route::post("/login",'App\Http\Controllers\AuthenticationController@login');
Route::post("/forgot",'App\Http\Controllers\AuthenticationController@forgot');
Route::post("/updateprofile",'App\Http\Controllers\AuthenticationController@updateprofile');


// Admin API's
Route::post("/insertBusiness",'App\Http\Controllers\Admin@insertBusiness');
Route::post("/insertLocation",'App\Http\Controllers\Admin@insertLocation');

// SaaS Plans & Billing
Route::get('/public/plans', 'App\\Http\\Controllers\\PlanController@publicIndex');
Route::get('/public/plans/{code}', 'App\\Http\\Controllers\\PlanController@show');
Route::post('/admin/plans', 'App\\Http\\Controllers\\PlanController@store');
Route::post('/admin/subscribe', 'App\\Http\\Controllers\\BillingController@subscribe');
Route::post('/admin/subscriptions/{id}/cancel', 'App\\Http\\Controllers\\BillingController@cancel');
Route::post('/admin/subscriptions/{id}/pause', 'App\\Http\\Controllers\\BillingController@pause');
Route::post('/admin/subscriptions/{id}/resume', 'App\\Http\\Controllers\\BillingController@resume');
Route::get('/admin/subscriptions', 'App\\Http\\Controllers\\BillingController@list');
Route::get('/admin/subscription-payments', 'App\\Http\\Controllers\\BillingController@payments');
Route::post('/webhooks/razorpay', 'App\\Http\\Controllers\\BillingController@webhook');


// User App API's

Route::post("/createInvoice",'App\Http\Controllers\Admin@createInvoice');
Route::get("/invoice/delete/{id}",'App\Http\Controllers\Admin@removeInvoice');
Route::post("/editInvoice",'App\Http\Controllers\Admin@editInvoice');
Route::get("/invoice/cancel/{id}",'App\Http\Controllers\Admin@cancelInvoice');

Route::post("/addProduct",'App\Http\Controllers\Admin@addProduct');
Route::post("/editProduct",'App\Http\Controllers\Admin@editProduct');
Route::get("/item/delete/{item_id}",'App\Http\Controllers\Admin@removeItem');


Route::post("/addAddress",'App\Http\Controllers\Admin@addAddress');

Route::post("/addExpense",'App\Http\Controllers\Admin@addExpense');
Route::get("/getAllExpenses",'App\Http\Controllers\Admin@getAllExpenses');
Route::get("/getExpenseById",'App\Http\Controllers\Admin@getExpenseById');
Route::get("/deleteExpense",'App\Http\Controllers\Admin@deleteExpense');

Route::get("/getItemsByInvoiceId",'App\Http\Controllers\Admin@getItemsByInvoiceId');
Route::get("/getAddressByInvoiceId",'App\Http\Controllers\Admin@getAddressByInvoiceId');
Route::get("/getAllInvoices",'App\Http\Controllers\Admin@getAllInvoices');
Route::get("/getDetailedInvoice/{invoiceId}",'App\Http\Controllers\Admin@getDetailedInvoice');
Route::get("/getExistedUser",'App\Http\Controllers\Admin@getExistedUser');
Route::get("/dashboardReport",'App\Http\Controllers\Admin@dashboardReport');
Route::get("/getDetailedInvoiceWeb/{invoiceId}",'App\Http\Controllers\Admin@getDetailedInvoiceWeb');
Route::get("/getBulkInvoicesWeb",'App\Http\Controllers\Admin@getBulkInvoicesWeb');





// AFter Report Module
Route::get("/getSaleReport",'App\Http\Controllers\Admin@getSaleReport');
Route::get("/getExpenseReport",'App\Http\Controllers\Admin@getExpenseReport');
Route::get("/getPurchaseSaleInvoice",'App\Http\Controllers\Admin@getPurchaseSaleInvoice');
Route::get("/getInvoiceListReport",'App\Http\Controllers\Admin@getInvoiceListReport');


Route::get("/getBulkInvoices",'App\Http\Controllers\Admin@getBulkInvoices');
Route::post("/getBulkInvoicesSelected",'App\Http\Controllers\Admin@getBulkInvoicesSelected');
Route::get("/getItemizedSalesReport",'App\Http\Controllers\Admin@getItemizedSalesReport');
Route::get("/getlocations",'App\Http\Controllers\Admin@getlocations');
Route::get("/getProducts",'App\Http\Controllers\Admin@getProducts');

// Categories
Route::post('/categories', 'App\\Http\\Controllers\\Admin@createCategory');
Route::get('/categories', 'App\\Http\\Controllers\\Admin@listCategories');
Route::post('/categories/update', 'App\\Http\\Controllers\\Admin@updateCategory');
Route::delete('/categories/{id}', 'App\\Http\\Controllers\\Admin@deleteCategory');

// Products with images
Route::post('/products', 'App\\Http\\Controllers\\Admin@createProductWithImages');
Route::post('/products/update', 'App\\Http\\Controllers\\Admin@updateProductWithImages');
Route::get('/products/{id}', 'App\\Http\\Controllers\\Admin@getProductWithImages');
Route::delete('/products/{id}', 'App\\Http\\Controllers\\Admin@deleteProduct');

// Product Dashboard and User Inquiry Routes
Route::post('/products/filter', [App\Http\Controllers\Admin::class, 'getFilteredProducts']);
Route::post('/inquiries/submit', [App\Http\Controllers\Admin::class, 'submitUserInquiry']);
Route::get('/inquiries', [App\Http\Controllers\Admin::class, 'getUserInquiries']);
Route::put('/inquiries/status', [App\Http\Controllers\Admin::class, 'updateInquiryStatus']);

// Debug route
Route::get('/products/debug', [App\Http\Controllers\Admin::class, 'getAllProductsDebug']);

// Business details
Route::get('/business/{id}', [App\Http\Controllers\Admin::class, 'getBusinessDetails']);

// Get all products with images
Route::get('/products/all', [App\Http\Controllers\Admin::class, 'getAllProductsWithImages']);
