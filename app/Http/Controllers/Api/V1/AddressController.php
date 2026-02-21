<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddressRequest;
use App\Http\Resources\Api\V1\AddressResource;
use App\Services\AddressService;
use App\Traits\ApiResponseTrait;

class AddressController extends Controller
{
    use ApiResponseTrait;

    /**
     * Inject the AddressService
     */
    public function __construct(
        protected AddressService $service
    ) {}

    /**
     * Display a listing of addresses.
     */
    public function index()
    {
        return $this->success($this->service->index());
    }

    /**
     * Store a newly created address.
     */
    public function store(AddressRequest $request)
    {
        $this->service->create($request->all());
        return $this->success("Address created successfully!");
    }

    /**
     * Display the specified address.
     */
    public function show($id)
    {
        $address = $this->service->findById($id);
        return $this->success(new AddressResource($address));
    }

    /**
     * Update the specified address.
     */
    public function update(AddressRequest $request, $id)
    {
        $this->service->update($id, $request->all());
        return $this->success("Address updated successfully!");
    }

    /**
     * Remove the specified address.
     */
    public function destroy($id)
    {
        $this->service->delete($id);
        return $this->success("Address deleted successfully!");
    }
}
