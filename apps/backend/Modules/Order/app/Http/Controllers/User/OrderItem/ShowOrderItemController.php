<?php

namespace Modules\Order\Http\Controllers\User\OrderItem;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Template scaffold for the order-item resource — the nwidart stub,
 * kept for the future order-item UI; every action is a placeholder.
 */
class ShowOrderItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return view('order::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('order::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): void
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): View
    {
        return view('order::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id): View
    {
        return view('order::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): void
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): void
    {
        //
    }
}
