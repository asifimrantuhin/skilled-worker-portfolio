<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        $query = Package::where('is_active', true);

        // Filters
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('destination')) {
            $query->where('destination', 'like', '%' . $request->destination . '%');
        }

        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->has('featured')) {
            $query->where('is_featured', true);
        }

        // Search
        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%')
                  ->orWhere('destination', 'like', '%' . $request->search . '%');
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $packages = $query->paginate($request->get('per_page', 12));

        return response()->json([
            'success' => true,
            'packages' => $packages,
        ]);
    }

    public function show($id)
    {
        $package = Package::with(['creator', 'availability'])->findOrFail($id);
        
        // Increment views
        $package->increment('views');

        return response()->json([
            'success' => true,
            'package' => $package,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'short_description' => 'nullable|string',
            'duration_days' => 'required|integer|min:1',
            'duration_nights' => 'nullable|integer',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'destination' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'images' => 'nullable|array',
            'images.*' => 'image|max:2048',
            'itinerary' => 'nullable|array',
            'inclusions' => 'nullable|array',
            'exclusions' => 'nullable|array',
            'max_participants' => 'nullable|integer|min:1',
            'min_participants' => 'nullable|integer|min:1',
            'is_featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        $data['slug'] = Str::slug($request->title) . '-' . uniqid();
        $data['created_by'] = $request->user()->id;

        // Handle image uploads
        if ($request->hasFile('images')) {
            $images = [];
            foreach ($request->file('images') as $image) {
                $path = $image->store('packages', 'public');
                $images[] = $path;
            }
            $data['images'] = $images;
        }

        $package = Package::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Package created successfully',
            'package' => $package,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $package = Package::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'short_description' => 'nullable|string',
            'duration_days' => 'sometimes|required|integer|min:1',
            'duration_nights' => 'nullable|integer',
            'price' => 'sometimes|required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'destination' => 'sometimes|required|string|max:255',
            'category' => 'nullable|string|max:255',
            'images' => 'nullable|array',
            'images.*' => 'image|max:2048',
            'itinerary' => 'nullable|array',
            'inclusions' => 'nullable|array',
            'exclusions' => 'nullable|array',
            'max_participants' => 'nullable|integer|min:1',
            'min_participants' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();

        // Handle image uploads
        if ($request->hasFile('images')) {
            $images = [];
            foreach ($request->file('images') as $image) {
                $path = $image->store('packages', 'public');
                $images[] = $path;
            }
            $data['images'] = array_merge($package->images ?? [], $images);
        }

        $package->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Package updated successfully',
            'package' => $package,
        ]);
    }

    public function destroy($id)
    {
        $package = Package::findOrFail($id);
        $package->delete();

        return response()->json([
            'success' => true,
            'message' => 'Package deleted successfully',
        ]);
    }

    public function checkAvailability(Request $request, $id)
    {
        $package = Package::findOrFail($id);
        $date = $request->get('date');
        $participants = $request->get('participants', 1);

        if (!$date) {
            return response()->json([
                'success' => false,
                'message' => 'Date is required',
            ], 422);
        }

        $availability = PackageAvailability::where('package_id', $id)
            ->where('date', $date)
            ->first();

        if (!$availability || !$availability->is_available) {
            return response()->json([
                'success' => true,
                'available' => false,
                'message' => 'Package not available for this date',
            ]);
        }

        $remainingSlots = $availability->available_slots - $availability->booked_slots;
        $isAvailable = $remainingSlots >= $participants;

        return response()->json([
            'success' => true,
            'available' => $isAvailable,
            'remaining_slots' => $remainingSlots,
            'price' => $availability->price_override ?? $package->price,
        ]);
    }

    public function setAvailability(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date|after:today',
            'available_slots' => 'required|integer|min:1',
            'price_override' => 'nullable|numeric|min:0',
            'is_available' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $availability = PackageAvailability::updateOrCreate(
            [
                'package_id' => $id,
                'date' => $request->date,
            ],
            [
                'available_slots' => $request->available_slots,
                'price_override' => $request->price_override,
                'is_available' => $request->is_available ?? true,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Availability set successfully',
            'availability' => $availability,
        ]);
    }
}

