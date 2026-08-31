<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Admin\Attribute\CreateAttributeController;
use Modules\Catalog\Http\Controllers\Admin\Attribute\DeleteAttributeController;
use Modules\Catalog\Http\Controllers\Admin\Attribute\ListAttributeController;
use Modules\Catalog\Http\Controllers\Admin\Attribute\ShowAttributeController;
use Modules\Catalog\Http\Controllers\Admin\Attribute\UpdateAttributeController;
use Modules\Catalog\Http\Controllers\Admin\AttributeGroup\CreateAttributeGroupController;
use Modules\Catalog\Http\Controllers\Admin\AttributeGroup\DeleteAttributeGroupController;
use Modules\Catalog\Http\Controllers\Admin\AttributeGroup\ListAttributeGroupController;
use Modules\Catalog\Http\Controllers\Admin\AttributeGroup\ShowAttributeGroupController;
use Modules\Catalog\Http\Controllers\Admin\AttributeGroup\UpdateAttributeGroupController;
use Modules\Catalog\Http\Controllers\Admin\AttributeTemplate\CreateAttributeTemplateController;
use Modules\Catalog\Http\Controllers\Admin\AttributeTemplate\DeleteAttributeTemplateController;
use Modules\Catalog\Http\Controllers\Admin\AttributeTemplate\ListAttributeTemplateController;
use Modules\Catalog\Http\Controllers\Admin\AttributeTemplate\ShowAttributeTemplateController;
use Modules\Catalog\Http\Controllers\Admin\AttributeTemplate\UpdateAttributeTemplateController;
use Modules\Catalog\Http\Controllers\Admin\AttributeValue\CreateAttributeValueController;
use Modules\Catalog\Http\Controllers\Admin\AttributeValue\DeleteAttributeValueController;
use Modules\Catalog\Http\Controllers\Admin\AttributeValue\ListAttributeValueController;
use Modules\Catalog\Http\Controllers\Admin\AttributeValue\ShowAttributeValueController;
use Modules\Catalog\Http\Controllers\Admin\AttributeValue\UpdateAttributeValueController;
use Modules\Catalog\Http\Controllers\Admin\Category\CreateCategoryController;
use Modules\Catalog\Http\Controllers\Admin\Category\DeleteCategoryController;
use Modules\Catalog\Http\Controllers\Admin\Category\ListCategoryController;
use Modules\Catalog\Http\Controllers\Admin\Category\ShowCategoryController;
use Modules\Catalog\Http\Controllers\Admin\Category\UpdateCategoryController;
use Modules\Catalog\Http\Controllers\Admin\Option\CreateOptionController;
use Modules\Catalog\Http\Controllers\Admin\Option\DeleteOptionController;
use Modules\Catalog\Http\Controllers\Admin\Option\ListOptionController;
use Modules\Catalog\Http\Controllers\Admin\Option\ShowOptionController;
use Modules\Catalog\Http\Controllers\Admin\Option\UpdateOptionController;
use Modules\Catalog\Http\Controllers\Admin\OptionValue\CreateOptionValueController;
use Modules\Catalog\Http\Controllers\Admin\OptionValue\DeleteOptionValueController;
use Modules\Catalog\Http\Controllers\Admin\OptionValue\ListOptionValueController;
use Modules\Catalog\Http\Controllers\Admin\OptionValue\ShowOptionValueController;
use Modules\Catalog\Http\Controllers\Admin\OptionValue\UpdateOptionValueController;
use Modules\Catalog\Http\Controllers\Admin\Product\CreateProductController;
use Modules\Catalog\Http\Controllers\Admin\Product\DeleteProductController;
use Modules\Catalog\Http\Controllers\Admin\Product\ListProductController;
use Modules\Catalog\Http\Controllers\Admin\Product\ShowProductController;
use Modules\Catalog\Http\Controllers\Admin\Product\UpdateProductController;
use Modules\Catalog\Http\Controllers\Admin\ProductComment\CreateProductCommentController;
use Modules\Catalog\Http\Controllers\Admin\ProductComment\DeleteProductCommentController;
use Modules\Catalog\Http\Controllers\Admin\ProductComment\ListProductCommentController;
use Modules\Catalog\Http\Controllers\Admin\ProductComment\ShowProductCommentController;
use Modules\Catalog\Http\Controllers\Admin\ProductComment\UpdateProductCommentController;
use Modules\Core\Http\Middleware\AdminMiddleware;

