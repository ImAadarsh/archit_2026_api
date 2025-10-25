<?php

namespace App\Http\Controllers;

use App\Models\Addres;
use App\Models\Businesss;
use App\Models\Category;
use App\Models\Expenses;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Locations;
use App\Models\Product;
use App\Models\User;
use App\Models\UserInquiry;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class Admin extends Controller
{
    public function insertBusiness(Request $request)
    {
        $rules = [
            'business_name' => 'required',
            'gst' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'alternate_phone' => 'nullable',
            'owner_name' => 'required',
        ];
    
        $validator = Validator::make($request->all(), $rules);
    
        if ($validator->fails()) {
            return $validator->errors();
        }
        try {
            $business = new Businesss();
            $business->business_name = $request->business_name;
            $business->gst = $request->gst;
            $business->email = $request->email;
            $business->phone = $request->phone;
            $business->alternate_phone = $request->input('alternate_phone');
            $business->owner_name = $request->owner_name;
            if ($request->hasFile('logo')) {
                $file = $request->file('logo')->store('public/logo');
                $business->logo = $file;
            }
            $business->save();
            return response([
                'status' => true,
                'message' => 'Business created successfully.',
                'data' => $business
            ], 200);
        } catch (\Exception $e) {
            return response([
                'status' => false,
                'message' => 'Failed to insert business.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function insertLocation(Request $request)
{
    $rules = [
        'location_name' => 'required',
        'business_id' => 'required',
        'address' => 'required',
        'email' => 'required|email',
        'alternate_phone' => 'nullable',
        'phone' => 'required',
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
        return $validator->errors();
    }

    try {
        $location = new Locations();
        $location->location_name = $request->location_name;
        $location->business_id = $request->business_id;
        $location->address = $request->address;
        $location->email = $request->email;
        $location->alternate_phone = $request->input('alternate_phone');
        $location->phone = $request->phone;
        $location->save();
        return response([
            'status' => true,
            'message' => 'Location created successfully.',
            'data' => $location
        ], 200);
    } catch (\Exception $e) {
        return response([
            'status' => false,
            'message' => 'Failed to insert location.',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function createInvoice(Request $request)
{
    $rules = [
        'type' => 'required|in:normal,performa'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
        return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
    }

    try {
        DB::beginTransaction();

        $nextSerialNo = $this->getNextSerialNumber($request->type, $request->location_id);

        $invoice = Invoice::create([
            'serial_no' => $nextSerialNo,
            'name' => $request->name,
            'mobile_number' => $request->mobile_number,
            'customer_type' => $request->customer_type,
            'doc_type' => $request->doc_type,
            'doc_no' => strtoupper($request->doc_no),
            'business_id' => $request->business_id,
            'location_id' => $request->location_id,
            'payment_mode' => $request->payment_mode,
            'billing_address_id' => $request->billing_address_id,
            'shipping_address_id' => $request->shipping_address_id,
            'type' => $request->type,
            'is_completed' => 0,
            'invoice_date' => $request->invoice_date,
            'full_paid' => $request->full_paid,
            'total_paid' => $request->total_paid
        ]);

        DB::commit();

        return response()->json([
            'status' => true, 
            'message' => 'Invoice created successfully.', 
            'data' => $invoice
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'status' => false, 
            'message' => 'Failed to create invoice.', 
            'error' => $e->getMessage()
        ], 500);
    }
}

private function getNextSerialNumber($type, $location_id)
{
    if ($type === 'performa') {
        return null;
    }
    if(!(Invoice::where('type', '!=', 'performa')->where('location_id',$location_id)->first())){
        return 1;
    }
    return Invoice::where('type', '!=', 'performa')->where('location_id',$location_id)
        ->max('serial_no') + 1;
}
    public function getAllInvoices(Request $request){
    try {
        $query = Invoice::query();
        $query->orderBy('created_at', 'desc');

                  // Filter by day
                  if($request->has('day')){
                    $query->whereDate('invoice_date', $request->day);
                }
        
                // Filter by month
                if($request->has('month')){
                    $query->whereMonth('invoice_date', $request->month);
                }
        
                // Filter by week
                if($request->has('week_start')){
                    // Assuming the week is passed as an array with start and end dates
                    $query->whereBetween('invoice_date', [$request->week_start, $request->week_end]);
                }
        
                // Filter by year
                if($request->has('year')){
                    $query->whereYear('invoice_date', $request->year);
                }

        // If location_id and business_id are provided, filter by both
        if ($request->has('location_id') && $request->has('business_id')) {
            $query->where('location_id', $request->location_id)
                  ->where('business_id', $request->business_id);
        }
        // If only business_id is provided, filter by business_id
        elseif ($request->has('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        // Retrieve the invoices along with the total price
        $invoices = $query->withSum('items', 'price_of_all')->get();

        // Check if any invoices are found
        if ($invoices->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No invoices found.'], 404);
        }

        // Return the invoices
        return response()->json(['status' => true, 'message' => 'Invoices retrieved successfully.', 'data' => $invoices], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => false, 'message' => 'Failed to retrieve invoices.', 'error' => $e->getMessage()], 500);
    }
}
    public function getDetailedInvoice($invoiceId){
    try {
        // Fetch the invoice along with its related items and product details
        $invoice = Invoice::with(['items.product', 'billingAddress', 'shippingAddress'])->find($invoiceId);

        // Check if the invoice is found
        if (!$invoice) {
            return response()->json(['status' => false, 'message' => 'Invoice not found.'], 404);
        }

        // Return the detailed invoice
        return response()->json(['status' => true, 'message' => 'Invoice details retrieved successfully.', 'data' => $invoice], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => false, 'message' => 'Failed to retrieve invoice details.', 'error' => $e->getMessage()], 500);
    }
}

public function getDetailedInvoiceWeb($invoiceId)
{
    try {
        // Fetch the invoice along with its related items, product details, and addresses
        $invoice = Invoice::with(['items.product', 'billingAddress', 'shippingAddress'])
            ->leftJoin('businessses', 'invoices.business_id', '=', 'businessses.id')
            ->leftJoin('locations', 'invoices.location_id', '=', 'locations.id')
            ->select(
                'invoices.*',
                'businessses.gst as business_gst',
                'businessses.business_name as business_name',
                'businessses.logo as business_logo',
                'locations.location_name',
                'locations.address as location_address',
                'locations.email as location_email',
                'locations.phone as location_phone',
                'locations.alternate_phone as location_alternate_phone'
            )
            ->find($invoiceId);

        // Check if the invoice is found
        if (!$invoice) {
            return response()->json(['status' => false, 'message' => 'Invoice not found.'], 404);
        }

        // Fetch bank details for the business and location
        $bankDetails = DB::table('banks')
            ->where('business_id', $invoice->business_id)
            ->where('location_id', $invoice->location_id)
            ->first();

        // Prepare business and location info
        $businessInfo = [
            'name' => $invoice->business_name ?? 'Archit Art Gallery',
            'address' => $invoice->location_address ?? 'Shop No: 28 Kirti Nagar Furniture Block, Kirti Nagar, New Delhi – 110015',
            'phone' => $invoice->location_phone ?? '+91- 9868 200 002',
            'alternate_phone' => $invoice->location_alternate_phone ?? '+91- 9289 388 374',
            'gst' => $invoice->business_gst ?? '07AADPA2039E1ZF',
            'logo' => $invoice->business_logo ?? null
        ];

        // Add business info and bank details to the invoice data
        $invoice->business_info = $businessInfo;
        $invoice->bank_details = $bankDetails;

        // Return the detailed invoice
        return response()->json([
            'status' => true,
            'message' => 'Invoice details retrieved successfully.',
            'data' => $invoice
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to retrieve invoice details.',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function getBulkInvoicesWeb(Request $request)
{
    try {
        // Start with a base query
        $query = Invoice::with(['items.product', 'billingAddress', 'shippingAddress'])
            ->leftJoin('businessses', 'invoices.business_id', '=', 'businessses.id')
            ->leftJoin('locations', 'invoices.location_id', '=', 'locations.id')
            ->select(
                'invoices.*',
                'businessses.gst as business_gst',
                'locations.location_name',
                'locations.address as location_address',
                'locations.email as location_email',
                'locations.phone as location_phone',
                'locations.alternate_phone as location_alternate_phone'
            )
            ->where('invoices.is_completed', 1);

        // Apply date range filter if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('invoices.invoice_date', [$request->start_date, $request->end_date]);
        }

        // Apply min amount filter if provided
        if ($request->has('min_amount')) {
            $query->where('invoices.total_amount', '>=', $request->min_amount);
        }

        // Apply max amount filter if provided
        if ($request->has('max_amount')) {
            $query->where('invoices.total_amount', '<=', $request->max_amount);
        }

        // Apply type filter if provided
        if ($request->has('type')) {
            $query->where('invoices.type', $request->type);
        }

        // Apply payment mode filter if provided
        if ($request->has('payment_mode')) {
            $query->where('invoices.payment_mode', $request->payment_mode);
        }

        // Apply business_id filter if provided
        if ($request->has('business_id')) {
            $query->where('invoices.business_id', $request->business_id);
        }

        // Apply location_id filter if provided
        if ($request->has('location_id')) {
            $query->where('invoices.location_id', $request->location_id);
        }

        // Execute the query and get the results
        $invoices = $query->get();

        // Check if any invoices are found
        if ($invoices->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No invoices found matching the criteria.'], 404);
        }

        // Process each invoice to add business_info
        $processedInvoices = $invoices->map(function ($invoice) {
            $invoice->business_info = [
                'name' => $invoice->location_name ?? 'Archit Art Gallery',
                'address' => $invoice->location_address ?? 'Shop No: 28 Kirti Nagar Furniture Block, Kirti Nagar, New Delhi – 110015',
                'phone' => $invoice->location_phone ?? '+91- 9868 200 002',
                'alternate_phone' => $invoice->location_alternate_phone ?? '+91- 9289 388 374',
                'gst' => $invoice->business_gst ?? '07AADPA2039E1ZF'
            ];
            
            // Remove the additional fields from the main invoice object
            unset($invoice->business_gst, $invoice->location_name, $invoice->location_address, 
                  $invoice->location_email, $invoice->location_phone, $invoice->location_alternate_phone);
            
            return $invoice;
        });

        // Return the detailed invoices
        return response()->json([
            'status' => true, 
            'message' => 'Invoices retrieved successfully.', 
            'data' => $processedInvoices
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false, 
            'message' => 'Failed to retrieve invoices.', 
            'error' => $e->getMessage()
        ], 500);
    }
}
    public function removeInvoice($id){
    try {
        // Find the invoice by ID
        $invoice = Invoice::find($id);

        // If invoice not found, return error
        if (!$invoice) {
            return response()->json(['status' => false, 'message' => 'Invoice not found.'], 404);
        }

        // Delete the invoice
        $invoice->delete();

        return response()->json(['status' => true, 'message' => 'Invoice deleted successfully.'], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => false, 'message' => 'Failed to delete invoice.', 'error' => $e->getMessage()], 500);
    }
}

public function cancelInvoice($id){
    try {
        // Find the invoice by ID
        $invoice = Invoice::find($id);

        // If invoice not found, return error
        if (!$invoice) {
            return response()->json(['status' => false, 'message' => 'Invoice not found.'], 404);
        }

        // Delete the invoice
        $invoice->is_cancelled = 1;
        $invoice->save();

        return response()->json(['status' => true, 'message' => 'Invoice cancelled successfully.'], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => false, 'message' => 'Failed to delete invoice.', 'error' => $e->getMessage()], 500);
    }
}
public function editInvoice(Request $request)
{
    $rules = [
        'id' => 'required|exists:invoices,id'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
        return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
    }

    try {
        DB::beginTransaction();

        $invoice = Invoice::findOrFail($request->id);

        $updatableFields = [
            'name', 'mobile_number', 'customer_type', 'doc_type', 'doc_no',
            'business_id', 'location_id', 'payment_mode', 'billing_address_id',
            'shipping_address_id', 'is_completed', 'invoice_date', 'full_paid', 'total_paid'
        ];

        $updateData = $request->only($updatableFields);
        
        // Convert doc_no to uppercase if it exists in the request
        if (isset($updateData['doc_no'])) {
            $updateData['doc_no'] = strtoupper($updateData['doc_no']);
        }

        $invoice->fill($updateData);
        $invoice->save();

        DB::commit();

        return response()->json([
            'status' => true,
            'message' => 'Invoice updated successfully.',
            'data' => $invoice
        ], 200);

    } catch (ModelNotFoundException $e) {
        DB::rollBack();
        return response()->json([
            'status' => false,
            'message' => 'Invoice not found.',
        ], 404);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'status' => false,
            'message' => 'Failed to update invoice.',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function addProduct(Request $request)
{
    $rules = [
        'invoice_id' => 'required|exists:invoices,id',
        'hsn_code' => 'required',
        'name' => 'required',
        'price' => 'required|numeric|min:0',
        'quantity' => 'required|integer|min:1',
     
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
        return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
    }

    try {
        DB::beginTransaction();

        $invoice = Invoice::findOrFail($request->invoice_id);
        if ($request->product_id != null){
            // Add debugging to see what product_id is being passed
            \Log::info('Looking for product with ID: ' . $request->product_id);
            $product = Product::find($request->product_id);
            if (!$product) {
                return response()->json([
                    'status' => false, 
                    'message' => 'Product not found with ID: ' . $request->product_id
                ], 404);
            }
        }else if ($request->hsn_code){
            $product = $this->getOrCreateProduct($request->hsn_code, $request->name,$request->category_id,$request->quantity, $invoice);
        }else{
            return response()->json(['status' => false, 'message' => 'Product not found.'], 404);
        }
        $address = Addres::where('invoice_id', $request->invoice_id)->first();
        $business_location = Locations::findOrFail($invoice->location_id);
        $state = $business_location->state;
        $item = new Item();
        $item->product_id = $product->id;
        $item->invoice_id = $request->invoice_id;
        $item->quantity = $request->quantity;
        $item->is_gst = $request->is_gst;
        $item->gst_rate = $request->gst_percent;
        $item->category_id = $request->category_id;


        $this->calculateItemPrice($item, $invoice, $request->price, $request->is_gst);
        $this->calculateGST($item, $address,$state, $invoice->type);

        $item->price_of_all = $this->calculateTotalPrice($item);
        $item->save();

        $this->updateInvoiceTotals($invoice, $item);

        DB::commit();
        $formattedItem = $this->formatItemResponse($item, $invoice);
        return response()->json([
            'status' => true,
            'message' => 'Product added successfully.',
            'data' => $formattedItem
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['status' => false, 'message' => 'Failed to add product.', 'error' => $e->getMessage()], 500);
    }
}

private function getOrCreateProduct($hsnCode, $name, $category_id, $quantity, $invoice)
{
    
    $product = new Product();
    $product->hsn_code = $hsnCode;
    $product->name = $name;
    $product->business_id = $invoice->business_id;
    $product->location_id = $invoice->location_id;
    $product->category_id = $category_id;
    $product->quantity = $quantity;
    $product->save();
    return $product;
}

private function calculateItemPrice(Item $item, Invoice $invoice, $price, $isGst)
{
    if ($invoice->type == 'normal' && $isGst) {
        // Use dynamic GST rate instead of hardcoded 18%
        $gstMultiplier = 1 + ($item->gst_rate / 100);
        $item->price_of_one = round($price / $gstMultiplier, 2);
    } else {
        $item->price_of_one = $price;
    }
}

private function calculateGST(Item $item, ?Addres $address, $state, $invoiceType)
{
    if ($invoiceType == 'normal') {
        $basePrice = $item->price_of_one * $item->quantity;
        $isDelhi = $address && strtolower($address->state) == strtolower($state);
        
        // Use the dynamic GST rate from the item
        $gstRate = $item->gst_rate / 100; // Convert percentage to decimal
        $cgstDgstRate = $gstRate / 2; // Split GST rate equally between CGST and DGST

        if ($isDelhi) {
            $item->dgst = $item->cgst = round($cgstDgstRate * $basePrice, 2);
            $item->igst = 0;
        } else {
            $item->dgst = $item->cgst = 0;
            $item->igst = round($gstRate * $basePrice, 2);
        }
    } else {
        $item->dgst = $item->cgst = $item->igst = 0;
    }
}

private function calculateTotalPrice(Item $item)
{
    return round($item->price_of_one * $item->quantity + $item->dgst + $item->cgst + $item->igst, 2);
}

private function updateInvoiceTotals(Invoice $invoice, Item $item)
{
    $invoice->total_dgst += $item->dgst;
    $invoice->total_cgst += $item->cgst;
    $invoice->total_igst += $item->igst;
    $invoice->total_amount += $item->price_of_all;
    $invoice->save();
}

private function formatItemResponse(Item $item, Invoice $invoice) {
    return array_merge(
        $item->toArray(),
        [
            't_dgst' => round($invoice->total_dgst, 2),
            't_cgst' => round($invoice->total_cgst, 2),
            't_igst' => round($invoice->total_igst, 2),
            'total_amount' => round($invoice->total_amount, 2),
            'total_ex_gst_amount' => round($invoice->total_amount - $invoice->total_dgst - $invoice->total_cgst - $invoice->total_igst, 2),
        ]
    );
}
    public function getItemsByInvoiceId(Request $request){
    // Validation rules for invoice ID
    $rules = [
        'invoice_id' => 'required|exists:invoices,id',
    ];

    // Validate the incoming request
    $validator = Validator::make($request->all(), $rules);

    // If validation fails, return errors
    if ($validator->fails()) {
        return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
    }

    try {
        // Fetch items associated with the provided invoice ID
        $items = Item::where('invoice_id', $request->invoice_id)->get();

        // Check if any items are found
        if ($items->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No items found for the provided invoice ID.'], 404);
        }

        // Return the items
        return response()->json(['status' => true, 'message' => 'Items retrieved successfully.', 'data' => $items], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => false, 'message' => 'Failed to retrieve items.', 'error' => $e->getMessage()], 500);
    }
}

    public function editProduct(Request $request){
    // Validation rules for updating item
    $rules = [
        'hsn_code' => 'required',
        'name' => 'required',
        'price' => 'required',
        'quantity' => 'required',
        'item_id' => 'required',
    ];

    // Validate the incoming request
    $validator = Validator::make($request->all(), $rules);

    // If validation fails, return errors
    if ($validator->fails()) {
        return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
    }

    try {
        // Find the item by its ID
        $item = Item::findOrFail($request->item_id);

        // Find or create the product based on HSN code
        if ($product = Product::where('hsn_code', $request->hsn_code)->first()) {
            $item->product_id = $product->id;
        } else {
            $product = new Product();
            $product->name = $request->name;
            $product->hsn_code = $request->hsn_code;
            $product->save();
            $item->product_id = $product->id;
        }

        // Update item details
        $item->quantity = $request->quantity;
        if($request->is_gst==1){
            $item->price_of_one = $request->price;
        }else{
            $item->price_of_one = $request->price/1.18;
        }
        $address = Addres::where('invoice_id', $request->invoice_id)->first();
        // Calculate GST based on whether it's inclusive or exclusive
        if($request->type=='normal'){
                  if ($request->is_gst == 1) {
            // Inclusive GST
            // Check if the address place is Delhi
           
            if ($address->state == 'delhi') {
                $item->dgst = (0.09 * $item->price_of_one) * $request->quantity; // 9% GST for Delhi
                $item->cgst = (0.09 * $item->price_of_one) * $request->quantity; // 9% GST for Delhi
                $item->igst = 0; // No IGST for Delhi
            } else {
                $item->dgst = 0; // No DGST for other states
                $item->cgst = 0; // No CGST for other states
                $item->igst = (0.18 * $item->price_of_one) * $request->quantity; // 18% IGST for other states
            }
        } else {
            // Exclusive GST
            if ($address->state == 'delhi') {
                $item->dgst = (0.09 * $item->price_of_one) * $request->quantity; // 9% GST for Delhi
                $item->cgst = (0.09 * $item->price_of_one) * $request->quantity; // 9% GST for Delhi
                $item->igst = 0; // No IGST for Delhi
            } else {
                $item->dgst = 0; // No DGST for other states
                $item->cgst = 0; // No CGST for other states
                $item->igst = (0.18 * $item->price_of_one) * $request->quantity; // 18% IGST for other states
            }
        }
            
        }else if($request->type=='performa'){
             $item->dgst = 0;
                $item->cgst = 0;
                $item->igst = 0;
            
        }
  

        // Calculate total price of the item
        $item->price_of_all = $item->price_of_one * $request->quantity + $item->dgst + $item->cgst + $item->igst;
        $update_main = Invoice::find($request->invoice_id);
        $update_main->total_dgst = $update_main->total_dgst+$item->dgst;
        $update_main->total_cgst = $update_main->total_cgst+$item->cgst;
        $update_main->total_igst = $update_main->total_igst+$item->igst;
        $update_main->total_amount = $update_main->total_amount + $item->price_of_all;
        $update_main->save();
        // Save the updated item
        $item->save();
        
        return response()->json(['status' => true, 'message' => 'Item updated successfully.', 'data' => $item], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => false, 'message' => 'Failed to update item.', 'error' => $e->getMessage()], 500);
    }
}

public function removeItem($item_id)
{
    try {
        DB::beginTransaction();

        $item = Item::findOrFail($item_id);
        $invoice = Invoice::findOrFail($item->invoice_id);

        // Update the main invoice totals
        $invoice->total_dgst -= $item->dgst;
        $invoice->total_cgst -= $item->cgst;
        $invoice->total_igst -= $item->igst;
        $invoice->total_amount -= $item->price_of_all;

        $invoice->save();

        // Delete the item
        $item->delete();

        DB::commit();

        // Create response data with rounded values
        $response_data = new \stdClass();
        $response_data->t_dgst = round($invoice->total_dgst, 2);
        $response_data->t_cgst = round($invoice->total_cgst, 2);
        $response_data->t_igst = round($invoice->total_igst, 2);
        $response_data->total_amount = round($invoice->total_amount, 2);
        $response_data->total_ex_gst_amount = round($invoice->total_amount - $invoice->total_dgst - $invoice->total_cgst - $invoice->total_igst, 2);

        return response()->json([
            'status' => true, 
            'message' => 'Item removed successfully.', 
            'data' => $response_data
        ], 200);

    } catch (ModelNotFoundException $e) {
        DB::rollBack();
        return response()->json([
            'status' => false, 
            'message' => $e->getMessage()
        ], 404);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'status' => false, 
            'message' => 'Failed to remove item.', 
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function addAddress(Request $request){
    $rules = [
        'state' => 'required',
        'invoice_id' => 'required',
        'type' => 'required',
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
        return $validator->errors();
    }

    try {
        if(Addres::where('invoice_id', $request->invoice_id)->where('type',$request->type)->first()){
            $location = Addres::where('invoice_id', $request->invoice_id)->where('type',$request->type)->first();
            $location->type = $request->type;
            $location->address_1 = $request->address_1;
            $location->address_2 = $request->address_2;
            $location->city = $request->city;
            $location->state = $request->state;
            $location->pincode = $request->pincode;
            $location->save();
        }else{
            $location = new Addres();
            $location->invoice_id = $request->invoice_id;
            $location->type = $request->type;
            $location->address_1 = $request->address_1;
            $location->address_2 = $request->address_2;
            $location->city = $request->city;
            $location->state = $request->state;
            $location->pincode = $request->pincode;
            $location->save();
             
        }
        $update_main = Invoice::find($request->invoice_id);
             if($request->type==0){
                 $update_main->billing_address_id = $location->id;
             }else{
                  $update_main->shipping_address_id = $location->id;
             }
             $update_main->save();
        
        
        return response([
            'status' => true,
            'message' => 'Adress created/Updated successfully.',
            'data' => $location
        ], 200);
    } catch (\Exception $e) {
        return response([
            'status' => false,
            'message' => 'Failed to insert location.',
            'error' => $e->getMessage()
        ], 500);
    }
}
private function getFileExtension($base64Data) {
    $fileInfo = explode(';base64,', $base64Data);
    $mime = str_replace('data:', '', $fileInfo[0]);
    $extension = explode('/', $mime)[1];
    return $extension;
}
public function addExpense(Request $request){
    $rules = [
        'name' => 'required',
        'amount' => 'required',
        'type' => 'required',
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
        return $validator->errors();
    }

    try {
        if($expense = Expenses::find($request->id)){
            $expense->name = $request->name;
            $expense->amount = $request->amount;
            $expense->type = $request->type;
            $expense->business_id = $request->business_id;
            $expense->location_id = $request->location_id;
            $expense->user_id = $request->user_id;
            $expense->save();
            $expense->find($expense->id);
            if ($request->has('file')) {
                $fileData = $request->file; // base64 encoded file data
                $fileName = 'expense_' . time() . '.' . $this->getFileExtension($fileData).'.'.$request->extension;
                $filePath = 'public/expense/' . $fileName;
                \Storage::put($filePath, base64_decode($fileData));
                $expense->file = $filePath;
            }
    
            $expense->save();
        } else {
            $expense = new Expenses();
            $expense->name = $request->name;
            $expense->amount = $request->amount;
            $expense->type = $request->type;
            $expense->business_id = $request->business_id;
            $expense->location_id = $request->location_id;
            $expense->user_id = $request->user_id;
            $expense->save();
            $expense->find($expense->id);
            if ($request->has('file')) {
                $fileData = $request->file; // base64 encoded file data
                $fileName = 'expense_'.$expense->id.'.'.$request->extension;
                $filePath = 'public/expense/' . $fileName;
                \Storage::put($filePath, base64_decode($fileData));
                $expense->file = $filePath;
            }
            $expense->save();
        }
        
        return response([
            'status' => true,
            'message' => 'Expense created/Updated successfully.',
            'data' => $expense
        ], 200);
    } catch (\Exception $e) {
        return response([
            'status' => false,
            'message' => 'Failed to insert expense.',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function getAllExpenses(Request $request){
    $query = Expenses::query();

    // Filter by day
    if($request->has('day')){
        $query->whereDate('created_at', $request->day);
    }

    // Filter by month
    if($request->has('month')){
        $query->whereMonth('created_at', $request->month);
    }

      // Apply payment mode filter if provided
      if ($request->has('business_id')) {
        $query->where('business_id', $request->business_id);
    }
     // Apply payment mode filter if provided
     if ($request->has('location_id')) {
        $query->where('location_id', $request->location_id);
    }

    // Filter by week
    if($request->has('week_start')){
        // Assuming the week is passed as an array with start and end dates
        $query->whereBetween('created_at', [$request->week_start, $request->week_end]);
    }

    // Filter by year
    if($request->has('year')){
        $query->whereYear('created_at', $request->year);
    }

    // Filter by expense type (0 or 1)
    if($request->has('type')){
        $type = $request->type;
        $query->where('type', $type);
    }

        // Filter by name
        if($request->has('name')){
            $name = $request->name;
            $query->where('name', 'like', '%' . $name . '%');
        }

            // Filter by amount range
    if($request->has('amount_min') && $request->has('amount_max')){
        $amountMin = $request->amount_min;
        $amountMax = $request->amount_max;
        $query->whereBetween('amount', [$amountMin, $amountMax]);
    } 

    if($request->has('expense_id')){
        $query->where('id', $request->expense_id);
    }

        // Order by id in descending order
        $query->orderBy('id', 'DESC');


    $expenses = $query->get();

    return response([
        'status' => true,
        'data' => $expenses,
        'message' => "Data Feteched."
    ], 200);
}


// Get expense by ID
public function getExpenseById($id){
    $expense = Expenses::find($id);
    if($expense){
        return response([
            'status' => true,
            'data' => $expense
        ], 200);
    }else{
        return response([
            'status' => false,
            'message' => 'Expense not found.'
        ], 404);
    }
}

// Delete expense by ID
public function deleteExpense(Request $request){
    $expense = Expenses::find($request->id);
    if($expense){
        $expense->delete();
        return response([
            'status' => true,
            'message' => 'Expense deleted successfully.'
        ], 200);
    }else{
        return response([
            'status' => false,
            'message' => 'Expense not found.'
        ], 404);
    }
}

    public function getAddressByInvoiceId(Request $request){
    // Validation rules for invoice ID
    $rules = [
        'invoice_id' => 'required|exists:addres,invoice_id',
    ];

    // Validate the incoming request
    $validator = Validator::make($request->all(), $rules);

    // If validation fails, return errors
    if ($validator->fails()) {
        return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
    }

    try {
        // Fetch the address associated with the provided invoice ID
        $address = Addres::where('invoice_id', $request->invoice_id)->get();

        // Check if the address is found
        if (!$address) {
            return response()->json(['status' => false, 'message' => 'No address found for the provided invoice ID.'], 404);
        }

        // Return the address
        return response()->json(['status' => true, 'message' => 'Address retrieved successfully.', 'data' => $address], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => false, 'message' => 'Failed to retrieve address.', 'error' => $e->getMessage()], 500);
    }
}

public function getSaleReport(Request $request){
    $query = Invoice::query();

    // Filter by day
    if($request->has('day')){
        $query->whereDate('invoice_date', $request->day);
    }

    // Filter by month
    if($request->has('month')){
        $query->whereMonth('invoice_date', $request->month);
    }

    // Filter by week
    if($request->has('week_start')){
        // Assuming the week is passed as an array with start and end dates
        $query->whereBetween('invoice_date', [$request->week_start, $request->week_end]);
    }

    // Filter by year
    if($request->has('year')){
        $query->whereYear('invoice_date', $request->year);
    }

    // Filter by invoice type or performa
    if($request->has('type')){
        $Type = $request->type;
        $query->where('type', $Type);
    }

    // Filter by name
    if($request->has('name')){
        $name = $request->name;
        $query->where('name', 'like', '%' . $name . '%');
    }

    // Filter by amount range
    if($request->has('amount_min') && $request->has('amount_max')){
        $amountMin = $request->amount_min;
        $amountMax = $request->amount_max;
        $query->whereBetween('total_amount', [$amountMin, $amountMax]);
    }

    // Filter by invoice ID
    if($request->has('invoice_id')){
        $query->where('id', $request->invoice_id);
    }

    // Order by id in descending order
    $query->orderBy('id', 'DESC');

    // Get the filtered invoices
    $query->where('is_completed', 1);
    $invoices = $query->get(['id', 'name', 'total_amount', 'invoice_date', 'type']);

    // Calculate total amount and total transactions
    $totalAmount = round($invoices->sum('total_amount'),2);
    $totalTransactions = $invoices->count();

    return response([
        'status' => true,
        'total_amount' => $totalAmount,
        'total_transactions' => $totalTransactions,
        'data' => $invoices,
        'message' => "Data fetched."
    ], 200);
}

public function getExpenseReport(Request $request){
    $query = Expenses::query();

    // Filter by day
    if($request->has('day')){
        $query->whereDate('created_at', $request->day);
    }

    // Filter by month
    if($request->has('month')){
        $query->whereMonth('created_at', $request->month);
    }

    // Filter by week
    if($request->has('week_start')){
        // Assuming the week is passed as an array with start and end dates
        $query->whereBetween('created_at', [$request->week_start, $request->week_end]);
    }

    // Filter by year
    if($request->has('year')){
        $query->whereYear('created_at', $request->year);
    }

    // Filter by expense type (0 or 1)
    if($request->has('type')){
        $type = $request->type;
        $query->where('type', $type);
    }

        // Filter by name
        if($request->has('name')){
            $name = $request->name;
            $query->where('name', 'like', '%' . $name . '%');
        }

          // Apply payment mode filter if provided
     if ($request->has('business_id')) {
        $query->where('business_id', $request->business_id);
    }
     // Apply payment mode filter if provided
     if ($request->has('location_id')) {
        $query->where('location_id', $request->location_id);
    }

            // Filter by amount range
    if($request->has('amount_min') && $request->has('amount_max')){
        $amountMin = $request->amount_min;
        $amountMax = $request->amount_max;
        $query->whereBetween('amount', [$amountMin, $amountMax]);
    } 

    if($request->has('expense_id')){
        $query->where('id', $request->expense_id);
    }

        // Order by id in descending order
        $query->orderBy('id', 'DESC');


    $expenses = $query->get();
        // Calculate total amount and total transactions
        $totalAmount = round($expenses->sum('amount'),2);
        $totalTransactions = $expenses->count();
        $params = [];

        if ($request->day) $params[] = "day=" . $request->day;
        if ($request->month) $params[] = "month=" . $request->month;
        if ($request->year) $params[] = "year=" . $request->year;
        if ($request->week_start) $params[] = "week_start=" . $request->week_start;
        if ($request->week_end) $params[] = "week_end=" . $request->week_end;
        if ($request->type) $params[] = "type=" . $request->type;
        if ($request->name) $params[] = "name=" . $request->name;
        if ($request->amount_min) $params[] = "amount_min=" . $request->amount_min;
        if ($request->amount_max) $params[] = "amount_max=" . $request->amount_max;
        if ($request->business_id) $params[] = "business_id=" . $request->business_id;
        if ($request->location_id) $params[] = "location_id=" . $request->location_id;
        
        $query_string = implode("&", $params);
        
        $excel = "https://business.architartgallery.in/api/expense-excel.php?" . $query_string;
        $pdf = "https://business.architartgallery.in/api/expense-pdf.php?" . $query_string;
    return response([
        'status' => true,
        'total_expense' => $totalAmount,
        'total_transactions' => $totalTransactions,
        'excel' => $excel,
        'pdf' => $pdf,
        'data' => $expenses,
        'message' => "Data Feteched."
    ], 200);
}

public function getPurchaseSaleInvoice(Request $request){
    $query = Invoice::query();

    // Filter by day
    if($request->has('day')){
        $query->whereDate('invoice_date', $request->day);
    }

    // Filter by month
    if($request->has('month')){
        $query->whereMonth('invoice_date', $request->month);
    }

    // Filter by week
    if($request->has('week_start')){
        // Assuming the week is passed as an array with start and end dates
        $query->whereBetween('invoice_date', [$request->week_start, $request->week_end]);
    }

    // Filter by year
    if($request->has('year')){
        $query->whereYear('invoice_date', $request->year);
    }

    // Filter by payment mode
    if($request->has('payment_mode')){
        $paymentMode = $request->payment_mode;
        $query->where('payment_mode', $paymentMode);
    }

    // Filter by invoice type (normal only)
    $query->where('type', 'normal');

    // Filter by name
    if($request->has('name')){
        $name = $request->name;
        $query->where('name', 'like', '%' . $name . '%');
    }

    // Filter by amount range
    if($request->has('amount_min') && $request->has('amount_max')){
        $amountMin = $request->amount_min;
        $amountMax = $request->amount_max;
        $query->whereBetween('total_amount', [$amountMin, $amountMax]);
    }

    // Filter by invoice ID
    if($request->has('invoice_id')){
        $query->where('id', $request->invoice_id);
    }

    // Ensure only completed invoices are considered
    $query->where('is_completed', 1);

    // Order by id in descending order
    $query->orderBy('id', 'DESC');

    // Select only the specified columns
    $invoices = $query->get(['id', 'name', 'total_amount', 'total_igst', 'total_cgst', 'total_dgst', 'invoice_date', 'type']);

    // Calculate total GST, amount excluding GST, and aggregate totals
    $totalGST = 0;
    $totalExcludingGST = 0;
    foreach ($invoices as $invoice) {
        $computedTotalGst = $invoice->total_igst + $invoice->total_cgst + $invoice->total_dgst;
        $computedAmountExGst = $invoice->total_amount - $computedTotalGst;
        $totalGST += $computedTotalGst;
        $totalExcludingGST += $computedAmountExGst;
    }

    // Calculate total amount and total transactions
    $totalAmount = round($invoices->sum('total_amount'), 2);
    $totalTransactions = $invoices->count();
    $params = [];

    if ($request->business_id) $params['business_id'] = $request->business_id;
    if ($request->location_id) $params['location_id'] = $request->location_id;
    if ($request->day) $params['day'] = $request->day;
    if ($request->month) $params['month'] = $request->month;
    if ($request->year) $params['year'] = $request->year;
    if ($request->week_start) $params['week_start'] = $request->week_start;
    if ($request->week_end) $params['week_end'] = $request->week_end;
    if ($request->amount_min) $params['amount_min'] = $request->amount_min;
    if ($request->amount_max) $params['amount_max'] = $request->amount_max;
    if ($request->payment_mode) $params['payment_mode'] = $request->payment_mode;
    
    $excel_params = $params;
    $excel_params['type'] = 'normal';
    $excel_query = http_build_query($excel_params);
    
    $pdf_params = $params;
    $pdf_params['type'] = 'normal';
    $pdf_params['max_amount'] = $params['amount_max'] ?? null;
    $pdf_params['min_amount'] = $params['amount_min'] ?? null;
    $pdf_params['start_date'] = $params['week_start'] ?? null;
    $pdf_params['end_date'] = $params['week_end'] ?? null;
    unset($pdf_params['amount_max'], $pdf_params['amount_min'], $pdf_params['week_start'], $pdf_params['week_end']);
    $pdf_query = http_build_query($pdf_params);
    
    $excel = "https://business.architartgallery.in/api/invoice-excel.php?" . $excel_query;
    $pdf = "https://invoice.invoicemate.in/invoices.html?" . $pdf_query;    
    return response([
        'status' => true,
        'total_amount' => $totalAmount,
        'total_transactions' => $totalTransactions,
        'total_gst' => round($totalGST, 2),
        'total_excluding_gst' => round($totalExcludingGST, 2),
        'pdf' => $pdf,
        'excel' => $excel,
        'data' => $invoices,
        'message' => "Data fetched."
    ], 200);
}
public function getInvoiceListReport(Request $request)
{
    $query = Invoice::query();

    // Filter by day
    if ($request->has('day')) {
        $query->whereDate('invoice_date', $request->day);
    }

    // Filter by month
    if ($request->has('month')) {
        $query->whereMonth('invoice_date', $request->month);
    }

    // Filter by week
    if ($request->has('week_start') && $request->has('week_end')) {
        $query->whereBetween('invoice_date', [$request->week_start, $request->week_end]);
    }

    // Filter by year
    if ($request->has('year')) {
        $query->whereYear('invoice_date', $request->year);
    }

    // Filter by invoice type or performa
    if ($request->has('type')) {
        $query->where('type', $request->type);
    }

    // Apply payment mode filter if provided
    if ($request->has('payment_mode')) {
        $query->where('payment_mode', $request->payment_mode);
    }

    // Apply business_id filter if provided
    if ($request->has('business_id')) {
        $query->where('business_id', $request->business_id);
    }

    // Apply location_id filter if provided
    if ($request->has('location_id')) {
        $query->where('location_id', $request->location_id);
    }

    // Filter by name
    if ($request->has('name')) {
        $query->where('name', 'like', '%' . $request->name . '%');
    }

    // Filter by amount range
    if ($request->has('amount_min') && $request->has('amount_max')) {
        $query->whereBetween('total_amount', [$request->amount_min, $request->amount_max]);
    }

    // Filter by invoice ID
    if ($request->has('invoice_id')) {
        $query->where('id', $request->invoice_id);
    }
    

    // Order by id in descending order
    $query->orderBy('id', 'DESC');

    // Get the filtered invoices
    $query->where('is_completed', 1);
    $invoices = $query->select([
        DB::raw('CASE WHEN type = "performa" THEN 0 ELSE CAST(serial_no AS SIGNED) END as id'),
        'name',
        'total_amount',
        'invoice_date',
        'total_cgst',
        'total_dgst',
        'total_igst',
        'type',
        DB::raw('ROUND((total_igst + total_dgst + total_cgst), 2) as tgst'),
        DB::raw('ROUND((total_amount - (total_igst + total_dgst + total_cgst)), 2) as amount_wgst')
    ])->get();

    // Calculate total amount and total transactions
    $totalAmount = round($invoices->sum('total_amount'), 2);
    $totalgst = round($invoices->sum('total_igst') + $invoices->sum('total_cgst') + $invoices->sum('total_dgst'), 2);
    $totalTransactions = $invoices->count();

    $excel = "https://business.architartgallery.in/api/invoice-excel.php?" . http_build_query($request->all());
    $pdf = "https://invoice.invoicemate.in/invoices.html?" . http_build_query($request->all());

    return response([
        'status' => true,
        'total_amount' => $totalAmount,
        'total_amount_wgst' => round($totalAmount-$totalgst,2),
        'tgst' => $totalgst,
        'total_transactions' => $totalTransactions,
        'pdf' => $pdf,
        'excel' => $excel,
        'data' => $invoices,
        'message' => "Data fetched."
    ], 200);
}
public function getExistedUser(Request $request)
{
    // Validate the request to ensure either 'name' or 'mobile_number' is provided
    $request->validate([
        'mobile_number' => 'required',
    ]);
    $mobileNumber = $request->input('mobile_number');

    // Query to find the user based on name or mobile number
    $query = Invoice::query();

    if ($mobileNumber) {
        $query->where('mobile_number', 'like', '%' . $mobileNumber . '%');
    }

    // Retrieve the first matching invoice
    $invoice = $query->first();

    // If no invoice found, return an error response
    if (!$invoice) {
        return response([
            'status' => false,
            'message' => 'No user found with the provided name or mobile number.'
        ], 404);
    }

    // Get billing and shipping addres
    $billingAddress = Addres::where('invoice_id', $invoice->id)->where('type', 'billing')->first();
    $shippingAddress = Addres::where('invoice_id', $invoice->id)->where('type', 'shipping')->first();

    // Prepare response data
    $data = [
        'name' => $invoice->name,
        'mobile_number' => $invoice->mobile_number,
        'customer_type' => $invoice->customer_type,
        'doc_no' => strtoupper($invoice->doc_no),
        'billing_id' => $invoice->billing_address_id,
        'shipping_id' => $invoice->shipping_address_id,
        'billing_address' => $billingAddress ? $billingAddress->only(['address_1', 'address_2', 'city', 'state', 'pincode']) : null,
        'shipping_address' => $shippingAddress ? $shippingAddress->only(['address_1', 'address_2', 'city', 'state', 'pincode']) : null,
    ];

    return response([
        'status' => true,
        'data' => $data,
        'message' => 'User data fetched successfully.'
    ], 200);
}

public function dashboardReport(Request $request)
{
    // Helper function to apply date filters
    $applyDateFilters = function ($query) use ($request) {
        if ($request->has('day')) {
            $query->whereDate('invoice_date', $request->day);
        }
        if ($request->has('month')) {
            $query->whereMonth('invoice_date', $request->month);
        }
        if ($request->has('week_start') && $request->has('week_end')) {
            $query->whereBetween('invoice_date', [$request->week_start, $request->week_end]);
        }
        if ($request->has('year')) {
            $query->whereYear('invoice_date', $request->year);
        }
    };

    // Get Sale Report
    $saleQuery = Invoice::query()->where('is_completed', 1)->where('business_id',$request->business_id)->where('location_id',$request->location_id);
    $applyDateFilters($saleQuery);
    $sales = $saleQuery->orderBy('id', 'DESC')->get(['id', 'name', 'total_amount', 'invoice_date', 'type']);
    $actualSaleAmount = round($sales->sum('total_amount'), 2);

    // Get Purchase Sale Invoice Report
    $purchaseSaleQuery = Invoice::query()->where('is_completed', 1)->where('type', 'normal')->where('business_id',$request->business_id)->where('location_id',$request->location_id);
    $applyDateFilters($purchaseSaleQuery);
    $purchaseSales = $purchaseSaleQuery->get(['id', 'total_dgst', 'total_cgst', 'total_igst', 'total_amount']);
    $totalGst = round($purchaseSales->sum(function($invoice) {
        return $invoice->total_dgst + $invoice->total_cgst + $invoice->total_igst;
    }), 2);
    $totalExcludingGst = round($purchaseSales->sum('total_amount') - $totalGst, 2);

    // Get Invoice Report (Item Purchases)
    $itemQuery = Item::query()->where('business_id',$request->business_id)->where('location_id',$request->location_id)
        ->join('invoices', 'items.invoice_id', '=', 'invoices.id')
        ->where('invoices.is_completed', 1)
        ->where('invoices.type', 'normal');
    $applyDateFilters($itemQuery);
    $items = $itemQuery->get(['items.quantity']);
    $totalItemsPurchased = $items->sum('quantity');

    // Get Expenses Report
    $expenseQuery = Expenses::query()->where('business_id',$request->business_id)->where('location_id',$request->location_id);
    // Apply date filters to expenses (assuming expenses have a 'created_at' field)
    if ($request->has('day')) {
        $expenseQuery->whereDate('created_at', $request->day);
    }
    if ($request->has('month')) {
        $expenseQuery->whereMonth('created_at', $request->month);
    }
    if ($request->has('week_start') && $request->has('week_end')) {
        $expenseQuery->whereBetween('created_at', [$request->week_start, $request->week_end]);
    }
    if ($request->has('year')) {
        $expenseQuery->whereYear('created_at', $request->year);
    }
    $expenses = $expenseQuery->get();
    $totalAmount = round($expenses->sum('amount'), 2);

    // Prepare response data
    $data = [
        'actual_sale_amount' => round(($actualSaleAmount-$totalGst),2),
        'total_excluding_gst' => $totalExcludingGst,
        'total_expense' => $totalAmount,
        'total_gst' => $totalGst,
        'total_items_purchased' => $totalItemsPurchased,
        'profit_loss' => null
    ];

    return response([
        'status' => true,
        'data' => $data,
        'message' => 'Dashboard data fetched successfully.'
    ], 200);
}


public function getBulkInvoices(Request $request)
{
    try {
        // Start with a base query
        $query = Invoice::with(['items.product', 'billingAddress', 'shippingAddress'])
            ->where('is_completed', 1);

        // Apply date range filter if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('invoice_date', [$request->start_date, $request->end_date]);
        }

        // Apply min amount filter if provided
        if ($request->has('min_amount')) {
            $query->where('total_amount', '>=', $request->min_amount);
        }

        // Apply max amount filter if provided
        if ($request->has('max_amount')) {
            $query->where('total_amount', '<=', $request->max_amount);
        }

        // Apply type filter if provided
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Apply payment mode filter if provided
        if ($request->has('payment_mode')) {
            $query->where('payment_mode', $request->payment_mode);
        }
        // Apply payment mode filter if provided
        if ($request->has('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        // Apply payment mode filter if provided
        if ($request->has('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        // Execute the query and get the results
        $invoices = $query->get();

        // Check if any invoices are found
        if ($invoices->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No invoices found matching the criteria.'], 404);
        }

        // Return the detailed invoices
        return response()->json([
            'status' => true, 
            'message' => 'Invoices retrieved successfully.', 
            'data' => $invoices
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false, 
            'message' => 'Failed to retrieve invoices.', 
            'error' => $e->getMessage()
        ], 500);
    }
}
public function getBulkInvoicesSelected(Request $request)
{
    try {
        // Start with a base query
        $query = Invoice::with(['items.product', 'billingAddress', 'shippingAddress'])
            ->leftJoin('businessses', 'invoices.business_id', '=', 'businessses.id')
            ->leftJoin('locations', 'invoices.location_id', '=', 'locations.id')
            ->select(
                'invoices.*',
                'businessses.gst as business_gst',
                'locations.location_name',
                'locations.address as location_address',
                'locations.email as location_email',
                'locations.phone as location_phone',
                'locations.alternate_phone as location_alternate_phone'
            )
            ->where('invoices.is_completed', 1);
            
        if ($request->has('ids') && is_array($request->ids)) {
            $query->whereIn('invoices.id', $request->ids);
        }

        // Execute the query and get the results
        $invoices = $query->get();

        // Check if any invoices are found
        if ($invoices->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No invoices found matching the criteria.'], 404);
        }

        // Process each invoice to add business_info
        $processedInvoices = $invoices->map(function ($invoice) {
            $invoice->business_info = [
                'name' => $invoice->location_name ?? 'Archit Art Gallery',
                'address' => $invoice->location_address ?? 'Shop No: 28 Kirti Nagar Furniture Block, Kirti Nagar, New Delhi – 110015',
                'phone' => $invoice->location_phone ?? '+91- 9868 200 002',
                'alternate_phone' => $invoice->location_alternate_phone ?? '+91- 9289 388 374',
                'gst' => $invoice->business_gst ?? '07AADPA2039E1ZF'
            ];
            
            // Remove the additional fields from the main invoice object
            unset($invoice->business_gst, $invoice->location_name, $invoice->location_address, 
                  $invoice->location_email, $invoice->location_phone, $invoice->location_alternate_phone);
            
            return $invoice;
        });

        // Return the detailed invoices
        return response()->json([
            'status' => true, 
            'message' => 'Invoices retrieved successfully.', 
            'data' => $processedInvoices
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false, 
            'message' => 'Failed to retrieve invoices.', 
            'error' => $e->getMessage()
        ], 500);
    }
}
 
 
public function getItemizedSalesReport(Request $request)
    {
        // Start building the query
        $query = DB::table('items')
            ->join('products', 'items.product_id', '=', 'products.id')
            ->join('invoices', 'items.invoice_id', '=', 'invoices.id')
            ->where('invoices.business_id', $request->business_id)
            ->where('invoices.location_id', $request->location_id)
            ->where('invoices.is_completed', 1)
            ->where('invoices.type', '=', 'normal');

        // // Filter by price range
        // if ($request->has('price_min')) {
        //     $query->where('items.price_of_one', '>=', $request->price_min);
        // }
        // if ($request->has('price_max')) {
        //     $query->where('items.price_of_one', '<=', $request->price_max);
        // }


                // Filter by day
            if($request->has('day')){
                $query->whereDate('invoices.invoice_date', $request->day);
            }

            // Filter by month
            if($request->has('month')){
                $query->whereMonth('invoices.invoice_date', $request->month);
            }

            

                // Filter by year
            if($request->has('year')){
                $query->whereYear('invoices.invoice_date', $request->year);
            }

            // Filter by week
            if($request->has('week_start')){
                // Assuming the week is passed as an array with start and end dates
                $query->whereBetween('invoices.invoice_date', [$request->week_start, $request->week_end]);
            }

                        // Filter by amount range
            if($request->has('amount_min') && $request->has('amount_max')){
                $amountMin = $request->amount_min;
                $amountMax = $request->amount_max;
                $query->whereBetween('items.price_of_one', [$amountMin, $amountMax]);
            } 


        // Get the results
        $results = $query->select('products.name as product_name', 
                                  'items.price_of_one', 
                                  DB::raw('SUM(items.quantity) as total_quantity'), 
                                  DB::raw('SUM(items.price_of_all) as total_sales'))
                         ->groupBy('products.name', 'items.product_id', 'items.price_of_one')
                         ->orderBy('items.price_of_one')
                         ->get();

        // Calculate total sales

       

        // Prepare data for table
        if ($request->has('purchase_at')) {
            $purchase_at = $request->purchase_at / 100;
            $tableData = $results->map(function ($item) use ($purchase_at) {
                $adjustedPrice = $item->price_of_one * $purchase_at;
                return [
                    'product_name' => $item->product_name,
                    'price_per_item' => round($adjustedPrice, 2),
                    'total_quantity' => $item->total_quantity,
                    'total_sales' => round($item->total_quantity * $adjustedPrice, 2)
                ];
            });
        } else {
            $tableData = $results->map(function ($item) {
                return [
                    'product_name' => $item->product_name,
                    'price_per_item' => $item->price_of_one,
                    'total_quantity' => $item->total_quantity,
                    'total_sales' => round($item->total_quantity * $item->price_of_one, 2)
                ];
            });
        }
        $totalSales = $tableData->sum('total_sales');
        $url = "https://business.architartgallery.in/api/itemised-excel.php?" . http_build_query($request->all());

        return response()->json([
            'status' => true,
            'excel' => $url,
            'total_sales' => round($totalSales, 2),
            'total_items' => $results->sum('total_quantity'),
            'table_data' => $tableData,
            'message' => "Data fetched successfully."
        ], 200);
    }

    public function getlocations(Request $request)
    {
        try {
            // Start with a base query
            $query = Locations::
                where('business_id', $request->business_id);
    
            // Execute the query and get the results
            $invoices = $query->get();
    
            // Check if any invoices are found
            if ($invoices->isEmpty()) {
                return response()->json(['status' => false, 'message' => 'No Location found matching the business Id.'], 404);
            }
    
            
    
            // Return the detailed invoices
            return response()->json([
                'status' => true, 
                'message' => 'Location retrieved successfully.', 
                'data' => $invoices
            ], 200);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => false, 
                'message' => 'Failed to retrieve invoices.', 
                'error' => $e->getMessage()
            ], 500);
        }
    }

   public function getProducts(Request $request)
    {
        $rules = [
            'business_id' => 'required|exists:businessses,id',
            'location_id' => 'required|exists:locations,id',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
        }

        try {
            $products = Product::where('business_id', $request->business_id)
                ->where('location_id', $request->location_id)
                ->where('is_temp', 0)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($products->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No products found for the given business and location.'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Products retrieved successfully.',
                'data' => $products
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve products.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Category CRUD
    public function createCategory(Request $request)
    {
        $rules = [
            'business_id' => 'required|exists:businessses,id',
            'location_id' => 'required|exists:locations,id',
            'name' => 'required|string|max:255',
            'hsn_code' => 'nullable|string|max:255',
            'image' => 'nullable', // base64 string
            'extension' => 'nullable|string|max:10',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
        }

        try {
            $category = new \App\Models\Category();
            $category->business_id = $request->business_id;
            $category->location_id = $request->location_id;
            $category->name = $request->name;
            $category->hsn_code = $request->hsn_code;

            if ($request->has('image') && $request->image) {
                $fileData = $request->image;
                $ext = $request->extension ?: $this->getFileExtension($fileData);
                $fileName = 'category_' . time() . '.' . $ext;
                $filePath = 'public/categories/' . $fileName;
                \Storage::put($filePath, base64_decode($fileData));
                $category->image = $filePath;
            }

            $category->save();

            return response()->json(['status' => true, 'message' => 'Category created successfully.', 'data' => $category], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to create category.', 'error' => $e->getMessage()], 500);
        }
    }

    public function listCategories(Request $request)
    {
        $rules = [
            'business_id' => 'required|exists:businessses,id',
            'location_id' => 'nullable|exists:locations,id',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
        }

        $query = \App\Models\Category::where('business_id', $request->business_id);

        // Filter by location_id if provided
        if ($request->has('location_id') && $request->location_id) {
            $query->where('location_id', $request->location_id);
        }

        $categories = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['status' => true, 'message' => 'Categories retrieved successfully.', 'data' => $categories], 200);
    }

    public function updateCategory(Request $request)
    {
        $rules = [
            'id' => 'required|exists:categories,id',
            'name' => 'sometimes|required|string|max:255',
            'location_id' => 'sometimes|required|exists:locations,id',
            'hsn_code' => 'nullable|string|max:255',
            'image' => 'nullable',
            'extension' => 'nullable|string|max:10',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
        }

        try {
            $category = \App\Models\Category::findOrFail($request->id);
            if ($request->has('name')) {
                $category->name = $request->name;
            }
            if ($request->has('location_id')) {
                $category->location_id = $request->location_id;
            }
            if ($request->has('hsn_code')) {
                $category->hsn_code = $request->hsn_code;
            }
            if ($request->has('image') && $request->image) {
                $fileData = $request->image;
                $ext = $request->extension ?: $this->getFileExtension($fileData);
                $fileName = 'category_' . time() . '.' . $ext;
                $filePath = 'public/categories/' . $fileName;
                \Storage::put($filePath, base64_decode($fileData));
                $category->image = $filePath;
            }
            $category->save();
            return response()->json(['status' => true, 'message' => 'Category updated successfully.', 'data' => $category], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update category.', 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteCategory($id)
    {
        try {
            $category = \App\Models\Category::findOrFail($id);
            $category->delete();
            return response()->json(['status' => true, 'message' => 'Category deleted successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to delete category.', 'error' => $e->getMessage()], 500);
        }
    }

    // Product Category (Art Category) CRUD
    public function createProductCategory(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
        }

        try {
            $productCategory = new \App\Models\ProductCategory();
            $productCategory->name = $request->name;
            $productCategory->category_id = $request->category_id;
            $productCategory->save();

            $productCategory->load('category');

            return response()->json(['status' => true, 'message' => 'Art Category created successfully.', 'data' => $productCategory], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to create art category.', 'error' => $e->getMessage()], 500);
        }
    }

    public function listProductCategories(Request $request)
    {
        $rules = [
            'business_id' => 'required|exists:businessses,id',
            'location_id' => 'nullable|exists:locations,id',
            'category_id' => 'nullable|exists:categories,id',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
        }

        $query = \App\Models\ProductCategory::with('category')
            ->whereHas('category', function($query) use ($request) {
                $query->where('business_id', $request->business_id);
                
                // Filter by location_id if provided
                if ($request->has('location_id') && $request->location_id) {
                    $query->where('location_id', $request->location_id);
                }
            });

        // Filter by category_id if provided
        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        $productCategories = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['status' => true, 'message' => 'Art Categories retrieved successfully.', 'data' => $productCategories], 200);
    }

    public function updateProductCategory(Request $request)
    {
        $rules = [
            'id' => 'required|exists:product_category,id',
            'name' => 'sometimes|required|string|max:255',
            'category_id' => 'sometimes|required|exists:categories,id',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
        }

        try {
            $productCategory = \App\Models\ProductCategory::findOrFail($request->id);
            if ($request->has('name')) {
                $productCategory->name = $request->name;
            }
            if ($request->has('category_id')) {
                $productCategory->category_id = $request->category_id;
            }
            $productCategory->save();

            $productCategory->load('category');

            return response()->json(['status' => true, 'message' => 'Art Category updated successfully.', 'data' => $productCategory], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update art category.', 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteProductCategory($id)
    {
        try {
            $productCategory = \App\Models\ProductCategory::findOrFail($id);
            $productCategory->delete();
            return response()->json(['status' => true, 'message' => 'Art Category deleted successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to delete art category.', 'error' => $e->getMessage()], 500);
        }
    }

    public function getProductCategoriesByCategory(Request $request)
    {
        $rules = [
            'category_id' => 'required|exists:categories,id',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
        }

        $productCategories = \App\Models\ProductCategory::with('category')
            ->where('category_id', $request->category_id)
            ->orderBy('name', 'asc')
            ->get();

        return response()->json(['status' => true, 'message' => 'Art Categories retrieved successfully.', 'data' => $productCategories], 200);
    }

    // Product CRUD with multiple images
    public function createProductWithImages(Request $request)
    {
        $rules = [
            'business_id' => 'required|exists:businessses,id',
            'location_id' => 'required|exists:locations,id',
            'name' => 'required|string|max:255',
            'hsn_code' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'art_category_id' => 'nullable|exists:product_category,id',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
        }

        try {
            $product = new Product();
            $product->business_id = $request->business_id;
            $product->location_id = $request->location_id;
            $product->name = $request->name;
            $product->hsn_code = $request->hsn_code;
            $product->price = $request->price;
            $product->category_id = $request->category_id;
            $product->art_category_id = $request->art_category_id;
            $product->product_serial_number = $request->product_serial_number;
            $product->item_code = $request->item_code;
            $product->height = $request->height;
            $product->width = $request->width;
            $product->is_framed = $request->is_framed ?? 0;
            $product->is_include_gst = $request->is_include_gst ?? 0;
            $product->artist_name = $request->artist_name;
            $product->quantity = $request->quantity;
            $product->is_temp = 0;
            $product->save();

            // Handle multiple images from multipart form data
            $uploadedFiles = $request->allFiles();
            
            // Method 1: Check if 'images' field exists (single file or array)
            if (isset($uploadedFiles['images'])) {
                $images = $uploadedFiles['images'];
                
                if (is_array($images)) {
                    foreach ($images as $index => $file) {
                        if ($file && $file->isValid()) {
                            $fileName = 'product_' . $product->id . '_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                            $filePath = $file->storeAs('public/product_images', $fileName);
                            
                            \App\Models\ProductImage::create([
                                'product_id' => $product->id,
                                'image' => $filePath,
                            ]);
                        }
                    }
                } else {
                    $file = $images;
                    if ($file && $file->isValid()) {
                        $fileName = 'product_' . $product->id . '_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                        $filePath = $file->storeAs('public/product_images', $fileName);
                        
                        \App\Models\ProductImage::create([
                            'product_id' => $product->id,
                            'image' => $filePath,
                        ]);
                    }
                }
            }
            // Method 2: Check for individual indexed files (images[0], images[1], etc.)
            else {
                $allInputs = $request->all();
                foreach ($allInputs as $key => $value) {
                    if (preg_match('/^images\[(\d+)\]$/', $key, $matches)) {
                        $index = $matches[1];
                        
                        // Try to get the file using the raw key
                        $file = null;
                        
                        if (isset($uploadedFiles[$key])) {
                            $file = $uploadedFiles[$key];
                        } else if ($request->hasFile($key)) {
                            $file = $request->file($key);
                        }
                        
                        if ($file && $file->isValid()) {
                            $fileName = 'product_' . $product->id . '_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                            $filePath = $file->storeAs('public/product_images', $fileName);
                            
                            \App\Models\ProductImage::create([
                                'product_id' => $product->id,
                                'image' => $filePath,
                            ]);
                        }
                    }
                }
            }

            $product->load(['category', 'artCategory', 'images']);
            
            return response()->json(['status' => true, 'message' => 'Product created successfully.', 'data' => $product], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to create product.', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateProductWithImages(Request $request)
    {
        $rules = [
            'id' => 'required|exists:products,id',
            'name' => 'sometimes|required|string|max:255',
            'hsn_code' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'art_category_id' => 'nullable|exists:product_category,id',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
        }

        try {
            $product = Product::findOrFail($request->id);
            if ($request->has('name')) $product->name = $request->name;
            if ($request->has('hsn_code')) $product->hsn_code = $request->hsn_code;
            if ($request->has('price')) $product->price = $request->price;
            if ($request->has('category_id')) $product->category_id = $request->category_id;
            if ($request->has('art_category_id')) $product->art_category_id = $request->art_category_id;
            if ($request->has('location_id')) $product->location_id = $request->location_id;
            if ($request->has('product_serial_number')) $product->product_serial_number = $request->product_serial_number;
            if ($request->has('item_code')) $product->item_code = $request->item_code;
            if ($request->has('height')) $product->height = $request->height;
            if ($request->has('width')) $product->width = $request->width;
            if ($request->has('is_framed')) $product->is_framed = $request->is_framed;
            if ($request->has('is_include_gst')) $product->is_include_gst = $request->is_include_gst;
            if ($request->has('artist_name')) $product->artist_name = $request->artist_name;
            if ($request->has('quantity')) $product->quantity = $request->quantity;
            $product->save();

            // Handle image deletions
            $allData = $request->all();
            foreach ($allData as $key => $value) {
                if (strpos($key, 'delete_image_ids[') === 0) {
                    $imageId = (int)$value;
                    $imageToDelete = \App\Models\ProductImage::where('id', $imageId)
                        ->where('product_id', $product->id)
                        ->first();
                    if ($imageToDelete) {
                        // Delete the file from storage
                        if (\Storage::exists($imageToDelete->image)) {
                            \Storage::delete($imageToDelete->image);
                        }
                        $imageToDelete->delete();
                    }
                }
            }

            // Handle new images from multipart form data
            $allInputs = $request->all();
            foreach ($allInputs as $key => $value) {
                if (strpos($key, 'images[') === 0) {
                    $file = $request->file($key);
                    if ($file && $file->isValid()) {
                        $fileName = 'product_' . $product->id . '_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                        $filePath = $file->storeAs('public/product_images', $fileName);
                        
                        \App\Models\ProductImage::create([
                            'product_id' => $product->id,
                            'image' => $filePath,
                        ]);
                    }
                }
            }

            $product->load(['category', 'artCategory', 'images']);
            return response()->json(['status' => true, 'message' => 'Product updated successfully.', 'data' => $product], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update product.', 'error' => $e->getMessage()], 500);
        }
    }

    public function getProductWithImages($id)
    {
        try {
            $product = Product::with(['category', 'artCategory', 'images'])->findOrFail($id);
            return response()->json(['status' => true, 'message' => 'Product retrieved successfully.', 'data' => $product], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to retrieve product.', 'error' => $e->getMessage()], 500);
        }
    }

    // Product Dashboard and User Inquiry APIs
    public function getFilteredProducts(Request $request)
    {
        $rules = [
            'business_id' => 'required|exists:businessses,id',
            'location_id' => 'required|exists:locations,id',
        ];
        
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
        }

        try {
            // Debug: Log the request parameters
            \Log::info('Filter request:', $request->all());
            
            $query = Product::with(['category', 'artCategory', 'images'])
                ->where('business_id', $request->business_id)
                ->where('location_id', $request->location_id);
                // Temporarily removed ->where('is_temp', 0) to debug
            
            // Debug: Log the base query count
            $baseCount = $query->count();
            \Log::info("Base query count (business_id: {$request->business_id}, location_id: {$request->location_id}): {$baseCount}");

            // Category filter (multiple categories)
            if ($request->has('categories') && is_array($request->categories)) {
                $query->whereIn('category_id', $request->categories);
            }

            // Price range filter
            if ($request->has('min_price') && is_numeric($request->min_price)) {
                $query->where('price', '>=', $request->min_price);
            }
            if ($request->has('max_price') && is_numeric($request->max_price)) {
                $query->where('price', '<=', $request->max_price);
            }

            // Size filters
            if ($request->has('min_height') && is_numeric($request->min_height)) {
                $query->where('height', '>=', $request->min_height);
            }
            if ($request->has('max_height') && is_numeric($request->max_height)) {
                $query->where('height', '<=', $request->max_height);
            }
            if ($request->has('min_width') && is_numeric($request->min_width)) {
                $query->where('width', '>=', $request->min_width);
            }
            if ($request->has('max_width') && is_numeric($request->max_width)) {
                $query->where('width', '<=', $request->max_width);
            }

            // Artist name filter
            if ($request->has('artist_name') && !empty($request->artist_name)) {
                $query->where('artist_name', 'LIKE', '%' . $request->artist_name . '%');
            }

            // Framed filter
            if ($request->has('is_framed')) {
                $query->where('is_framed', $request->is_framed);
            }

            // Include GST filter
            if ($request->has('is_include_gst')) {
                $query->where('is_include_gst', $request->is_include_gst);
            }

            // Quantity filter
            if ($request->has('min_quantity') && is_numeric($request->min_quantity)) {
                $query->where('quantity', '>=', $request->min_quantity);
            }

            // Product serial number filter
            if ($request->has('product_serial_number') && !empty($request->product_serial_number)) {
                $query->where('product_serial_number', 'LIKE', '%' . $request->product_serial_number . '%');
            }

            // Item code filter
            if ($request->has('item_code') && !empty($request->item_code)) {
                $query->where('item_code', 'LIKE', '%' . $request->item_code . '%');
            }

            // Name filter
            if ($request->has('name') && !empty($request->name)) {
                $query->where('name', 'LIKE', '%' . $request->name . '%');
            }

            // HSN code filter
            if ($request->has('hsn_code') && !empty($request->hsn_code)) {
                $query->where('hsn_code', 'LIKE', '%' . $request->hsn_code . '%');
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $allowedSortFields = ['name', 'price', 'created_at', 'artist_name', 'height', 'width', 'quantity'];
            
            if (in_array($sortBy, $allowedSortFields)) {
                $query->orderBy($sortBy, $sortOrder);
            }

            // Debug: Log the final query count before pagination
            $finalCount = $query->count();
            \Log::info("Final query count after filters: {$finalCount}");
            
            // Pagination
            $perPage = $request->get('per_page', 12);
            $products = $query->paginate($perPage);

            // Get filter options for UI
            $filterOptions = [
                'categories' => Category::where('business_id', $request->business_id)
                    ->where('location_id', $request->location_id)
                    ->get(['id', 'name', 'hsn_code']),
                'price_range' => [
                    'min' => Product::where('business_id', $request->business_id)
                        ->where('location_id', $request->location_id)
                        ->min('price'),
                    'max' => Product::where('business_id', $request->business_id)
                        ->where('location_id', $request->location_id)
                        ->max('price')
                ],
                'size_range' => [
                    'height' => [
                        'min' => Product::where('business_id', $request->business_id)
                            ->where('location_id', $request->location_id)
                            ->min('height'),
                        'max' => Product::where('business_id', $request->business_id)
                            ->where('location_id', $request->location_id)
                            ->max('height')
                    ],
                    'width' => [
                        'min' => Product::where('business_id', $request->business_id)
                            ->where('location_id', $request->location_id)
                            ->min('width'),
                        'max' => Product::where('business_id', $request->business_id)
                            ->where('location_id', $request->location_id)
                            ->max('width')
                    ]
                ],
                'artists' => Product::where('business_id', $request->business_id)
                    ->where('location_id', $request->location_id)
                    ->whereNotNull('artist_name')
                    ->distinct()
                    ->pluck('artist_name')
                    ->filter()
                    ->values()
            ];

            // Debug: Also return the raw counts for debugging
            return response()->json([
                'status' => true,
                'message' => 'Products retrieved successfully.',
                'data' => $products,
                'filter_options' => $filterOptions,
                'debug' => [
                    'base_count' => $baseCount,
                    'final_count' => $finalCount,
                    'business_id' => $request->business_id,
                    'location_id' => $request->location_id
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to retrieve products.', 'error' => $e->getMessage()], 500);
        }
    }

    // Debug endpoint to check all products
    public function getAllProductsDebug(Request $request)
    {
        try {
            $products = Product::with(['category', 'images'])->get();
            $totalCount = Product::count();
            $businessCount = Product::where('business_id', $request->business_id ?? 0)->count();
            $locationCount = Product::where('location_id', $request->location_id ?? 0)->count();
            
            return response()->json([
                'status' => true,
                'message' => 'Debug info retrieved',
                'data' => [
                    'total_products' => $totalCount,
                    'business_products' => $businessCount,
                    'location_products' => $locationCount,
                    'sample_products' => $products->take(5)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Debug failed', 'error' => $e->getMessage()], 500);
        }
    }

    public function submitUserInquiry(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'mobile' => 'nullable|string|max:20',
            'business_id' => 'required|exists:businessses,id',
            'location_id' => 'required|exists:locations,id',
            'filter_data' => 'nullable|array',
            'selected_products' => 'nullable|array',
            'inquiry_notes' => 'nullable|string'
        ];
        
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
        }

        try {
            $inquiry = UserInquiry::create([
                'name' => $request->name,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'business_id' => $request->business_id,
                'location_id' => $request->location_id,
                'filter_data' => $request->filter_data,
                'selected_products' => $request->selected_products,
                'inquiry_notes' => $request->inquiry_notes,
                'status' => 'pending'
            ]);

            $inquiry->load(['business', 'location']);

            return response()->json([
                'status' => true,
                'message' => 'Inquiry submitted successfully.',
                'data' => $inquiry
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to submit inquiry.', 'error' => $e->getMessage()], 500);
        }
    }

    public function getUserInquiries(Request $request)
    {
        $rules = [
            'business_id' => 'required|exists:businessses,id',
        ];
        
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
        }

        try {
            $query = UserInquiry::with(['business', 'location'])
                ->where('business_id', $request->business_id);

            // Location filter
            if ($request->has('location_id')) {
                $query->where('location_id', $request->location_id);
            }

            // Status filter
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Date range filter
            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            // Search by name, email, or mobile
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', '%' . $search . '%')
                      ->orWhere('email', 'LIKE', '%' . $search . '%')
                      ->orWhere('mobile', 'LIKE', '%' . $search . '%');
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $allowedSortFields = ['name', 'email', 'mobile', 'status', 'created_at'];
            
            if (in_array($sortBy, $allowedSortFields)) {
                $query->orderBy($sortBy, $sortOrder);
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $inquiries = $query->paginate($perPage);

            return response()->json([
                'status' => true,
                'message' => 'Inquiries retrieved successfully.',
                'data' => $inquiries
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to retrieve inquiries.', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateInquiryStatus(Request $request)
    {
        $rules = [
            'inquiry_id' => 'required|exists:user_inquiries,id',
            'status' => 'required|in:pending,contacted,completed,cancelled'
        ];
        
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
        }

        try {
            $inquiry = UserInquiry::find($request->inquiry_id);
            $inquiry->status = $request->status;
            $inquiry->save();

            $inquiry->load(['business', 'location']);

            return response()->json([
                'status' => true,
                'message' => 'Inquiry status updated successfully.',
                'data' => $inquiry
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update inquiry status.', 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteProduct($id)
    {
        try {
            $product = Product::findOrFail($id);
            
            // Delete associated product images from storage
            $productImages = \App\Models\ProductImage::where('product_id', $id)->get();
            foreach ($productImages as $image) {
                if (Storage::exists($image->image)) {
                    Storage::delete($image->image);
                }
                $image->delete();
            }
            
            // Delete the product
            $product->delete();
            
            return response()->json([
                'status' => true, 
                'message' => 'Product deleted successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false, 
                'message' => 'Failed to delete product.', 
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getBusinessDetails($businessId)
    {
        try {
            // Find the business by ID
            $business = Businesss::find($businessId);
            
            if (!$business) {
                return response()->json([
                    'status' => false,
                    'message' => 'Business not found.'
                ], 404);
            }
            
            // Return only the required fields
            $businessDetails = [
                'id' => $business->id,
                'business_name' => $business->business_name,
                'logo' => $business->logo,
                'phone' => $business->phone,
                'email' => $business->email,
                'gst' => $business->gst,
                'is_active' => $business->is_active
            ];
            
            return response()->json([
                'status' => true,
                'message' => 'Business details retrieved successfully.',
                'data' => $businessDetails
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve business details.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAllProductsWithImages(Request $request)
    {
        try {
            $query = Product::with(['category', 'artCategory', 'images'])
                ->where('is_temp', 0);

            // Filter by business_id if provided
            if ($request->has('business_id') && !empty($request->business_id)) {
                $query->where('business_id', $request->business_id);
            }

            // Filter by location_id if provided
            if ($request->has('location_id') && !empty($request->location_id)) {
                $query->where('location_id', $request->location_id);
            }

            // If neither business_id nor location_id is provided, return error
            if (!$request->has('business_id') && !$request->has('location_id')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Either business_id or location_id is required.'
                ], 400);
            }

            // Get products with pagination
            $perPage = $request->get('per_page', 999);
            $products = $query->paginate($perPage);

            return response()->json([
                'status' => true,
                'message' => 'Products retrieved successfully.',
                'data' => $products
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve products.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
