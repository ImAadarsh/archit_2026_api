<?php

namespace App\Http\Controllers;

use App\Models\ShopProductReview;
use App\Models\ShopProductReviewImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Photo-only "Living With Art" gallery.
 * Each uploaded image becomes its own review row (one photo per review).
 */
class ReviewController extends Controller
{
    /**
     * POST /api/reviews
     * Multipart: images[]* (min 1). Optional: is_published, business_id, product_id.
     * Creates one review per image — no text required.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'nullable|integer|exists:products,id',
            'business_id' => 'nullable|integer',
            'is_published' => 'nullable',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
        }

        $files = $this->collectImageFiles($request);
        if (count($files) < 1) {
            return response()->json([
                'status' => false,
                'message' => 'At least one image is required.',
            ], 400);
        }

        try {
            $businessId = (int) ($request->business_id ?: 1);
            $productId = $request->filled('product_id') ? (int) $request->product_id : null;
            $isPublished = $request->has('is_published')
                ? filter_var($request->is_published, FILTER_VALIDATE_BOOLEAN)
                : true;
            $sortOrder = (int) ($request->sort_order ?: 0);

            $created = DB::transaction(function () use ($files, $businessId, $productId, $isPublished, $sortOrder) {
                $reviews = [];

                foreach ($files as $index => $file) {
                    if (!$file || !$file->isValid()) {
                        continue;
                    }

                    $review = ShopProductReview::create([
                        'product_id' => $productId,
                        'business_id' => $businessId,
                        'customer_name' => null,
                        'place' => null,
                        'purchase_date' => null,
                        'review_text' => null,
                        'is_published' => $isPublished,
                        'sort_order' => $sortOrder + $index,
                    ]);

                    $ext = $file->getClientOriginalExtension() ?: 'jpg';
                    $fileName = 'review_' . $review->id . '_' . time() . '_' . uniqid() . '.' . $ext;
                    $filePath = $file->storeAs('public/review_images', $fileName);

                    ShopProductReviewImage::create([
                        'review_id' => $review->id,
                        'image' => $filePath,
                        'sort_order' => 0,
                    ]);

                    $reviews[] = $review->load('images');
                }

                return $reviews;
            });

            if (empty($created)) {
                return response()->json([
                    'status' => false,
                    'message' => 'No valid images were uploaded.',
                ], 400);
            }

            return response()->json([
                'status' => true,
                'message' => count($created) . ' photo(s) added to Living With Art.',
                'data' => $created,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create gallery photos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/reviews/images/upload
     */
    public function uploadImages(Request $request)
    {
        $files = $this->collectImageFiles($request);
        if (count($files) < 1) {
            return response()->json([
                'status' => false,
                'message' => 'At least one image is required (images[]).',
            ], 400);
        }

        try {
            $uploaded = [];
            foreach ($files as $index => $file) {
                if (!$file || !$file->isValid()) {
                    continue;
                }
                $ext = $file->getClientOriginalExtension() ?: 'jpg';
                $fileName = 'review_' . time() . '_' . uniqid() . '.' . $ext;
                $filePath = $file->storeAs('public/review_images', $fileName);
                $uploaded[] = [
                    'image' => $filePath,
                    'sort_order' => $index,
                ];
            }

            if (empty($uploaded)) {
                return response()->json([
                    'status' => false,
                    'message' => 'No valid images were uploaded.',
                ], 400);
            }

            return response()->json([
                'status' => true,
                'message' => count($uploaded) . ' image(s) uploaded successfully.',
                'data' => $uploaded,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to upload images.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/reviews?business_id=&published=1&random=1&limit=
     */
    public function index(Request $request)
    {
        $query = ShopProductReview::with(['images']);

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->product_id);
        }
        if ($request->filled('business_id')) {
            $query->where('business_id', (int) $request->business_id);
        }
        if ($request->has('published')) {
            $published = filter_var($request->published, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_published', $published);
        } else {
            $query->where('is_published', true);
        }

        if (filter_var($request->get('random'), FILTER_VALIDATE_BOOLEAN)) {
            $query->inRandomOrder();
        } else {
            $query->orderByDesc('sort_order')->orderByDesc('id');
        }

        if ($request->filled('limit')) {
            $query->limit(min(100, max(1, (int) $request->limit)));
        }

        $reviews = $query->get();

        return response()->json([
            'status' => true,
            'data' => $reviews,
        ]);
    }

    public function show($id)
    {
        $review = ShopProductReview::with(['images', 'product'])->find($id);
        if (!$review) {
            return response()->json(['status' => false, 'message' => 'Review not found'], 404);
        }
        return response()->json(['status' => true, 'data' => $review]);
    }

    public function destroy($id)
    {
        $review = ShopProductReview::with('images')->find($id);
        if (!$review) {
            return response()->json(['status' => false, 'message' => 'Review not found'], 404);
        }

        try {
            foreach ($review->images as $img) {
                $path = $img->image;
                if ($path && str_starts_with($path, 'public/')) {
                    $full = storage_path('app/' . $path);
                    if (is_file($full)) {
                        @unlink($full);
                    }
                }
                $img->delete();
            }
            $review->delete();

            return response()->json(['status' => true, 'message' => 'Photo deleted.']);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/reviews/top-products — kept for compatibility; returns empty when photos are unlinked.
     */
    public function topProducts(Request $request)
    {
        return response()->json(['status' => true, 'data' => []]);
    }

    private function collectImageFiles(Request $request): array
    {
        $files = [];
        $uploadedFiles = $request->allFiles();

        if (isset($uploadedFiles['images'])) {
            $images = $uploadedFiles['images'];
            if (is_array($images)) {
                foreach ($images as $file) {
                    $files[] = $file;
                }
            } else {
                $files[] = $images;
            }
            return $files;
        }

        foreach ($uploadedFiles as $key => $value) {
            if (preg_match('/^images\[\d+\]$/', $key) || preg_match('/^images\.\d+$/', $key)) {
                if (is_array($value)) {
                    foreach ($value as $file) {
                        $files[] = $file;
                    }
                } else {
                    $files[] = $value;
                }
            }
        }

        return $files;
    }
}