Route::middleware(['auth:sanctum', AdminMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/products', ListProductController::class)->name('products.list');
    Route::post('/products', CreateProductController::class)->name('products.create');
    Route::get('/products/{id}', ShowProductController::class)->name('products.show');
    Route::patch('/products/{id}', UpdateProductController::class)->name('products.update');
    Route::delete('/products/{id}', DeleteProductController::class)->name('products.delete');

    Route::get('/product-comments', ListProductCommentController::class)->name('product-comments.list');
    Route::post('/product-comments', CreateProductCommentController::class)->name('product-comments.create');
    Route::get('/product-comments/{id}', ShowProductCommentController::class)->name('product-comments.show');
    Route::patch('/product-comments/{id}', UpdateProductCommentController::class)->name('product-comments.update');
    Route::delete('/product-comments/{id}', DeleteProductCommentController::class)->name('product-comments.delete');

    Route::get('/categories', ListCategoryController::class)->name('categories.list');
    Route::post('/categories', CreateCategoryController::class)->name('categories.create');
    Route::get('/categories/{id}', ShowCategoryController::class)->name('categories.show');
    Route::patch('/categories/{id}', UpdateCategoryController::class)->name('categories.update');
    Route::delete('/categories/{id}', DeleteCategoryController::class)->name('categories.delete');

    Route::get('/options', ListOptionController::class)->name('options.list');
    Route::post('/options', CreateOptionController::class)->name('options.create');
    Route::get('/options/{id}', ShowOptionController::class)->name('options.show');
    Route::patch('/options/{id}', UpdateOptionController::class)->name('options.update');
    Route::delete('/options/{id}', DeleteOptionController::class)->name('options.delete');

    Route::get('/options/{id}/values', ListOptionValueController::class)->name('option-values.list');
    Route::post('/options/{id}/values', CreateOptionValueController::class)->name('option-values.create');
    Route::get('/options/{id}/values/{valueId}', ShowOptionValueController::class)->name('option-values.show');
    Route::patch('/options/{id}/values/{valueId}', UpdateOptionValueController::class)->name('option-values.update');
    Route::delete('/options/{id}/values/{valueId}', DeleteOptionValueController::class)->name('option-values.delete');

    Route::get('/attribute-groups', ListAttributeGroupController::class)->name('attribute-groups.list');
    Route::post('/attribute-groups', CreateAttributeGroupController::class)->name('attribute-groups.create');
    Route::get('/attribute-groups/{id}', ShowAttributeGroupController::class)->name('attribute-groups.show');
    Route::patch('/attribute-groups/{id}', UpdateAttributeGroupController::class)->name('attribute-groups.update');
    Route::delete('/attribute-groups/{id}', DeleteAttributeGroupController::class)->name('attribute-groups.delete');

    Route::get('/attributes', ListAttributeController::class)->name('attributes.list');
    Route::post('/attributes', CreateAttributeController::class)->name('attributes.create');
    Route::get('/attributes/{id}', ShowAttributeController::class)->name('attributes.show');
    Route::patch('/attributes/{id}', UpdateAttributeController::class)->name('attributes.update');
    Route::delete('/attributes/{id}', DeleteAttributeController::class)->name('attributes.delete');

    Route::get('/attributes/{id}/values', ListAttributeValueController::class)->name('attribute-values.list');
    Route::post('/attributes/{id}/values', CreateAttributeValueController::class)->name('attribute-values.create');
    Route::get('/attributes/{id}/values/{valueId}', ShowAttributeValueController::class)->name('attribute-values.show');
    Route::patch('/attributes/{id}/values/{valueId}', UpdateAttributeValueController::class)->name('attribute-values.update');
    Route::delete('/attributes/{id}/values/{valueId}', DeleteAttributeValueController::class)->name('attribute-values.delete');

    Route::get('/attribute-templates', ListAttributeTemplateController::class)->name('attribute-templates.list');
    Route::post('/attribute-templates', CreateAttributeTemplateController::class)->name('attribute-templates.create');
    Route::get('/attribute-templates/{id}', ShowAttributeTemplateController::class)->name('attribute-templates.show');
    Route::patch('/attribute-templates/{id}', UpdateAttributeTemplateController::class)->name('attribute-templates.update');
    Route::delete('/attribute-templates/{id}', DeleteAttributeTemplateController::class)->name('attribute-templates.delete');
});
