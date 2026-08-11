<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShopProductReview;
use App\Models\ShopProductReviewImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Product reviews with multi-image upload.
 * Images are stored the same way as product images: storage/app/public/review_images
 */
class ReviewController extends Controller
{
    /**
     * POST /api/reviews
     * Multipart: product_id*, review_text*, customer_name?, place?, purchase_date?,
     *            is_published?, business_id?, images[]* (min 1)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
            'review_text' => 'required|string|min:3',
            'customer_name' => 'nullable|string|max:255',
            'place' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
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
                'message' => 'At least one review image is required.',
            ], 400);
        }

        try {
            $product = Product::findOrFail($request->product_id);

            $review = DB::transaction(function () use ($request, $files, $product) {
                $review = ShopProductReview::create([
                    'product_id' => $product->id,
                    'business_id' => (int) ($request->business_id ?: ($product->business_id ?? 1)),
                    'customer_name' => $request->customer_name ?: null,
                    'place' => $request->place ?: null,
                    'purchase_date' => $request->purchase_date ?: null,
                    'review_text' => trim($request->review_text),
                    'is_published' => $request->has('is_published')
                        ? filter_var($request->is_published, FILTER_VALIDATE_BOOLEAN)
                        : true,
                    'sort_order' => (int) ($request->sort_order ?: 0),
                ]);

                foreach ($files as $index => $file) {
                    if (!$file || !$file->isValid()) {
                        continue;
                    }
                    $ext = $file->getClientOriginalExtension() ?: 'jpg';
                    $fileName = 'review_' . $review->id . '_' . time() . '_' . uniqid() . '.' . $ext;
                    $filePath = $file->storeAs('public/review_images', $fileName);

                    ShopProductReviewImage::create([
                        'review_id' => $review->id,
                        'image' => $filePath,
                        'sort_order' => $index,
                    ]);
                }

                return $review->load('images');
            });

            if ($review->images->isEmpty()) {
                $review->delete();
                return response()->json([
                    'status' => false,
                    'message' => 'No valid images were uploaded.',
                ], 400);
            }

            return response()->json([
                'status' => true,
                'message' => 'Review created successfully.',
                'data' => $review,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create review.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/reviews/images/upload
     * Upload one or more images only (returns storage paths). Same storage style as products.
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
     * GET /api/reviews?product_id=&business_id=&published=1
     */
    public function index(Request $request)
    {
        $query = ShopProductReview::with(['images', 'product:id,name,price,is_temp,is_processed']);

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

        $reviews = $query->orderByDesc('sort_order')->orderByDesc('id')->get();

        return response()->json([
            'status' => true,
            'data' => $reviews,
        ]);
    }

    /**
     * GET /api/reviews/{id}
     */
    public function show($id)
    {
        $review = ShopProductReview::with(['images', 'product'])->find($id);
        if (!$review) {
            return response()->json(['status' => false, 'message' => 'Review not found'], 404);
        }
        return response()->json(['status' => true, 'data' => $review]);
    }

    /**
     * DELETE /api/reviews/{id}
     */
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

            return response()->json(['status' => true, 'message' => 'Review deleted.']);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete review.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/reviews/top-products?business_id=&limit=12
     * Top reviewed products (for Shop by Review page).
     */
    public function topProducts(Request $request)
    {
        $limit = min(50, max(1, (int) ($request->limit ?: 12)));
        $businessId = (int) ($request->business_id ?: 1);

        $rows = DB::table('shop_product_reviews as r')
            ->join('products as p', 'p.id', '=', 'r.product_id')
            ->where('r.business_id', $businessId)
            ->where('r.is_published', 1)
            ->where('p.is_temp', 0)
            ->where('p.is_processed', 1)
            ->select(
                'p.id as product_id',
                'p.name',
                'p.price',
                'p.artist_name',
                DB::raw('COUNT(r.id) as review_count'),
                DB::raw('MAX(r.id) as latest_review_id')
            )
            ->groupBy('p.id', 'p.name', 'p.price', 'p.artist_name')
            ->orderByDesc('review_count')
            ->orderByDesc('latest_review_id')
            ->limit($limit)
            ->get();

        return response()->json(['status' => true, 'data' => $rows]);
    }

    /**
     * Collect uploaded files from images, images[], or images[n] keys.
     */
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
