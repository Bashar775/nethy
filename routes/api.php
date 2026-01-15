<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::options('/{any}', function () {
    return response()->json([], 200);
})->where('any', '.*');
//User AUTH ROUTES
Route::post('/register',[AuthController::class,'register']); //tested
Route::post('/login',[AuthController::class,'login']);
Route::post('employeelogin',[AuthController::class,'employeeLogin']);    //tested
Route::post('/logout',[AuthController::class,'logout'])->middleware('auth:sanctum');
Route::get('/email/verify/{id}/{hash}',[AuthController::class,'verifyEmail'])->middleware(['signed'])->name('verification.verify'); //tested
Route::post('/email/resend',[AuthController::class, 'resendEmailVerification'])->middleware(['auth:sanctum'])->name('verification.resend');
Route::post('/createemployees',[AuthController::class,'create']); //tested
Route::get('/customers',[AuthController::class,'indexCustomers'])->middleware(['auth:sanctum', 'employee']); //tested
Route::get('/employees',[AuthController::class,'indexEmployees'])->middleware(['auth:sanctum', 'employee']); //tested
//SUPPLIER ROUTES
Route::get('/suppliers',[App\Http\Controllers\Api\SupplierController::class,'index'])->middleware(['auth:sanctum','employee']); //tested
Route::get('/suppliers/{id}',[App\Http\Controllers\Api\SupplierController::class,'show'])->middleware(['auth:sanctum','employee']); //tested
Route::post('/createsupplier',[App\Http\Controllers\Api\SupplierController::class,'store'])->middleware(['auth:sanctum','employee']); //tested
Route::post('/updatesupplier/{id}',[App\Http\Controllers\Api\SupplierController::class,'update'])->middleware(['auth:sanctum','employee']); //tested
Route::delete('/deletesupplier/{id}',[App\Http\Controllers\Api\SupplierController::class,'destroy'])->middleware(['auth:sanctum','employee']); //tested
//PRODUCT ROUTES
Route::get('/products',[App\Http\Controllers\Api\ProductController::class,'index']); //tested
Route::get('/products/{id}',[App\Http\Controllers\Api\ProductController::class,'show']); //tested
Route::post('/createproduct',[App\Http\Controllers\Api\ProductController::class,'store'])->middleware(['auth:sanctum','employee']); //tested
Route::post('/updateproduct/{id}',[App\Http\Controllers\Api\ProductController::class, 'update'])->middleware(['auth:sanctum','employee']); //tested
Route::delete('/deleteproduct/{id}',[App\Http\Controllers\Api\ProductController::class,'destroy'])->middleware(['auth:sanctum','employee']); //tested
//CATEGORY ROUTES
Route::get('/categories',[App\Http\Controllers\Api\CategoryController::class,'index']); //tested
Route::post('/createcategory',[App\Http\Controllers\Api\CategoryController::class,'store'])->middleware(['auth:sanctum','employee']); //tested
Route::get('/categories/{id}',[App\Http\Controllers\Api\CategoryController::class,'show']); //tested
Route::post('/updatecategorystate/{id}',[App\Http\Controllers\Api\CategoryController::class,'updateState'])->middleware(['auth:sanctum','employee']); //tested
Route::post('/updatecategory/{id}',[App\Http\Controllers\Api\CategoryController::class,'update'])->middleware(['auth:sanctum','employee']); //tested
Route::delete('/deletecategory/{id}',[App\Http\Controllers\Api\CategoryController::class,'destroy'])->middleware(['auth:sanctum','employee']); //tested
//CUSTOMER OREDERS ROUTES
Route::get('/customerorders',[OrderController::class,'index'])->middleware(['auth:sanctum', 'employee']);
Route::get('/customerorders/{id}',[OrderController::class,'show'])->middleware(['auth:sanctum', 'employee']);
Route::post('/createorder',[OrderController::class,'store'])->middleware(['auth:sanctum']);
Route::post('/confirmcustomerorder/{id}',[OrderController::class,'confirm'])->middleware(['auth:sanctum', 'employee']);
Route::post('/cancelcustomerorder/{id}',[OrderController::class,'cancel'])->middleware(['auth:sanctum', 'employee']);
Route::post('/deletecustomerorder/{id}',[OrderController::class,'destroy'])->middleware(['auth:sanctum', 'employee']);
Route::post('/additemtoorder/{id}',[OrderController::class,'addItem'])->middleware(['auth:sanctum', 'employee']);
Route::post('/removeitemfromorder/{id}',[OrderController::class,'removeItem'])->middleware(['auth:sanctum', 'employee']);
Route::post('/updateorderheaders/{id}',[OrderController::class,'update'])->middleware(['auth:sanctum', 'employee']);
//SUPPLIER ORDERS ROUTES
Route::get('/supplierorders',[App\Http\Controllers\Api\SupplierOrderController::class,'index'])->middleware(['auth:sanctum','employee']);
Route::get('/supplierorders/{id}',[App\Http\Controllers\Api\SupplierOrderController::class,'show'])->middleware(['auth:sanctum','employee']);
Route::post('/createsupplierorder',[App\Http\Controllers\Api\SupplierOrderController::class,'store'])->middleware(['auth:sanctum','employee']);
Route::post('/confirmsupplierorder/{id}',[App\Http\Controllers\Api\SupplierOrderController::class,'confirm'])->middleware(['auth:sanctum','employee']);
Route::post('/cancelsupplierorder/{id}',[App\Http\Controllers\Api\SupplierOrderController::class,'cancel'])->middleware(['auth:sanctum','employee']);
// Route::post('/deletesupplierorder/{id}',[App\Http\Controllers\Api\SupplierOrderController::class,'destroy'])->middleware(['auth:sanctum','employee']);
Route::post('/additemtosupplierorder/{id}',[App\Http\Controllers\Api\SupplierOrderController::class,'addItem'])->middleware(['auth:sanctum','employee']);
Route::post('/removeitemfromsupplierorder/{id}',[App\Http\Controllers\Api\SupplierOrderController::class,'removeItem'])->middleware(['auth:sanctum','employee']);
Route::post('/updatesupplierorderheaders/{id}',[App\Http\Controllers\Api\SupplierOrderController::class,'update'])->middleware(['auth:sanctum','employee']);
//STOCK MOVMENTS ROUTES
Route::get('/stockmovments',[App\Http\Controllers\Api\StockMovmentController::class,'index'])->middleware(['auth:sanctum','employee']);
//INVOICE ROUTES
Route::get('/invoices',[App\Http\Controllers\Api\InvoiceController::class,'index'])->middleware(['auth:sanctum','employee']);
Route::post('/generateinvoice',[App\Http\Controllers\Api\InvoiceController::class,'generate'])->middleware(['auth:sanctum','employee']);
Route::get('/invoices/{id}',[App\Http\Controllers\Api\InvoiceController::class,'show'])->middleware(['auth:sanctum','employee']);
Route::post('/deleteinvoice/{id}',[App\Http\Controllers\Api\InvoiceController::class,'destroy'])->middleware(['auth:sanctum','employee']);
Route::post('/changestatusinvoice/{id}',[App\Http\Controllers\Api\InvoiceController::class,'changeStatus'])->middleware(['auth:sanctum','employee']);
Route::post('/updateinvoice/{id}',[App\Http\Controllers\Api\InvoiceController::class,'update'])->middleware(['auth:sanctum','employee']);
Route::post('/enforcecreateinvoice',[App\Http\Controllers\Api\InvoiceController::class,'store'])->middleware(['auth:sanctum','employee']);
//SUPPLIER INVVOICE ROUTES
Route::get('/supplierinvoices',[App\Http\Controllers\Api\SupplierInvoiceController::class,'index'])->middleware(['auth:sanctum','employee']);
Route::post('/generatesupplierinvoice',[App\Http\Controllers\Api\SupplierInvoiceController::class,'generate'])->middleware(['auth:sanctum','employee']);
Route::get('/supplierinvoices/{id}',[App\Http\Controllers\Api\SupplierInvoiceController::class,'show'])->middleware(['auth:sanctum','employee']);
Route::post('/deletesupplierinvoice/{id}',[App\Http\Controllers\Api\SupplierInvoiceController::class,'destroy'])->middleware(['auth:sanctum','employee']);
Route::post('/changesupplierinvoicestatus/{id}',[App\Http\Controllers\Api\SupplierInvoiceController::class,'changeStatus'])->middleware(['auth:sanctum','employee']);
Route::post('/updatesupplierinvoice/{id}',[App\Http\Controllers\Api\SupplierInvoiceController::class,'update'])->middleware(['auth:sanctum','employee']);
Route::post('/enforcecreatesupplierinvoice',[App\Http\Controllers\Api\SupplierInvoiceController::class,'store'])->middleware(['auth:sanctum','employee']);
//PAYMENT ROUTES
Route::get('/payments',[App\Http\Controllers\Api\PaymentController::class,'index'])->middleware(['auth:sanctum','employee']);
Route::get('/payments/{id}',[App\Http\Controllers\Api\PaymentController::class,'show'])->middleware(['auth:sanctum','employee']);
Route::post('/createpayment',[App\Http\Controllers\Api\PaymentController::class,'store'])->middleware(['auth:sanctum','employee']);
Route::post('/updatepayment/{id}',[App\Http\Controllers\Api\PaymentController::class,'update'])->middleware(['auth:sanctum','employee']);
Route::delete('/deletepayment/{id}',[App\Http\Controllers\Api\PaymentController::class,'destroy'])->middleware(['auth:sanctum','employee']);
//IMAGE ROUTES
Route::post('/addimages/{id}',[App\Http\Controllers\Api\ImageController::class,'addImages'])->middleware(['auth:sanctum','employee']); //tested
Route::delete('/deleteimage/{id}',[App\Http\Controllers\Api\ImageController::class,'destroy'])->middleware(['auth:sanctum','employee']); //tested

//just for testing
Route::post('/seed',[AuthController::class, 'seed']);